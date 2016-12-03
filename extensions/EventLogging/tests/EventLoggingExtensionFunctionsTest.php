<?php
/**
 * PHP Unit tests for top-level ('ef-*') functions in EventLogging
 * extension.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers efSchemaValidate
 */
class EventLoggingExtensionFunctionsTest extends MediaWikiTestCase {

	/** @var array: a basic JSON schema, decoded to associative array. **/
	private static $validSchema = [
		'properties' => [
			'valid' => [
				'type' => 'boolean',
				'required' => true,
			],
			'action' => [
				'type' => 'string',
				'enum' => [
					'delete',
					'edit',
					'history',
					'protect',
					'purge',
					'submit',
					'view',
				],
			],
		]
	];

	/** @var array: conforms to $validSchema. **/
	private static $validObject = [ 'valid' => true, 'action' => 'history' ];

	/** @var array: does not conform to $validSchema. **/
	private static $invalidObject = [ 'valid' => true, 'action' => 'cache' ];

	const UGLY_JSON = '{"nested":{"value":"{}"}}';

	/**
	 * Tests validation of objects against schema.
	 * EventLogging uses Rob Lanphier's JSON Schema Validation Library,
	 * which comes with a set of unit tests for verifying the handling
	 * of various edge cases. Accordingly, this test is designed to
	 * perform only a basic, high-level sanity-check on object and
	 * schema validation.
	 *
	 * @covers efSchemaValidate
	 */
	function testSchemaValidate() {
		$this->assertTrue( efSchemaValidate( self::$validObject, self::$validSchema ),
			'efSchemaValidate() returns true when object validates successfully.' );
		$this->assertTrue( efSchemaValidate( self::$validSchema ),
			'efSchemaValidate() defaults to validating against the schema schema.' );
	}

	/**
	 * Tests invalidation of objects that deviate from schema.
	 * @covers efSchemaValidate
	 */
	function testSchemaInvalidate() {
		$this->setExpectedException( 'JsonSchemaException' );
		efSchemaValidate( self::$invalidObject, self::$validSchema );
	}
}
