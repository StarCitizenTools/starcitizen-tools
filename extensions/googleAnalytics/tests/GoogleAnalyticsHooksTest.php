<?php

/**
 * @covers GoogleAnalyticsHooks
 */
class GoogleAnalyticsHooksTest extends MediaWikiLangTestCase {
	public function setUp() {
		parent::setUp();
		$this->setMwGlobals( 'wgGoogleAnalyticsAccount', '' );
	}
	/**
	 * @param $allowed
	 * @return Skin
	 */
	private function mockSkin( $allowed, $title = 'Main Page' ) {
		$skin = $this->getMockBuilder( 'SkinFallback' )
			->disableOriginalConstructor()
			->setMethods( array( 'getUser', 'getTitle' ) )
			->getMock();
		$user = $this->getMockBuilder( 'User' )
			->disableOriginalConstructor()
			->setMethods( array( 'isAllowed' ) )
			->getMock();

		$user->expects( $this->any() )
			->method( 'isAllowed' )
			->will( $this->returnValue( $allowed ) );
		$skin
			->expects( $this->any() )
			->method( 'getUser' )
			->will( $this->returnValue( $user ) );

		$skin->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( Title::newFromText( $title ) ) );

		return $skin;
	}

	/**
	 * @dataProvider provideUserPermissions
	 */
	public function testUserPermissions( $allowed, $expected ) {
		$text = '';
		GoogleAnalyticsHooks::onSkinAfterBottomScripts( $this->mockSkin( $allowed ), $text );
		$this->assertContains( $expected, $text );
	}

	public static function provideUserPermissions() {
		return array(
			array( false, 'No web analytics configured' ),
			array( true, 'Web analytics code inclusion is disabled for this user' ),
		);
	}

	public function testAccountIdSet() {
		$this->setMwGlobals( 'wgGoogleAnalyticsAccount', 'foobarbaz' );
		$text = '';
		GoogleAnalyticsHooks::onSkinAfterBottomScripts( $this->mockSkin( false ), $text );
		$this->assertContains( 'www.google-analytics.com/analytics.js', $text );
		$this->assertContains( 'foobarbaz', $text );
		$this->setMwGlobals( 'wgGoogleAnalyticsAccount', '' );
		GoogleAnalyticsHooks::onSkinAfterBottomScripts( $this->mockSkin( false ), $text );
		$this->assertContains( 'No web analytics configured', $text );
		$this->setMwGlobals( 'wgGoogleAnalyticsOtherCode', 'analytics.example.com/foo.js' );
		GoogleAnalyticsHooks::onSkinAfterBottomScripts( $this->mockSkin( false ), $text );
		$this->assertContains( 'analytics.example.com/foo.js', $text );
	}

	public function testAnonymizeIp() {
		$this->setMwGlobals( 'wgGoogleAnalyticsAccount', 'foobarbaz' );
		$text = '';
		GoogleAnalyticsHooks::onSkinAfterBottomScripts( $this->mockSkin( false ), $text );
		$this->assertContains( 'anonymizeIp', $text );
		$this->setMwGlobals( 'wgGoogleAnalyticsAnonymizeIP', false );
		$text = '';
		GoogleAnalyticsHooks::onSkinAfterBottomScripts( $this->mockSkin( false ), $text );
		$this->assertNotContains( 'anonymizeIp', $text );
	}

	/**
	 * @dataProvider provideExcludedPages
	 */
	public function testExcludedPages( $type, $conf, $title, $include ) {
		$this->setMwGlobals( $type, array( $conf ) );
		$text = '';
		GoogleAnalyticsHooks::onSkinAfterBottomScripts( $this->mockSkin( false, $title ), $text );
		if ( $include ) {
			$this->assertContains( 'No web analytics configured', $text );
		} else {
			$this->assertContains( 'Web analytics code inclusion is disabled for this page', $text );
		}
	}

	public static function provideExcludedPages() {
		return array(
			array( 'wgGoogleAnalyticsIgnoreSpecials', 'Preferences', 'Special:Preferences', false ),
			array( 'wgGoogleAnalyticsIgnoreSpecials', 'Userlogout', 'Special:Preferences', true ),
			array( 'wgGoogleAnalyticsIgnoreNsIDs', NS_HELP, 'Help:FooBar', false ),
			array( 'wgGoogleAnalyticsIgnoreNsIDs', NS_MAIN, 'Help:FooBar', true ),
			array( 'wgGoogleAnalyticsIgnorePages', 'Help:FooBar', 'Help:FooBar', false ),
			array( 'wgGoogleAnalyticsIgnorePages', 'Help:FooBar', 'Help:FooBarBaz', true ),
		);
	}
}
