<?php
/**
 * PHP Unit tests for JsonSchemaContent.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers JsonSchema
 */
class JsonSchemaTest extends MediaWikiTestCase {

	const INVALID_JSON = '"Malformed, JSON }';
	const INVALID_JSON_SCHEMA = '{"malformed":true}';  // Valid JSON, invalid JSON Schema.
	const VALID_JSON_SCHEMA = '{"properties":{"valid":{"type":"boolean","required":true}}}';
	const EVIL_JSON = '{"title":"<script>alert(document.cookie);</script>"}';

	/**
	 * Tests handling of invalid JSON.
	 * @covers JsonSchemaContent::isValid
	 */
	function testInvalidJson() {
		$content = new JsonSchemaContent( self::INVALID_JSON );
		$this->assertFalse( $content->isValid(), 'Malformed JSON should be detected.' );
	}

	/**
	 * Tests handling of valid JSON that is not valid JSON Schema.
	 * @covers JsonSchemaContent::isValid
	 */
	function testInvalidJsonSchema() {
		$content = new JsonSchemaContent( self::INVALID_JSON_SCHEMA );
		$this->assertFalse( $content->isValid(), 'Malformed JSON Schema should be detected.' );
	}

	/**
	 * Tests successful validation of well-formed JSON Schema.
	 * @covers JsonSchemaContent::isValid
	 */
	function testValidJsonSchema() {
		$content = new JsonSchemaContent( self::VALID_JSON_SCHEMA );
		$this->assertTrue( $content->isValid(), 'Valid JSON Schema should be recognized as valid.' );
	}

	/**
	 * Tests JSON pretty-printing.
	 * @covers JsonSchemaContent::preSaveTransform
	 */
	function testPreSaveTransform() {
		$transformed = new JsonSchemaContent( self::VALID_JSON_SCHEMA );
		$prettyJson = $transformed->preSaveTransform(
			new Title(), new User(), new ParserOptions() )->getNativeData();

		$this->assertContains( "\n", $prettyJson, 'Transformed JSON is beautified.' );
		$this->assertEquals(
			FormatJson::decode( $prettyJson ),
			FormatJson::decode( self::VALID_JSON_SCHEMA ),
			'Beautification does not alter JSON value.'
		);
	}

	/**
	 * Tests JSON->HTML representation.
	 * @covers JsonSchemaContent::getHighlightHtml
	 */
	function testGetHighlightHtml() {
		$content = new JsonSchemaContent( self::EVIL_JSON );
		$out = $content->getParserOutput(
			Title::newFromText( 'Test' ),
			null,
			null,
			/* html */ true
		);
		$this->assertContains( '&lt;script>', $out->getText(), 'HTML output should be escaped' );
	}
}
