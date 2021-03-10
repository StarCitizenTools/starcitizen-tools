<?php
use TextExtracts\ExtractFormatter;

/**
 * @group TextExtracts
 */
class ExtractFormatterTest extends MediaWikiTestCase {
	/**
	 * @dataProvider provideExtracts
	 */
	public function testExtracts( $expected, $wikiText, $plainText ) {
		$title = Title::newFromText( 'Test' );
		$po = new ParserOptions();
		$po->setEditSection( true );
		$parser = new Parser();
		$text = $parser->parse( $wikiText, $title, $po )->getText();
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'textextracts' );
		$fmt = new ExtractFormatter( $text, $plainText, $config );
		$fmt->remove( '.metadata' ); // Will be added via $wgExtractsRemoveClasses on WMF
		$text = trim( $fmt->getText() );
		$this->assertEquals( $expected, $text );
	}

	public function provideExtracts() {
		$dutch = "'''Dutch''' (<span class=\"unicode haudio\" style=\"white-space:nowrap;\"><span class=\"fn\">"
			. "[[File:Loudspeaker.svg|11px|link=File:nl-Nederlands.ogg|About this sound]]&nbsp;[[:Media:nl-Nederlands.ogg|''Nederlands'']]"
			. "</span>&nbsp;<small class=\"metadata audiolinkinfo\" style=\"cursor:help;\">([[Wikipedia:Media help|<span style=\"cursor:help;\">"
			. "help</span>]]·[[:File:nl-Nederlands.ogg|<span style=\"cursor:help;\">info</span>]])</small></span>) is a"
			. " [[West Germanic languages|West Germanic language]] and the native language of most of the population of the [[Netherlands]]";

		return array(
			array(
				"Dutch ( Nederlands ) is a West Germanic language and the native language of most of the population of the Netherlands",
				$dutch,
				true,
			),

			array(
				"<p><span><span lang=\"baz\">qux</span></span>\n</p>",
				'<span class="foo"><span lang="baz">qux</span></span>',
				false,
			),
			array(
				"<p><span><span lang=\"baz\">qux</span></span>\n</p>",
				'<span style="foo: bar;"><span lang="baz">qux</span></span>',
				false,
			),
			array(
				"<p><span><span lang=\"qux\">quux</span></span>\n</p>",
				'<span class="foo"><span style="bar: baz;" lang="qux">quux</span></span>',
				false,
			),
		);
	}

	/**
	 * @dataProvider provideGetFirstSentences
	 * @param $text
	 * @param $sentences
	 * @param $expected
	 */
	public function testGetFirstSentences( $text, $sentences, $expected ) {
		$this->assertEquals( $expected, ExtractFormatter::getFirstSentences( $text, $sentences ) );
	}

	public function provideGetFirstSentences() {
		return array(
			array(
				'Foo is a bar. Such a smart boy. But completely useless.',
				2,
				'Foo is a bar. Such a smart boy.',
			),
			array(
				'Foo is a bar. Such a smart boy. But completely useless.',
				1,
				'Foo is a bar.',
			),
			array(
				'Foo is a bar. Such a smart boy.',
				2,
				'Foo is a bar. Such a smart boy.',
			),
			array(
				'Foo is a bar.',
				1,
				'Foo is a bar.',
			),
			array(
				'Foo is a bar.',
				2,
				'Foo is a bar.',
			),
			array(
				'',
				1,
				'',
			),
			// Exclamation points too!!!
			array(
				'Foo is a bar! Such a smart boy! But completely useless!',
				1,
				'Foo is a bar!',
			),
			// A tricky one
			array(
				"Acid phosphatase (EC 3.1.3.2) is a chemical you don't want to mess with. Polyvinyl acetate, however, is another story.",
				1,
				"Acid phosphatase (EC 3.1.3.2) is a chemical you don't want to mess with.",
			),
			// Bug T118621
			array(
				'Foo was born in 1977. He enjoys listening to Siouxsie and the Banshees.',
				1,
				'Foo was born in 1977.',
			),
			// Bug T115795 - Test no cropping after initials
			array(
				'P.J. Harvey is a singer. She is awesome!',
				1,
				'P.J. Harvey is a singer.',
			),
			// Bug T115817 - Non-breaking space is not a delimiter
			array(
				html_entity_decode( 'Pigeons (lat.&nbsp;Columbidae) are birds. They primarily feed on seeds.' ),
				1,
				html_entity_decode( 'Pigeons (lat.&nbsp;Columbidae) are birds.' ),
			),
		);
	}

	/**
	 * @dataProvider provideGetFirstChars
	 * @param $text
	 * @param $chars
	 * @param $expected
	 */
	public function testGetFirstChars( $text, $chars, $expected ) {
		$this->assertEquals( $expected, ExtractFormatter::getFirstChars( $text, $chars ) );
	}

	public function provideGetFirstChars() {
		$text = 'Lullzy lulz are lullzy!';
		return array(
			//array( $text, 0, '' ),
			array( $text, 100, $text ),
			array( $text, 1, 'Lullzy' ),
			array( $text, 6, 'Lullzy' ),
			//array( $text, 7, 'Lullzy' ),
			array( $text, 8, 'Lullzy lulz' ),
		);
	}
}
