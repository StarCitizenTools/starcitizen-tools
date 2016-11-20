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

/**
 * @covers Babel
 *
 * @group Babel
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class BabelTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgContLang' => Language::factory( 'qqx' ),
		] );
		$this->insertPage( 'User:User-1', '[[Category:en]]' );
	}

	/**
	 * @return Parser
	 */
	private function getParser() {
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
			->will( $this->returnValue( Title::newFromText( 'User:User-1' ) ) );

		$parser->expects( $this->any() )
			->method( 'getOutput' )
			->will( $this->returnValue( new ParserOutput() ) );

		return $parser;
	}

	/**
	 * @param int $expectedCount
	 * @param string $haystack
	 */
	private function assertBabelBoxCount( $expectedCount, $haystack ) {
		$this->assertSame( $expectedCount, substr_count( $haystack, '<div class="mw-babel-box' ) );
	}

	public function testRenderEmptyBox() {
		$wikiText = Babel::Render( $this->getParser(), '' );
		$this->assertSame(
			'{|style=" padding: (babel-box-cellpadding);  border-spacing: (babel-box-cellspacing);"'
			. ' class="mw-babel-wrapper"'
			. "\n"
			. '! class="mw-babel-header" | [[(babel-url)|(babel: User-1)]]'
			. "\n|-\n| \n|-\n"
			.  '! class="mw-babel-footer" | [[(babel-footer-url)|(babel-footer: User-1)]]'
			. "\n|}",
			$wikiText
		);
	}

	public function testRenderDefaultLevel() {
		$wikiText = Babel::Render( $this->getParser(), 'en' );
		$this->assertBabelBoxCount( 1, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-box mw-babel-box-N" dir="ltr">'
			. "\n"
			. '{|style=" padding: (babel-cellpadding);  border-spacing: (babel-cellspacing);"'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: en)|en]]<span class="mw-babel-box-level-N">-N</span>'
			. "\n"
			. '| dir="ltr" lang="en" | This user has a [[:Category:en-N|native]] understanding of '
			. '[[:Category:en|English]].'
			. "\n|}\n"
			. '</div>[[Category:en|N]][[Category:en-N]]',
			$wikiText
		);
	}

	public function testRenderCustomLevel() {
		$wikiText = Babel::Render( $this->getParser(), 'EN-1', 'zh-Hant' );
		$this->assertBabelBoxCount( 2, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-box mw-babel-box-1" dir="ltr">'
			. "\n"
			. '{|style=" padding: (babel-cellpadding);  border-spacing: (babel-cellspacing);"'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: en)|en]]<span class="mw-babel-box-level-1">-1</span>'
			. "\n"
			. '| dir="ltr" lang="en" | This user has [[:Category:en-1|basic]] knowledge of '
			. '[[:Category:en|English]].'
			. "\n|}\n"
			. '</div>[[Category:en|1]][[Category:en-1]]',
			$wikiText
		);
		$this->assertContains(
			'<div class="mw-babel-box mw-babel-box-N" dir="ltr">'
			. "\n"
			. '{|style=" padding: (babel-cellpadding);  border-spacing: (babel-cellspacing);"'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: zh-Hant)|zh-Hant]]'
			. '<span class="mw-babel-box-level-N">-N</span>'
			. "\n"
			. '| dir="ltr" lang="zh-Hant" | This user has a [[:Category:zh-Hant-N|native]] '
			. 'understanding of [[:Category:zh-Hant|]].'
			. "\n|}\n"
			. '</div>[[Category:zh-Hant|N]][[Category:zh-Hant-N]]',
			$wikiText
		);
	}

	public function testRenderPlain() {
		$wikiText = Babel::Render( $this->getParser(), 'plain=1', 'en' );
		$this->assertSame(
			'<div class="mw-babel-box mw-babel-box-N" dir="ltr">'
			. "\n"
			. '{|style=" padding: (babel-cellpadding);  border-spacing: (babel-cellspacing);"'
			. "\n"
			. '! dir="ltr" | [[(babel-portal: en)|en]]<span class="mw-babel-box-level-N">-N</span>'
			. "\n"
			. '| dir="ltr" lang="en" | This user has a [[:Category:en-N|native]] understanding of '
			. '[[:Category:en|English]].'
			. "\n|}\n"
			. '</div>[[Category:en|N]][[Category:en-N]]',
			$wikiText
		);
	}

	public function testRenderRedLink() {
		$wikiText = Babel::Render( $this->getParser(), 'redLink' );
		$this->assertBabelBoxCount( 0, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-notabox" dir="ltr">[[(babel-template: redLink)]]</div>',
			$wikiText
		);
	}

	public function testRenderInvalidTitle() {
		$wikiText = Babel::Render( $this->getParser(), '<invalidTitle>' );
		$this->assertBabelBoxCount( 0, $wikiText );
		$this->assertContains(
			'<div class="mw-babel-notabox" dir="ltr">(babel-template: <invalidTitle>)</div>',
			$wikiText
		);
	}

	public function testGetUserLanguages() {
		$user = User::newFromName( 'User-1' );
		$languages = Babel::getUserLanguages( $user );
		$this->assertSame( [
			'en',
		], $languages );
	}

}
