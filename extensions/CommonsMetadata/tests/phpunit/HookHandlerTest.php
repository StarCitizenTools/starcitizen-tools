<?php

namespace CommonsMetadata;

require_once __DIR__ . "/ParserTestHelper.php";

/**
 * @covers CommonsMetadata\HookHandler
 * @group Extensions/CommonsMetadata
 */
class HookHandlerTest extends \PHPUnit_Framework_TestCase {
	/** @var ParserTestHelper */
	protected $parserTestHelper;

	public function setUp() {
		$this->parserTestHelper = new ParserTestHelper();
		$this->parserTestHelper->setTestCase( $this );
	}

	public function testLocalFile() {
		$description = 'foo';
		$categories = array( 'Bar', 'Baz' );

		$metadata = array( 'OldKey' => 'OldValue', 'Categories' => array( 'value' => 'I_will_be_overwritten' ) );
		$maxCache = 3600;
		$file = $this->parserTestHelper->getLocalFile( $description, $categories );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $metadata, $file, $context, true, $maxCache );

		// cache interval was not changed
		$this->assertEquals( 3600, $maxCache );

		// metdata from other sources is kept but overwritten on conflict
		$this->assertArrayHasKey( 'OldKey', $metadata );
		$this->assertEquals( $metadata['OldKey'], 'OldValue' );
		$this->assertMetadataFieldEquals( 'Bar|Baz', 'Categories', $metadata );
	}

	public function testForeignApiFile() {
		$description = 'foo';

		$metadata = array( 'OldKey' => 'OldValue', 'Categories' => array( 'value' => 'I_will_remain' ) );
		$maxCache = 3600;
		$file = $this->parserTestHelper->getForeignApiFile( $description );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $metadata, $file, $context, true, $maxCache );

		// cache interval was not changed
		$this->assertEquals( 3600, $maxCache );

		// metdata from other sources is kept but overwritten on conflict
		$this->assertArrayHasKey( 'OldKey', $metadata );
		$this->assertEquals( $metadata['OldKey'], 'OldValue' );
		$this->assertMetadataFieldEquals( 'I_will_remain', 'Categories', $metadata );
	}

	public function testForeignDBFile() {
		$description = 'foo';
		$categories = array( 'Bar', 'Baz' );

		$metadata = array( 'OldKey' => 'OldValue', 'Categories' => array( 'value' => 'I_will_be_overwritten' ) );
		$maxCache = 3600;
		$file = $this->parserTestHelper->getForeignDbFile( $description, $categories );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $metadata, $file, $context, true, $maxCache );

		// cache interval is 12 hours for all remote files
		$this->assertEquals( 3600 * 12, $maxCache );

		// metdata from other sources is kept but overwritten on conflict
		$this->assertArrayHasKey( 'OldKey', $metadata );
		$this->assertEquals( $metadata['OldKey'], 'OldValue' );
		$this->assertMetadataFieldEquals( 'Bar|Baz', 'Categories', $metadata );
	}

	/*----------------------------------------------------------*/

	/**
	 * @dataProvider provideDesctiptionData
	 * @param string $testName a test name from ParserTestHelper::$testHTMLFiles
	 */
	public function testDescription( $testName ) {
		$maxCache = 3600;
		$actualMetadata = array();
		$description = $this->parserTestHelper->getTestHTML( $testName );
		$file = $this->parserTestHelper->getLocalFile( $description, array() );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $actualMetadata, $file, $context, true, $maxCache );

		$expectedMetadata = $this->parserTestHelper->getMetadata( $testName );
		foreach ( $expectedMetadata as $key => $val ) {
			$this->assertArrayHasKey( $key, $actualMetadata, "Field $key missing from metadata" );
			$this->assertEquals( $expectedMetadata[$key], $actualMetadata[$key], "Value for field $key does not match" );
		}
	}

	public function provideDesctiptionData() {
		return array(
			array( 'noinfo' ),
			array( 'simple' ),
			array( 'singlelang' ),
		);
	}

	/*----------------------------------------------------------*/

	/**
	 * @param mixed $expected metadata field value
	 * @param string $field metadata field name
	 * @param array $metadata metadata array as returned by GetExtendedMetadata hook
	 */
	protected function assertMetadataFieldEquals( $expected, $field, $metadata ) {
		$this->assertArrayHasKey( $field, $metadata );
		$this->assertArrayHasKey( 'value', $metadata[$field] );
		$this->assertEquals( $expected, $metadata[$field]['value'] );
	}
}
