<?php
/**
 * @group Database
 */
class CookieWarningHooksTest extends MediaWikiLangTestCase {
	protected function setUp() {
		parent::setUp();
		MessageCache::singleton()->enable();
	}

	/**
	 * @dataProvider providerOnSkinTemplateOutputPageBeforeExec
	 */
	public function testOnSkinTemplateOutputPageBeforeExec( $enabled, $morelinkConfig,
		$morelinkCookieWarningMsg, $morelinkCookiePolicyMsg, $expectedLink
	) {
		$this->setMwGlobals( [
			'wgCookieWarningEnabled' => $enabled,
			'wgCookieWarningMoreUrl' => $morelinkConfig,
			'wgCookieWarningForCountryCodes' => false,
		] );
		if ( $morelinkCookieWarningMsg ) {
			$title = Title::newFromText( 'cookiewarning-more-link', NS_MEDIAWIKI );
			$wikiPage = WikiPage::factory( $title );
			$wikiPage->doEditContent( new WikitextContent( $morelinkCookieWarningMsg ),
				"CookieWarning test" );
		}
		if ( $morelinkCookiePolicyMsg ) {
			$title = Title::newFromText( 'cookie-policy-link', NS_MEDIAWIKI );
			$wikiPage = WikiPage::factory( $title );
			$wikiPage->doEditContent( new WikitextContent( $morelinkCookiePolicyMsg ),
				"CookieWarning test" );
		}
		$sk = new SkinTemplate();
		$tpl = new CookieWarningTestTemplate();
		CookieWarningHooks::onSkinTemplateOutputPageBeforeExec( $sk, $tpl );
		$headElement = '';
		if ( isset( $tpl->data['headelement'] ) ) {
			$headElement = $tpl->data['headelement'];
		}
		if ( $expectedLink === false ) {
			$expected = '';
		} else {
			// @codingStandardsIgnoreStart Generic.Files.LineLength
			$expected =
				str_replace( '$1', $expectedLink,
					'<div class="mw-cookiewarning-container"><div class="mw-cookiewarning-text"><span>Cookies help us deliver our services. By using our services, you agree to our use of cookies.</span>$1<form method="POST"><input name="disablecookiewarning" class="mw-cookiewarning-dismiss" type="submit" value="OK"/></form></div></div>' );
			// @codingStandardsIgnoreEnd
		}
		$this->assertEquals( $expected, $headElement );
	}

	public function providerOnSkinTemplateOutputPageBeforeExec() {
		return [
			[
				// $wgCookieWarningEnabled
				true,
				// $wgCookieWarningMoreUrl
				'',
				// MediaWiki:Cookiewarning-more-link
				false,
				// MediaWiki:Cookie-policy-link
				false,
				// expected cookie warning link (when string), nothing if false
				'',
			],
			[
				false,
				'',
				false,
				false,
				false,
			],
			[
				true,
				'http://google.de',
				false,
				false,
				'&#160;<a href="http://google.de">More information</a>',
			],
			[
				true,
				'',
				'http://google.de',
				false,
				'&#160;<a href="http://google.de">More information</a>',
			],
			[
				true,
				'',
				false,
				'http://google.de',
				'&#160;<a href="http://google.de">More information</a>',
			],
			// the config should be the used, if set (no matter if the messages are used or not)
			[
				true,
				'http://google.de',
				false,
				'http://google123.de',
				'&#160;<a href="http://google.de">More information</a>',
			],
			[
				true,
				'http://google.de',
				'http://google1234.de',
				'http://google123.de',
				'&#160;<a href="http://google.de">More information</a>',
			],
			[
				true,
				'',
				'http://google.de',
				'http://google123.de',
				'&#160;<a href="http://google.de">More information</a>',
			],
		];
	}

	/**
	 * @dataProvider providerOnSkinTemplateOutputPageBeforeExecGeoLocation
	 */
	public function testOnSkinTemplateOutputPageBeforeExecGeoLocation( $ipAddress, $countryCodes,
		$expected
	) {
		$this->resetCookieWarningHooks();
		$this->setMwGlobals( [
			'wgCookieWarningEnabled' => true,
			'wgCookieWarningGeoIPLookup' => is_array( $countryCodes ) ? 'php' : 'none',
			'wgCookieWarningForCountryCodes' => $countryCodes,
		] );

		$request = new FauxRequest();
		$request->setIP( $ipAddress );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$sk = new SkinTemplate();
		$sk->setContext( $context );
		$tpl = new CookieWarningTestTemplate();
		CookieWarningHooks::onSkinTemplateOutputPageBeforeExec( $sk, $tpl );

		$this->assertEquals(
			$expected,
			isset( $tpl->data['headelement'] ) && (bool)$tpl->data['headelement']
		);
	}

	public function providerOnSkinTemplateOutputPageBeforeExecGeoLocation() {
		return [
			[
				'8.8.8.8',
				[ 'US' => 'United States of America' ],
				true,
			],
			[
				'8.8.8.8',
				[ 'EU' => 'European Union' ],
				false,
			],
			[
				'8.8.8.8',
				false,
				true,
			],
		];
	}

	private function resetCookieWarningHooks() {
		// reset the inConfiguredRegion value to retrigger a location lookup, if called again
		$singleton = CookieWarningHooks::class;
		$reflection = new ReflectionClass( $singleton );
		$instance = $reflection->getProperty( 'inConfiguredRegion' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$instance->setAccessible( false );
	}
}

class CookieWarningTestTemplate extends BaseTemplate {
	public function execute() {
		return;
	}
}
