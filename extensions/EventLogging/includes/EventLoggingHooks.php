<?php
/**
 * Hooks for EventLogging extension.
 *
 * @file
 *
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class EventLoggingHooks {

	/**
	 * Emit a debug log message for each invalid or unset
	 * configuration variable (if any).
	 */
	public static function onSetup() {
		global $wgMemc;

		if ( get_class( $wgMemc ) === 'EmptyBagOStuff' ) {
			wfDebugLog( 'EventLogging', 'No suitable memcached driver found.' );
		}

		foreach ( [
			'wgEventLoggingBaseUri',
			'wgEventLoggingDBname',
			'wgEventLoggingSchemaApiUri'
		] as $configVar ) {
			if ( !isset( $GLOBALS[ $configVar ] ) || $GLOBALS[ $configVar ] === false ) {
				wfDebugLog( 'EventLogging', "$configVar has not been configured." );
			}
		}
	}

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$out->addModules( [ 'ext.eventLogging.subscriber' ] );
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * Allows extensions to register schema modules by adding keys to an
	 * associative array which is passed by reference to each handler. The
	 * array maps schema names to numeric revision IDs. By using this hook
	 * handler rather than registering modules directly, extensions can have
	 * a soft dependency on EventLogging. If EventLogging is not present, the
	 * hook simply never fires. To log events for schemas that have been
	 * declared in this fashion, use mw#track.
	 *
	 * @par Example using a hook
	 * @code
	 * $wgHooks[ 'EventLoggingRegisterSchemas' ][] = function ( &$schemas ) {
	 *     $schemas[ 'MultimediaViewerNetworkPerformance' ] = 7917896;
	 * };
	 * @endcode
	 * @par Example using extension.json
	 * @code
	 * {
	 *     "EventLoggingSchemas": {
	 *         "MultimediaViewerNetworkPerformance": 7917896
	 *     }
	 * }
	 * @endcode
	 *
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader &$resourceLoader ) {
		global $wgEventLoggingSchemas;

		$extRegistry = ExtensionRegistry::getInstance();

		$schemas = $extRegistry->getAttribute( 'EventLoggingSchemas' ) + $wgEventLoggingSchemas;

		Hooks::run( 'EventLoggingRegisterSchemas', [ &$schemas ] );

		$modules = [];
		foreach ( $schemas as $schemaName => $rev ) {
			$modules[ "schema.$schemaName" ] = [
				'class'    => 'ResourceLoaderSchemaModule',
				'schema'   => $schemaName,
				'revision' => $rev,
			];
		}
		$resourceLoader->register( $modules );
	}

	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgEventLoggingBaseUri;

		$vars[ 'wgEventLoggingBaseUri' ] = $wgEventLoggingBaseUri;
		return true;
	}

	/**
	 * @param array &$testModules
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( &$testModules, &$resourceLoader ) {
		$testModules[ 'qunit' ][ 'ext.eventLogging.tests' ] = [
			'scripts'       => [ 'tests/ext.eventLogging.tests.js' ],
			'dependencies'  => [ 'ext.eventLogging' ],
			'localBasePath' => __DIR__ . '/..',
			'remoteExtPath' => 'EventLogging',
		];
		return true;
	}
}
