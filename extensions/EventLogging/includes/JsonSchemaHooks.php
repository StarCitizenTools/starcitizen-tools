<?php
/**
 * Hooks for managing JSON Schema namespace and content model.
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class JsonSchemaHooks {

	/**
	 * Registers API module and hooks which should only run if the JSON
	 * Schema namespace is enabled for this wiki.
	 * @return bool: Whether hooks and handler were registered.
	 */
	static function registerHandlers() {
		global $wgAPIModules, $wgHooks, $wgEventLoggingDBname, $wgDBname;

		if ( $wgEventLoggingDBname === $wgDBname ) {
			$wgHooks[ 'BeforePageDisplay' ][] = 'JsonSchemaHooks::onBeforePageDisplay';
			$wgHooks[ 'EditFilterMerged' ][] = 'JsonSchemaHooks::onEditFilterMerged';
			$wgHooks[ 'CodeEditorGetPageLanguage' ][] = 'JsonSchemaHooks::onCodeEditorGetPageLanguage';
			$wgHooks[ 'MovePageIsValidMove' ][] = 'JsonSchemaHooks::onMovePageIsValidMove';
			$wgAPIModules[ 'jsonschema' ] = 'ApiJsonSchema';
			return true;
		}
		return false;
	}

	/**
	 * Declares JSON as the code editor language for Schema: pages.
	 * This hook only runs if the CodeEditor extension is enabled.
	 * @param Title $title
	 * @param string &$lang Page language.
	 * @return bool
	 */
	static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		if ( $title->inNamespace( NS_SCHEMA ) ) {
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
		$title = $editor->getTitle();

		if ( $title->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		if ( !preg_match( '/^[a-zA-Z0-9_-]{1,63}$/', $title->getText() ) ) {
			$error = wfMessage( 'badtitle' )->text();
			return true;
		}

		$content = new JsonSchemaContent( $text );

		try {
			$content->validate();
		} catch ( JsonSchemaException $e ) {
			$error = $e->getMessage();
		}

		return true;
	}

	/**
	 * Add the revision id as the subtitle on NS_SCHEMA pages.
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @return bool
	 */
	static function onBeforePageDisplay( &$out, &$skin ) {
		$title = $out->getTitle();
		$revId = $out->getRevisionId();

		if ( $title->inNamespace( NS_SCHEMA ) && $revId !== null ) {
			$out->addSubtitle( $out->msg( 'eventlogging-revision-id' )
				// We use 'rawParams' rather than 'numParams' to make it
				// easy to copy/paste the value into code.
				->rawParams( $revId )
				->escaped() );
		}
		return true;
	}

	/**
	 * Prohibit moving (renaming) Schema pages, as doing so violates
	 * immutability guarantees.
	 *
	 * @param Title $currentTitle
	 * @param Title $newTitle
	 * @param Status $status
	 */
	static function onMovePageIsValidMove( Title $currentTitle, Title $newTitle, Status $status ) {
		if ( $currentTitle->inNamespace( NS_SCHEMA ) ) {
			$status->fatal( 'eventlogging-error-move-source' );
			return false;
		} elseif ( $newTitle->inNamespace( NS_SCHEMA ) ) {
			$status->fatal( 'eventlogging-error-move-destination' );
			return false;
		}
		return true;
	}
}
