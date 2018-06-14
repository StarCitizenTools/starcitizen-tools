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
	 * Convenience function to determine whether the
	 * Schema namespace is enabled
	 *
	 * @return bool
	 */
	public static function isSchemaNamespaceEnabled() {
		global $wgEventLoggingDBname, $wgDBname;

		return $wgEventLoggingDBname === $wgDBname;
	}

	/**
	 * ApiMain::moduleManager hook to register jsonschema
	 * API module only if the Schema namespace is enabled
	 *
	 * @param ApiModuleManager $moduleManager
	 */
	public static function onApiMainModuleManager( ApiModuleManager $moduleManager ) {
		if ( self::isSchemaNamespaceEnabled() ) {
			$moduleManager->addModule(
				'jsonschema',
				'action',
				ApiJsonSchema::class
			);
		}
	}

	/**
	 * Declares JSON as the code editor language for Schema: pages.
	 * This hook only runs if the CodeEditor extension is enabled.
	 * @param Title $title
	 * @param string &$lang Page language.
	 * @return bool
	 */
	static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		if ( self::isSchemaNamespaceEnabled()
			&& $title->inNamespace( NS_SCHEMA )
		) {
			$lang = 'json';
		}
		return true;
	}

	/**
	 * Validates that the revised contents are valid JSON.
	 * If not valid, rejects edit with error message.
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 * @return True
	 */
	static function onEditFilterMergedContent( $context, $content, $status, $summary,
		$user, $minoredit
	) {
		$title = $context->getTitle();

		if ( !self::isSchemaNamespaceEnabled()
			|| !$title->inNamespace( NS_SCHEMA )
		) {
			return true;
		}

		if ( !preg_match( '/^[a-zA-Z0-9_-]{1,63}$/', $title->getText() ) ) {
			$status->fatal( 'badtitle' );
			return true;
		}

		if ( !$content instanceof JsonSchemaContent ) {
			return true;
		}

		try {
			$content->validate();
		} catch ( JsonSchemaException $e ) {
			$status->fatal( $context->msg( $e->getCode(), $e->args ) );
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

		if ( self::isSchemaNamespaceEnabled()
			&& $title->inNamespace( NS_SCHEMA ) && $revId !== null
		) {
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
	 * @return bool
	 */
	static function onMovePageIsValidMove( Title $currentTitle, Title $newTitle, Status $status ) {
		if ( !self::isSchemaNamespaceEnabled() ) {
			// Namespace isn't even enabled
			return true;
		} elseif ( $currentTitle->inNamespace( NS_SCHEMA ) ) {
			$status->fatal( 'eventlogging-error-move-source' );
			return false;
		} elseif ( $newTitle->inNamespace( NS_SCHEMA ) ) {
			$status->fatal( 'eventlogging-error-move-destination' );
			return false;
		}
		return true;
	}
}
