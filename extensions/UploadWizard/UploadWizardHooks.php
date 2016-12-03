<?php
class UploadWizardHooks {

	/**
	 * Schema update to set up the needed database tables.
	 *
	 * @since 1.2
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return true
	 */
	public static function onSchemaUpdate( /* DatabaseUpdater */ $updater = null ) {
		$dbfile = __DIR__ . '/UploadWizard.' . $updater->getDB()->getType() . '.sql';
		if ( !file_exists( $dbfile ) ) {
			$dbfile = __DIR__ . '/UploadWizard.sql';
		}
		$updater->addExtensionTable( 'uw_campaigns', $dbfile );
		$updater->addExtensionUpdate( array(
			'addIndex',
			'uw_campaigns',
			'uw_campaigns_name',
			__DIR__ . '/sql/UW_IndexCampaignsName.sql',
			true
		) );
		$updater->addExtensionUpdate( array(
			'addIndex',
			'uw_campaigns',
			'uw_campaigns_enabled',
			__DIR__ . '/sql/UW_IndexCampaignsEnabled.sql',
			true
		) );

		return true;
	}

	/**
	 * Adds the preferences of UploadWizard to the list of available ones.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @since 1.2
	 *
	 * @param User $user
	 * @param array $preferences
	 *
	 * @return true
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$config = UploadWizardConfig::getConfig();

		// User preference to skip the licensing tutorial, provided it's not globally disabled
		if ( UploadWizardConfig::getSetting( 'tutorial' ) != array() ) {
			$preferences['upwiz_skiptutorial'] = array(
				'type' => 'check',
				'label-message' => 'mwe-upwiz-prefs-skiptutorial',
				'section' => 'uploads/upwiz-interface'
			);
		}

		if ( UploadWizardConfig::getSetting( 'enableLicensePreference' ) ) {
			$licenseConfig = UploadWizardConfig::getSetting( 'licenses' );

			$licenses = array();

			$licensingOptions = UploadWizardConfig::getSetting( 'licensing' );

			$ownWork = $licensingOptions['ownWork'];
			foreach ( $ownWork['licenses'] as $license ) {
				$licenseMessage = self::getLicenseMessage( $license, $licenseConfig );
				$licenseKey = wfMessage( 'mwe-upwiz-prefs-license-own', $licenseMessage )->text();
				$licenses[$licenseKey] = 'ownwork-' . $license;
			}

			$thirdParty = UploadWizardConfig::getThirdPartyLicenses();
			$hasCustom = false;
			foreach ( $thirdParty as $license ) {
				if ( $license !== 'custom' ) {
					$licenseMessage = self::getLicenseMessage( $license, $licenseConfig );
					$licenseKey = wfMessage( 'mwe-upwiz-prefs-license-thirdparty', $licenseMessage )->text();
					$licenses[$licenseKey] = 'thirdparty-' . $license;
				} else {
					$hasCustom = true;
				}
			}

			$licenses = array_merge(
				array(
					wfMessage( 'mwe-upwiz-prefs-def-license-def' )->text() => 'default'
				),
				$licenses
			);

			if ( $hasCustom ) {
				// The "custom license" option must be last, otherwise the text referring to "following
				// wikitext" and "last option above" makes no sense.
				$licenseMessage = self::getLicenseMessage( 'custom', $licenseConfig );
				$licenseKey = wfMessage( 'mwe-upwiz-prefs-license-thirdparty', $licenseMessage )->text();
				$licenses[$licenseKey] = 'thirdparty-custom';
			};

			$preferences['upwiz_deflicense'] = array(
				'type' => 'radio',
				'label-message' => 'mwe-upwiz-prefs-def-license',
				'section' => 'uploads/upwiz-licensing',
				'options' => $licenses
			);

			if ( $hasCustom ) {
				$preferences['upwiz_deflicense_custom'] = array(
					'type' => 'text',
					'label-message' => 'mwe-upwiz-prefs-def-license-custom',
					'help-message' => 'mwe-upwiz-prefs-def-license-custom-help',
					'section' => 'uploads/upwiz-licensing',
				);
			}
		}

		// Setting for maximum number of simultaneous uploads (always lower than the server-side config)
		if ( $config[ 'maxSimultaneousConnections' ] > 1 ) {
			// Hack to make the key and value the same otherwise options are added wrongly.
			$range = range( 0, $config[ 'maxSimultaneousConnections' ] );
			$range[0] = 'default';

			$preferences['upwiz_maxsimultaneous'] = array(
				'type' => 'select',
				'label-message' => 'mwe-upwiz-prefs-maxsimultaneous-upload',
				'section' => 'uploads/upwiz-experimental',
				'options' => $range
			);
		}

		return true;
	}

	/**
	 * Hook to blacklist flickr images by intercepting upload from url
	 */
	public static function onIsUploadAllowedFromUrl( $url, &$allowed ) {
		if ( $allowed ) {
			$flickrBlacklist = new UploadWizardFlickrBlacklist(
				UploadWizardConfig::getConfig(),
				RequestContext::getMain()
			);
			if ( $flickrBlacklist->isBlacklisted( $url ) ) {
				$allowed = false;
			}
		}
		return true;
	}

	/**
	 * Get JavaScript test modules
	 * @param array $testModules
	 * @param ResourceLoader resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules(
		array &$testModules,
		ResourceLoader &$resourceLoader
	) {
		$testModules['qunit']['ext.uploadWizard.unit.tests'] = array(
			'scripts' => array(
				'tests/qunit/controller/uw.controller.Deed.test.js',
				'tests/qunit/controller/uw.controller.Details.test.js',
				'tests/qunit/controller/uw.controller.Step.test.js',
				'tests/qunit/controller/uw.controller.Thanks.test.js',
				'tests/qunit/controller/uw.controller.Tutorial.test.js',
				'tests/qunit/controller/uw.controller.Upload.test.js',
				'tests/qunit/transports/mw.FormDataTransport.test.js',
				'tests/qunit/uw.EventFlowLogger.test.js',
				'tests/qunit/uw.ConcurrentQueue.test.js',
				'tests/qunit/mw.UploadWizard.test.js',
				'tests/qunit/mw.UploadWizardUpload.test.js',
				'tests/qunit/mw.UploadWizardLicenseInput.test.js',
				'tests/qunit/mw.FlickrChecker.test.js',
				'tests/qunit/mw.UploadWizardDetails.test.js',
				'tests/qunit/mw.fileApi.test.js',
			),
			'dependencies' => array(
				'ext.uploadWizard',
			),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'UploadWizard',
		);
	}

	/**
	 * Hook to add unit tests
	 * @param array $files list of testcases
	 * @return bool
	 */
	public static function onUnitTestsList( array &$files ) {
		$testDir = __DIR__ . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'phpunit';
		$files = array_merge( $files, glob( $testDir . DIRECTORY_SEPARATOR . '*Test.php' ) );
		return true;
	}

	/**
	 * Helper function to get the message for a license.
	 *
	 * @since 1.2
	 *
	 * @param string $licenseName
	 * @param array $licenseConfig
	 *
	 * @return string
	 */
	public static function getLicenseMessage( $licenseName, array $licenseConfig ) {
		if ( array_key_exists( 'url', $licenseConfig[$licenseName] ) ) {
			return wfMessage(
				$licenseConfig[$licenseName]['msg'],
				'',
				$licenseConfig[$licenseName]['url']
			)->parse();
		} else {
			return wfMessage( $licenseConfig[$licenseName]['msg'] )->text();
		}
	}
}
