<?php

namespace MediaWiki\Extension\WikiSEO\Tests;

use MediaWiki\Extension\WikiSEO\TagParser;
use MediaWikiTestCase;

class TagParserTest extends MediaWikiTestCase {
	/**
	 * @var TagParser
	 */
	private $tagParser;

	protected function setUp(): void {
		parent::setUp();
		$this->tagParser = new TagParser();
	}

	protected function tearDown(): void {
		unset( $this->tagParser );
		parent::tearDown();
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\TagParser::parseArgs
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
	 * @covers \MediaWiki\Extension\WikiSEO\TagParser::parseArgs
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
		$this->assertEquals(
			'First Equal separates = Second Equal is included',
			$parsedArgs['description']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\TagParser::parseText
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
}
