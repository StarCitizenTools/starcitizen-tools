<?php
/**
 * Hooks for managing JSON Schema namespace and content model.
 *
 * @file
 * @ingroup Extensions
 * @ingroup UploadWizard
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class CampaignHooks {

	/**
	 * FIXME: This should be done as a DataUpdate
	 *
	 * Sets up appropriate entries in the uc_campaigns table for each Campaign
	 * Acts everytime a page in the NS_CAMPAIGN namespace is saved
	 */
	public static function onPageContentSaveComplete(
		$article, $user, $content, $summary, $isMinor, $isWatch,
		$section, $flags, $revision, $status, $baseRevId
	) {
		if ( !$content instanceof CampaignContent ) {
			return true;
		}

		$dbw = wfGetDB( DB_MASTER );

		$campaignData = $content->getJsonData();
		$insertData = array(
			'campaign_enabled' => $campaignData['enabled'] ? 1 : 0
		);
		$success = $dbw->upsert(
			'uw_campaigns',
			array_merge( array(
				'campaign_name' => $article->getTitle()->getDBkey()
			), $insertData ),
			array( 'campaign_name' ),
			$insertData
		);

		$campaign = new UploadWizardCampaign( $article->getTitle(), $content->getJsonData() );
		$dbw->onTransactionPreCommitOrIdle( function () use ( $campaign ) {
			$campaign->invalidateCache();
		} );

		return $success;
	}

	/**
	 * Invalidates the cache for a campaign when any of its dependents are edited. The 'dependents'
	 * are tracked by entries in the templatelinks table, which are inserted by using the
	 * PageContentSaveComplete hook.
	 *
	 * This is usually run via the Job Queue mechanism.
	 */
	public static function onLinksUpdateComplete( LinksUpdate &$linksupdate ) {
		if ( !$linksupdate->getTitle()->inNamespace( NS_CAMPAIGN ) ) {
			return true;
		}

		$campaign = new UploadWizardCampaign( $linksupdate->getTitle() );
		$campaign->invalidateCache();

		return true;
	}
	/**
	 * Deletes entries from uc_campaigns table when a Campaign is deleted
	 */
	public static function onArticleDeleteComplete(
		$article, $user, $reason, $id, $content, $logEntry
	) {
		if ( !$article->getTitle()->inNamespace( NS_CAMPAIGN ) ) {
			return true;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->onTransactionPreCommitOrIdle( function () use ( $dbw, $article ) {
			$dbw->delete(
				'uw_campaigns',
				array( 'campaign_name' => $article->getTitle()->getDBKey() )
			);
		} );

		return true;
	}

	/**
	 * Update campaign names when the Campaign page moves
	 */
	public static function onTitleMoveComplete(
		Title $oldTitle, Title $newTitle, $user, $pageid, $redirid
	) {
		if ( !$oldTitle->inNamespace( NS_CAMPAIGN ) ) {
			return true;
		}

		$dbw = wfGetDB( DB_MASTER );
		$success = $dbw->update(
			'uw_campaigns',
			array( 'campaign_name' => $newTitle->getDBKey() ),
			array( 'campaign_name' => $oldTitle->getDBKey() )
		);

		return $success;
	}

	/**
	 * Declares JSON as the code editor language for Campaign: pages.
	 * This hook only runs if the CodeEditor extension is enabled.
	 * @param Title $title
	 * @param string &$lang Page language.
	 * @return bool
	 */
	static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		if ( $title->inNamespace( NS_CAMPAIGN ) ) {
			$lang = 'json';
		}
		return true;
	}

	/**
	 * Validates that the revised contents are valid JSON.
	 * If not valid, rejects edit with error message.
	 * @param EditPage $editor
	 * @param string $text Content of the revised article.
	 * @param string &$error Error message to return.
	 * @param string $summary Edit summary provided for edit.
	 * @return True
	 */
	static function onEditFilterMerged( $editor, $text, &$error, $summary ) {
		if ( !$editor->getTitle()->inNamespace( NS_CAMPAIGN ) ) {
			return true;
		}

		$content = new CampaignContent( $text );

		try {
			$content->validate();
		} catch ( JsonSchemaException $e ) {
			$error = $e->getMessage();
		}

		return true;
	}
}
