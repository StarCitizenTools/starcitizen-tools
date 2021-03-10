<?php
/**
 * PHP Unit tests for ResourceLoaderSchemaModule class.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers ResourceLoaderSchemaModule
 */
class ResourceLoaderSchemaModuleMemcachedTest extends MediaWikiTestCase {

	const TITLE = 'TestSchema';
	const REV = 99;

	/** @var ResourceLoaderContext */
	private $context;
	/** @var ResourceLoaderSchemaModule */
	private $module;

	function setUp() {
		parent::setUp();

		$this->context = new ResourceLoaderContext(
			new ResourceLoader(), new WebRequest() );

		$this->module = self::getMockSchemaModule( self::TITLE, self::REV );
	}

	function getMockSchemaModule( $title, $revid ) {
		$schema = $this
			->getMockBuilder( 'RemoteSchema' )
			->setConstructorArgs( [ $title, $revid ] )
			->getMock();

		$module = new ResourceLoaderSchemaModule( [
			'schema'   => $title,
			'revision' => $revid
		] );

		// Inject mock
		$module->schema = $schema;
		return $module;
	}

	/**
	 * @covers ResourceLoaderSchemaModule::getDefinitionSummary
	 */
	function testModuleVersion() {
		$version1 = $this->module->getVersionHash( $this->context );

		$module2 = self::getMockSchemaModule( self::TITLE, self::REV + 1 );
		$version2 = $module2->getVersionHash( $this->context );

		$this->assertNotEquals( $version1, $version2,
			'Version changes when revision changes'
		);
	}

	/**
	 * getTargets() should return an array including both 'desktop' and
	 * 'mobile'. This is essentially verifying that the base class
	 * implementation correctly delegates to the 'targets' property on
	 * ResourceLoaderSchemaModule.
	 */
	function testGetTargets() {
		$targets = $this->module->getTargets();
		$this->assertContains( 'mobile', $targets );
		$this->assertContains( 'desktop', $targets );
	}
}
