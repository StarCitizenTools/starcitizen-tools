<?php

namespace Babel\Tests;

use Babel;
use Language;
use MediaWikiTestCase;
use Parser;
use ParserOptions;
use ParserOutput;
use Title;
use User;
use WikiPage;

/**
 * @covers Babel
 *
 * @group Babel
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class BabelTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgContLang' => Language::factory( 'qqx' ),
			// Note that individual tests will change this
			'wgBabelUseDatabase' => true,
			'wgBabelCentralApi' => false,
			'wgBabelCentralDb' => false,
			'wgCapitalLinks' => false,
		] );
		$user = User::newFromName( 'User-1' );
		$user->addToDatabase();
		$title = $user->getUserPage();
		$this->insertPage( $title->getPrefixedText(), '{{#babel:en-1|es-2|de}}' );
		// Test on a category page too (
		$this->insertPage( Title::newFromText( 'Category:X1', '{{#babel:en-1|es-2|de}}' ) );
		$page = WikiPage::factory( $title );
		// Force a run of LinksUpdate
		$updates = $page->getContent()->getSecondaryDataUpdates( $title );
		foreach ( $updates as $update ) {
			$update->doUpdate();
		}
	}

	/**
	 * @param Title $title
	 * @return Parser
	 */
	private function getParser( Title $title ) {
		$options = new ParserOptions();
		$options->setIsPreview( true );

		$parser = $this->getMockBuilder( 'Parser' )
			->disableOriginalConstructor()
			->getMock();

		$parser->expects( $this->any() )
			->method( 'getOptions' )
			->will( $this->returnValue( $options ) );

		$parser->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $title ) );

		$parser->expects( $this->any() )
			->method( 'getOutput' )
			->will( $this->returnValue( new ParserOutput() ) );

		$parser->expects( $this->any() )
			->method( 'getDefaultSort' )
			->will( $this->returnValue( '' ) );

		return $parser;
	}

	/**
	 * @param int $expectedCount
	 * @param string $haystack
	 */
	private function assertBabelBoxCount( $expectedCount, $haystack ) {
		$this->assertSame( $expectedCount, substr_count( $haystack, '<div class="mw-babel-box' ) );
	}

	/**
	 * @param Parser $parser
	 * @param string $cat
	 * @param string $sortKey
	 */
	private function assertHasCategory( $parser, $cat, $sortKey ) {
		$cats = $parser->getOutput()->getCategories();
		$this->assertArrayHasKey( $cat, $cats );
		$this->assertSame( $sortKey, $cats[$cat] );
	}

	/**
	 * @param Parser $parser
	 * @param string $cat
	 */
	private function assertNotHasCategory( $parser, $cat ) {
		$cats = $parser->getOutput()->getCategories();
		$this->assertArrayNotHasKey( $cat, $cats );
	}

	public function testRenderEmptyBox() {
		$title = Title::newFromText( 'User:User-1' );
		$parser = $this->getParser( $title );
		$wikiText = Babel::Render( $parser, '' );
		$this->assertSame(
			'{|class="mw-babel-wrapper"'
			. "\n"
			. '! class="mw-babel-header" | [[(babel-url)|(babel: User-1)]]'
			. "\n|-\n| \n|-\n"
			. '! class="mw-babel-footer" | [[(babel-footer-url)|(babel-footer: User-1)]]'
			. "\n|}",
			$wikiText
		);
	}

	/**
	 * Provides different page names, such as pages in the Category namespace.
	 */
	public static function providePageNames() {
		return [
			[ 'User:User-1' ],
			[ 'Category:X1' ],
		];
	}

	/**
	 * @dataProvider providePageNames
	 */
	public function testRenderDefaultLevel( $pageName ) {
		$parser = $this->getParser( Title::newFromText( $pageName ) );
		$wikiText = Babel::Render( $parser, 'en' );
		$this->assertBabelBoxCount( 1, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-box mw-babel-box-N" dir="ltr">'
			. "\n"
			. '{|'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: en)|en]]<span class="mw-babel-box-level-N">-N</span>'
			. "\n"
			. '| dir="ltr" lang="en" | This user has a [[:Category:en-N|native]] understanding of '
			. '[[:Category:en|English]].'
			. "\n|}\n"
			. '</div>',
			$wikiText
		);

		$this->assertHasCategory( $parser, 'en', 'N' );
		$this->assertHasCategory( $parser, 'en-N', '' );
	}

	/**
	 * @dataProvider providePageNames
	 */
	public function testRenderDefaultLevelNoCategory( $pageName ) {
		$this->setMwGlobals( [ 'wgBabelMainCategory' => false ] );

		$parser = $this->getParser( Title::newFromText( $pageName ) );
		$wikiText = Babel::Render( $parser, 'en' );
		$this->assertBabelBoxCount( 1, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-box mw-babel-box-N" dir="ltr">'
			. "\n"
			. '{|'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: en)|en]]<span class="mw-babel-box-level-N">-N</span>'
			. "\n"
			. '| dir="ltr" lang="en" | This user has a [[:Category:en-N|native]] understanding of '
			. "[[:$pageName|English]]."
			. "\n|}\n"
			. '</div>',
			$wikiText
		);

		$this->assertNotHasCategory( $parser, 'en' );
		$this->assertHasCategory( $parser, 'en-N', '' );
	}

	public function testRenderCustomLevel() {
		$parser = $this->getParser( Title::newFromText( 'User:User-1' ) );
		$wikiText = Babel::Render( $parser, 'EN-1', 'zh-Hant' );
		$this->assertBabelBoxCount( 2, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-box mw-babel-box-1" dir="ltr">'
			. "\n"
			. '{|'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: en)|en]]<span class="mw-babel-box-level-1">-1</span>'
			. "\n"
			. '| dir="ltr" lang="en" | This user has [[:Category:en-1|basic]] knowledge of '
			. '[[:Category:en|English]].'
			. "\n|}\n"
			. '</div>',
			$wikiText
		);

		$this->assertHasCategory( $parser, 'en', '1' );
		$this->assertHasCategory( $parser, 'en-1', '' );

		$this->assertContains(
			'<div class="mw-babel-box mw-babel-box-N" dir="ltr">'
			. "\n"
			. '{|'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: zh-Hant)|zh-Hant]]'
			. '<span class="mw-babel-box-level-N">-N</span>'
			. "\n"
			. '| dir="ltr" lang="zh-Hant" | 這位使用者會[[:Category:zh-Hant-N|母語]]水準的 '
			. '[[:Category:zh-Hant|繁體中文]]。'
			. "\n|}\n"
			. '</div>',
			$wikiText
		);

		$this->assertHasCategory( $parser, 'zh-Hant', 'N' );
		$this->assertHasCategory( $parser, 'zh-Hant-N', '' );
	}

	public function testRenderPlain() {
		$parser = $this->getParser( Title::newFromText( 'User:User-1' ) );
		$wikiText = Babel::Render( $parser, 'plain=1', 'en' );
		$this->assertSame(
			'<div class="mw-babel-box mw-babel-box-N" dir="ltr">'
			. "\n"
			. '{|'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: en)|en]]<span class="mw-babel-box-level-N">-N</span>'
			. "\n"
			. '| dir="ltr" lang="en" | This user has a [[:Category:en-N|native]] understanding of '
			. '[[:Category:en|English]].'
			. "\n|}\n"
			. '</div>',
			$wikiText
		);

		$this->assertHasCategory( $parser, 'en', 'N' );
		$this->assertHasCategory( $parser, 'en-N', '' );
	}

	public function testRenderRedLink() {
		$parser = $this->getParser( Title::newFromText( 'User:User-1' ) );
		$wikiText = Babel::Render( $parser, 'redLink' );
		$this->assertBabelBoxCount( 0, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-notabox" dir="ltr">[[(babel-template: redLink)]]</div>',
			$wikiText
		);
	}

	public function testRenderInvalidTitle() {
		$parser = $this->getParser( Title::newFromText( 'User:User-1' ) );
		$wikiText = Babel::Render( $parser, '<invalidTitle>' );
		$this->assertBabelBoxCount( 0, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-notabox" dir="ltr">(babel-template: <invalidTitle>)</div>',
			$wikiText
		);
	}

	public function testRenderNoSkillNoCategory() {
		$parser = $this->getParser( Title::newFromText( 'User:User-1' ) );
		$wikiText = Babel::Render( $parser, 'en-0' );
		$this->assertNotHasCategory( $parser, 'en' );
	}

	/**
	 * Data provider to run a test with both db enabled and disabled
	 */
	public static function provideSettings() {
		return [
			[ [ 'wgBabelUseDatabase' => true ] ],
			[ [ 'wgBabelUseDatabase' => false ] ],
		];
	}

	/**
	 * @dataProvider provideSettings
	 */
	public function testGetUserLanguages( $settings ) {
		$this->setMwGlobals( $settings );
		$user = User::newFromName( 'User-1' );
		$this->assertSame( [
			'de',
			'en',
			'es',
		], Babel::getUserLanguages( $user ) );

		// Filter based on level
		$this->assertSame( [
			'de',
			'es',
		], Babel::getUserLanguages( $user, '2' ) );

		$this->assertSame( [
			'de',
		], Babel::getUserLanguages( $user, '3' ) );

		// Non-numerical level
		$this->assertSame( [
			'de',
		], Babel::getUserLanguages( $user, 'N' ) );
	}

	public function testGetUserLanguageInfo() {
		$user = User::newFromName( 'User-1' );
		$languages = Babel::getUserLanguageInfo( $user );
		$this->assertSame( [
			'de' => 'N',
			'en' => '1',
			'es' => '2',
		], $languages );
	}
}
