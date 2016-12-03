<?php
/**
 * PHP Unit tests for event schema validation
 *
 * @file
 * @ingroup Extensions
 *
 * @author Nuria Ruiz <nuria@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers JsonSchema
 */
class ValidateSchemaTest extends MediaWikiTestCase {

	const VALID_JSON_SCHEMA = '{
		"description": "A wrapper around event objects that encodes generic metadata",
		"properties": {
			"event": {
				"type": "object",
				"description": "The encapsulated event object",
				"required": true
			},
			"other": {
				"type": "string",
				"description": "Some fake stuff",
				"required": true
			},
			"userAgent": {
				"type": "string",
				"description": "Some fake User Agent",
				"required": false
			},
			"clientIp":{
				"type": "string",
				"description": "Some fake",
				"required": false
			},
			"clientValidated":{
				"type": "boolean",
				"description": "Some fake",
				"required": false
			}
		},
		"additionalProperties": false
	}';

	const VALID_EVENT = '{
		"clientIp": "e6636d0087dde9cc49142955607c17e0b5d3563a",
		"clientValidated": true,
		"event": {
			"action": "view",
			 "connectEnd": 393,
			 "connectStart": 393
		},
		"userAgent": "some",
		"other": "some"
	}';

	const INVALID_EVENT_MISSING_REQUIRED_FIELD = '{
		"clientIp": "e6636d0087dde9cc49142955607c17e0b5d3563a",
		"clientValidated": true,
		"event": {
			"action": "view",
			"connectEnd": 393,
			"connectStart": 393
		}
	}';

	const VALID_JSON_SCHEMA_MANDATORY_EVENT_PROPERTIES = '{
		"description": "The event object",
		"properties":{
			"Happy":{
				"type": "string",
				"description": "blah",
				"required": true
			}
		}
	}';

	const VALID_JSON_SCHEMA_NON_MANDATORY_EVENT_PROPERTIES = '{
		"description": "The event object",
		"required": true,
		"properties":{
			"Happy":{
				"type": "string",
				"description": "blah"
			}
		}
	}';

	/**
	 * Tests schema we are using for tests is, indeed, valid
	 * @covers JsonSchemaContent::isValid
	 */
	function testValidJson() {
		$content = new JsonSchemaContent( self::VALID_JSON_SCHEMA );
		$this->assertTrue( $content->isValid(), 'Well-formed JSON schema' );
		$content = new JsonSchemaContent(
			self::VALID_JSON_SCHEMA_MANDATORY_EVENT_PROPERTIES );
		$this->assertTrue( $content->isValid(),
			'Well-formed JSON schema MANDATORY_EVENT_PROPERTIES' );
		$content = new JsonSchemaContent(
				self::VALID_JSON_SCHEMA_NON_MANDATORY_EVENT_PROPERTIES );
		$this->assertTrue( $content->isValid(),
				'Well-formed JSON schema NON_MANDATORY_EVENT_PROPERTIES' );
	}

	/**
	* A valid event should, ahem, validate
	* @covers efSchemaValidate
	**/
	function testValidEvent() {
		$valid = efSchemaValidate(
			json_decode( self::VALID_EVENT, true ),
			json_decode( self::VALID_JSON_SCHEMA, true )
		);
		$this->assertTrue( $valid, 'Well-formed event should validate' );
	}

	/**
	*
	* @covers efSchemaValidate
	* @expectedException JsonSchemaException
	**/
	function testInvalidEvent() {
		$valid = efSchemaValidate(
			json_decode( self::INVALID_EVENT_MISSING_REQUIRED_FIELD, true ),
			json_decode( self::VALID_JSON_SCHEMA, true )
		);
		$this->assertFalse( $valid, 'Malformed event should not validate' );
	}

	/**
	* Event with non mandatory properties validates
	* @covers efSchemaValidate
	**/
	function testEventNonMandatoryProperties() {
		$valid = efSchemaValidate(
			json_decode( '{"Happy": "true"}', true ),
			json_decode( self::VALID_JSON_SCHEMA_NON_MANDATORY_EVENT_PROPERTIES, true )
		);
		$this->assertTrue( $valid, 'Event with non mandatory properties validates' );
	}
	/**
	* An empty event should validate if event does not have
	* mandatory properties
	* @covers efSchemaValidate
	**/
	function testEmptyEventForSchemaWithOptionalOnlyPropertiesIsValid() {
		$valid = efSchemaValidate(
			json_decode( '{}', true ),
			json_decode(
				self::VALID_JSON_SCHEMA_NON_MANDATORY_EVENT_PROPERTIES, true )
		);
		$this->assertTrue( $valid, 'Empty event should validate if event has only
			optional properties' );

		# now test event serialized to []
		$valid = efSchemaValidate(
			json_decode( '[]', true ),
			json_decode( self::VALID_JSON_SCHEMA_NON_MANDATORY_EVENT_PROPERTIES, true )
		);
		$this->assertTrue( $valid, 'Empty event like [] should validate if event has only
			optional properties' );
	}
}
