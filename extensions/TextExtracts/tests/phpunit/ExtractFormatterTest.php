<?php

namespace TextExtracts\Test;

use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use ParserOptions;
use TextExtracts\ExtractFormatter;

/**
 * @covers \TextExtracts\ExtractFormatter
 * @group TextExtracts
 */
class ExtractFormatterTest extends MediaWikiTestCase {
	/**
	 * @dataProvider provideExtracts
	 */
	public function testExtracts( $expected, $text, $plainText ) {
		$po = new ParserOptions();
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'textextracts' );
		$fmt = new ExtractFormatter( $text, $plainText, $config );
		// .metadata class will be added via $wgExtractsRemoveClasses on WMF
		$fmt->remove( '.metadata' );
		$text = trim( $fmt->getText() );
		$this->assertEquals( $expected, $text );
	}

	public function provideExtracts() {
		// @codingStandardsIgnoreStart
		$dutch = '<b>Dutch</b> (<span class="unicode haudio" style="white-space:nowrap;"><span class="fn"><a href="/wiki/File:Nl-Nederlands.ogg" title="About this sound"><img alt="About this sound" src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Loudspeaker.svg/11px-Loudspeaker.svg.png" width="11" height="11" srcset="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Loudspeaker.svg/17px-Loudspeaker.svg.png 1.5x, https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Loudspeaker.svg/22px-Loudspeaker.svg.png 2x" /></a>&#160;<a href="https://upload.wikimedia.org/wikipedia/commons/d/db/Nl-Nederlands.ogg" class="internal" title="Nl-Nederlands.ogg"><i>Nederlands</i></a></span>&#160;<small class="metadata audiolinkinfo" style="cursor:help;">(<a href="/w/index.php?title=Wikipedia:Media_help&amp;action=edit&amp;redlink=1" class="new" title="Wikipedia:Media help (page does not exist)"><span style="cursor:help;">help</span></a>·<a href="/wiki/File:Nl-Nederlands.ogg" title="File:Nl-Nederlands.ogg"><span style="cursor:help;">info</span></a>)</small></span>) is a <a href="/w/index.php?title=West_Germanic_languages&amp;action=edit&amp;redlink=1" class="new" title="West Germanic languages (page does not exist)">West Germanic language</a> and the native language of most of the population of the <a href="/w/index.php?title=Netherlands&amp;action=edit&amp;redlink=1" class="new" title="Netherlands (page does not exist)">Netherlands</a>';
		$tocText = 'Lead<div id="toc" class="toc">TOC goes here</div>
<h1>Section</h1>
<p>Section text</p>';
		// @codingStandardsIgnoreEnd

		return [
			[
				'Dutch ( Nederlands ) is a West Germanic language and the native language of ' .
					'most of the population of the Netherlands',
				$dutch,
				true,
			],

			[
				"<span><span lang=\"baz\">qux</span></span>",
				'<span class="foo"><span lang="baz">qux</span></span>',
				false,
			],
			[
				"<span><span lang=\"baz\">qux</span></span>",
				'<span style="foo: bar;"><span lang="baz">qux</span></span>',
				false,
			],
			[
				"<span><span lang=\"qux\">quux</span></span>",
				'<span class="foo"><span style="bar: baz;" lang="qux">quux</span></span>',
				false,
			],
			[
				// Verify that TOC is properly removed (HTML mode)
				"Lead\n<h1>Section</h1>\n<p>Section text</p>",
				$tocText,
				false,
			],
			[
				// Verify that TOC is properly removed (plain text mode)
				"Lead\n\n\x01\x021\2\1Section\nSection text",
				$tocText,
				true,
			],
		];
	}

	/**
	 * @dataProvider provideGetFirstSentences
	 * @param string $text
	 * @param string $sentences
	 * @param string $expected
	 */
	public function testGetFirstSentences( $text, $sentences, $expected ) {
		$this->assertEquals( $expected, ExtractFormatter::getFirstSentences( $text, $sentences ) );
	}

	public function provideGetFirstSentences() {
		$longLine = str_repeat( 'word ', 1000000 );
		return [
			[
				'Foo is a bar. Such a smart boy. But completely useless.',
				2,
				'Foo is a bar. Such a smart boy.',
			],
			[
				'Foo is a bar. Such a smart boy. But completely useless.',
				1,
				'Foo is a bar.',
			],
			[
				'Foo is a bar. Such a smart boy.',
				2,
				'Foo is a bar. Such a smart boy.',
			],
			[
				'Foo is a bar.',
				1,
				'Foo is a bar.',
			],
			[
				'Foo is a bar.',
				2,
				'Foo is a bar.',
			],
			[
				'',
				1,
				'',
			],
			// Exclamation points too!!!
			[
				'Foo is a bar! Such a smart boy! But completely useless!',
				1,
				'Foo is a bar!',
			],
			// A tricky one
			[
				"Acid phosphatase (EC 3.1.3.2) is a chemical you don't want to mess with. " .
					"Polyvinyl acetate, however, is another story.",
				1,
				"Acid phosphatase (EC 3.1.3.2) is a chemical you don't want to mess with.",
			],
			// No clear sentences
			[
				"foo\nbar\nbaz",
				2,
				'foo',
			],
			// Bug T118621
			[
				'Foo was born in 1977. He enjoys listening to Siouxsie and the Banshees.',
				1,
				'Foo was born in 1977.',
			],
			// Bug T115795 - Test no cropping after initials
			[
				'P.J. Harvey is a singer. She is awesome!',
				1,
				'P.J. Harvey is a singer.',
			],
			// Bug T115817 - Non-breaking space is not a delimiter
			[
				html_entity_decode( 'Pigeons (lat.&nbsp;Columbidae) are birds. ' .
					'They primarily feed on seeds.' ),
				1,
				html_entity_decode( 'Pigeons (lat.&nbsp;Columbidae) are birds.' ),
			],
			// Bug T145231 - various problems with regexes
			[
				$longLine,
				3,
				trim( $longLine ),
			],
			[
				str_repeat( 'Sentence. ', 70000 ),
				65536,
				trim( str_repeat( 'Sentence. ', 65536 ) ),
			],
		];
	}

	/**
	 * @dataProvider provideGetFirstChars
	 * @param string $text
	 * @param string $chars
	 * @param string $expected
	 */
	public function testGetFirstChars( $text, $chars, $expected ) {
		$this->assertEquals( $expected, ExtractFormatter::getFirstChars( $text, $chars ) );
	}

	public function provideGetFirstChars() {
		$text = 'Lullzy lulz are lullzy!';
		$html = 'foo<tag>bar</tag>';
		$longText = str_repeat( 'тест ', 50000 );
		$longTextExpected = trim( str_repeat( 'тест ', 13108 ) );

		return [
			[ $text, -8, '' ],
			[ $text, 0, '' ],
			[ $text, 100, $text ],
			[ $text, 1, 'Lullzy' ],
			[ $text, 6, 'Lullzy' ],
			// [ $text, 7, 'Lullzy' ],
			[ $text, 8, 'Lullzy lulz' ],
			// HTML processing
			[ $html, 1, 'foo' ],
			// let HTML sanitizer clean it up later
			[ $html, 4, 'foo<tag>' ],
			[ $html, 12, 'foo<tag>bar</tag>' ],
			[ $html, 13, 'foo<tag>bar</tag>' ],
			[ $html, 16, 'foo<tag>bar</tag>' ],
			[ $html, 17, 'foo<tag>bar</tag>' ],
			// T143178 - previously, characters were extracted using regexps which failed when
			// requesting 64K chars or more.
			[ $longText, 65536, $longTextExpected ],
		];
	}
}
