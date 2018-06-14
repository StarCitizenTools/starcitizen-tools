<?php
/**
 * Static functions for extension.
 *
 * @file
 * @author Robert Leverington
 * @license GPL-2.0-or-later
 */

/**
 * Static functions for Babel extension.
 */
class BabelStatic {
	/**
	 * Registers the parser function hook.
	 *
	 * @param Parser $parser
	 *
	 * @return bool True.
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'babel', [ 'Babel', 'Render' ] );

		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'babel', __DIR__ . '/../babel.sql' );
	}

	/**
	 * @param LinksUpdate $linksUpdate
	 */
	public static function onLinksUpdate( LinksUpdate $linksUpdate ) {
		global $wgBabelCentralDb;

		$title = $linksUpdate->getTitle();
		// Has to be a root userpage
		if ( !$title->inNamespace( NS_USER ) || !$title->getRootTitle()->equals( $title ) ) {
			return;
		}

		// And the user has to exist
		$user = User::newFromName( $title->getText() );
		if ( !$user || !$user->getId() ) {
			return;
		}

		$babelDB = new MediaWiki\Babel\Database();
		$data = $linksUpdate->getParserOutput()->getExtensionData( 'babel' ) ?: [];
		$changed = $babelDB->setForUser( $user->getId(), $data );
		if ( $changed ) {
			$cache = ObjectCache::getMainWANInstance();
			$cache->touchCheckKey( $cache->makeKey( 'babel', 'userLanguages', $user->getId() ) );
			if ( $wgBabelCentralDb === wfWikiID() ) {
				// If this is the central wiki, invalidate all of the local caches
				$centralId = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
				if ( $centralId ) {
					$cache->touchCheckKey( $cache->makeGlobalKey( 'babel', 'userLanguages', $centralId ) );
				}
			}
		}
	}
}
