<?php

namespace Octfx\WikiSEO\Tests;

use MediaWikiTestCase;
use Octfx\WikiSEO\TagParser;

class TagParserTest extends MediaWikiTestCase {
	/**
	 * @var TagParser
	 */
	private $tagParser;

	protected function setUp() {
		parent::setUp();
		$this->tagParser = new TagParser();
	}

	protected function tearDown() {
		unset( $this->tagParser );
		parent::tearDown();
	}

	/**
	 * @covers \Octfx\WikiSEO\TagParser::parseArgs
	 */
	public function testParseArgs() {
		$args = [
			'title=Test Title',
			'=',
			'keywords=a,b ,  c , d',
			'=emptyKey',
			'emptyContent='
		];

		$parsedArgs = $this->tagParser->parseArgs( $args );

		$this->assertCount( 2, $parsedArgs );
		$this->assertArrayHasKey( 'title', $parsedArgs );
		$this->assertArrayNotHasKey( 'emptyContent', $parsedArgs );
	}

	/**
	 * @covers \Octfx\WikiSEO\TagParser::parseArgs
	 */
	public function testParseArgsMultipleEquals() {
		$args = [
			'description=First Equal separates = Second Equal is included',
			'====',
			'==emptyKey',
		];

		$parsedArgs = $this->tagParser->parseArgs( $args );

		$this->assertCount( 1, $parsedArgs );
		$this->assertArrayHasKey( 'description', $parsedArgs );
		$this->assertEquals( 'First Equal separates = Second Equal is included', $parsedArgs['description'] );
	}

	/**
	 * @covers \Octfx\WikiSEO\TagParser::parseText
	 */
	public function testParseText() {
		$text = <<<EOL
|title= Test Title
|keywords=A,B,C,D
|description=
|=emptyKey
|emptyContent=
EOL;

		$parsedArgs = $this->tagParser->parseText( $text );

		$this->assertCount( 2, $parsedArgs );
		$this->assertArrayHasKey( 'title', $parsedArgs );
		$this->assertArrayNotHasKey( 'emptyContent', $parsedArgs );
	}

	/**
	 * @covers \Octfx\WikiSEO\TagParser::extractSeoDataFromHtml
	 */
	public function testExtractSeoDataFromHtml() {
		$text = <<<EOL
<html>
<!-- Fake HTML Document -->
<head>
<title>Test Page</title>
</head>
<body>
<p>Lorem Ipsum Dolor Sit Amet</p>
<p><!--wiki-seo-data-start
WikiSEO:title_mode;cmVwbGFjZQ==
WikiSEO:title;VGl0bGUgZnJvbSBXaWtpU0VPIEV4dGVuc2lvbg==
WikiSEO:keywords;S2V5d29yZCAxLCBLZXl3b3JkIDIsIEtleXdvcmQgMw==
WikiSEO:locale;ZGVfREU=
wiki-seo-data-end--></p>
</body>
</html>
EOL;

		$expectedKeys = [
			'title_mode',
			'title',
			'keywords',
			'locale',
		];

		$parsedArgs = TagParser::extractSeoDataFromHtml( $text );

		$this->assertCount( 4, $parsedArgs );
		$this->assertArrayEquals( $expectedKeys, array_keys( $parsedArgs ) );
	}
}