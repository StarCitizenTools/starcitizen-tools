<?php

/**
 * Test the Upload Wizard Configuration
 *
 * @group Upload
 * @covers UploadWizardCampaign
 * @covers UploadWizardConfig
 */
class UploadWizardConfigTest extends MediaWikiTestCase {
	public function setUp() : void {
		parent::setUp();

		// Test expects empty defaults; otheriwse they will override the
		// parameters passed in by the test.
		UploadWizardConfig::setUrlSetting( 'defaults', [] );

		// insert a interwiki prefixes for testing inter-language links.
		// This is based on ParserTestRunner::setupInterwikis, which does
		// exactly the same (but with more prefixes) for parser tests.
		$this->setTemporaryHook( 'InterwikiLoadPrefix', function ( $prefix, &$iwData ) {
			static $testInterwikis = [
				'es' => [
					'iw_url' => 'http://es.wikipedia.org/wiki/$1',
					'iw_api' => '',
					'iw_wikiid' => '',
					'iw_local' => 1 ],
			];
			if ( array_key_exists( $prefix, $testInterwikis ) ) {
				$iwData = $testInterwikis[$prefix];
			}

			// We only want to rely on the above fixtures
			return false;
		} );
	}

	public function objRefProvider() {
		return [
			[
				'',
				false
			],
			[
				'JustSomeString',
				false
			],
			[
				'notawiki|Page Title',
				false
			],
			[
				'es|Page Title',
				'http://es.wikipedia.org/wiki/Page Title'
			],
			[
				'es|Page Title|id12345',
				'http://es.wikipedia.org/wiki/Page Title'
			],
		];
	}

	/**
	 * @dataProvider objRefProvider
	 */
	public function testButtonTargetIsSetToUseObjRef_buttonHrefMatchesRefPage(
		$objRef, $expectedResult
	) {
		global $wgUploadWizardConfig;

		$this->setMwGlobals( [
			'wgUploadWizardConfig' => array_merge( $wgUploadWizardConfig, [
				'defaults' => [ 'objref' => $objRef ],
			] ),
		] );

		$this->assertEquals(
			$expectedResult,
			$this->getHomeButtonHref()
		);
	}

	private function getHomeButtonHref() {
		$campaign = new UploadWizardCampaign( Title::newFromText( 'uw-test-campaign', NS_CAMPAIGN ),
			[
				'enabled' => true,
				'display' => [
					'homeButton' => [
						'label' => 'Back to that list page',
						'target' => 'useObjref'
					]
				]
			]
		);

		$config = $campaign->getParsedConfig();
		return isset(
			$config['display']['homeButton']['target']
		) ? $config['display']['homeButton']['target'] : false;
	}
}
