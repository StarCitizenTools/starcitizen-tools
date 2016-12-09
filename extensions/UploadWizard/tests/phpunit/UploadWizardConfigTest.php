<?php

/**
 * Test the Upload Wizard Configuration
 * @group Upload
 */

class UploadWizardConfigTest extends MediaWikiTestCase {

	public function objRefProvider() {
		return array(
			array(
				'',
				false
			),
			array(
				'JustSomeString',
				false
			),
			array(
				'notawiki|Page Title',
				false
			),
			array(
				'es|Page Title',
				'http://es.wikipedia.org/wiki/Page Title'
			),
			array(
				'es|Page Title|id12345',
				'http://es.wikipedia.org/wiki/Page Title'
			),
		);
	}

	/**
	 * @dataProvider objRefProvider
	 */
	public function testButtonTargetIsSetToUseObjRef_buttonHrefMatchesRefPage(
		$objRef, $expectedResult
	) {
		global $wgUploadWizardConfig;
		ParserTest::setupInterwikis();

		$this->setMwGlobals( array(
			'wgUploadWizardConfig' => array_merge( $wgUploadWizardConfig, array(
				'defaults' => array( 'objref' => $objRef ),
			) ),
		) );

		$this->assertEquals(
			$expectedResult,
			$this->getHomeButtonHref()
		);
	}

	private function getHomeButtonHref() {
		$campaign = new UploadWizardCampaign( Title::newFromText( 'uw-test-campaign', NS_CAMPAIGN ),
			array(
				'enabled' => true,
				'display' => array(
					'homeButton' => array(
						'label' => 'Back to that list page',
						'target' => 'useObjref'
					)
				)
			)
		);

		$config = $campaign->getParsedConfig();
		return isset(
			$config['display']['homeButton']['target']
		) ? $config['display']['homeButton']['target'] : false;
	}
}
