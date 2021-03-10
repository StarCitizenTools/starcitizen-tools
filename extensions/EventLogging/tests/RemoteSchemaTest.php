<?php
/**
 * PHP Unit tests for RemoteSchema class.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers RemoteSchema
 */
class RemoteSchemaTest extends MediaWikiTestCase {

	/** @var PHPUnit_Framework_MockObject_MockObject */
	private $cache;
	/** @var PHPUnit_Framework_MockObject_MockObject */
	private $http;
	/** @var RemoteSchema */
	private $schema;

	public $statusSchema = [ 'status' => [ 'type' => 'string' ] ];

	function setUp() {
		parent::setUp();

		$this->cache = $this
			->getMockBuilder( 'MemcachedPhpBagOStuff' )
			->disableOriginalConstructor()
			->getMock();

		$this->http = $this->getMock( 'stdClass', [ 'get' ] );
		$this->schema = new RemoteSchema( 'Test', 99, $this->cache, $this->http );
	}

	/**
	 * Tests behavior when content is in memcached.
	 * This is the most common scenario.
	 */
	function testSchemaInCache() {
		global $wgEventLoggingDBname;

		// If the revision was in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( "schema:{$wgEventLoggingDBname}:Test:99" ) )
			->will( $this->returnValue( $this->statusSchema ) );

		// ...no HTTP call will need to be made
		$this->http
			->expects( $this->never() )
			->method( 'get' );

		// ...so no lock will be acquired
		$this->cache
			->expects( $this->never() )
			->method( 'add' );

		$this->assertEquals( $this->statusSchema, $this->schema->get() );
	}

	/**
	 * Calling get() multiple times should not result in multiple
	 * memcached calls; instead, once the content is retrieved, it
	 * should be stored locally as an object attribute.
	 * @covers RemoteSchema::get
	 */
	function testContentLocallyCached() {
		$this->cache
			->expects( $this->once() )  // <-- the assert
			->method( 'get' )
			->will( $this->returnValue( $this->statusSchema ) );
		$this->schema->get();
		$this->schema->get();
		$this->schema->get();
	}

	/**
	 * Tests behavior when content is missing from memcached and has to
	 * be retrieved via HTTP instead.
	 */
	function testSchemaNotInCacheDoUpdate() {
		global $wgEventLoggingDBname;

		// If the revision was not in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( "schema:{$wgEventLoggingDBname}:Test:99" ) )
			->will( $this->returnValue( false ) );

		// ...RemoteSchema will attempt to acquire an update lock:
		$this->cache
			->expects( $this->any() )
			->method( 'add' )
			->with( $this->stringContains( "schema:{$wgEventLoggingDBname}:Test:99" ) )
			->will( $this->returnValue( true ) );

		// With the lock acquired, we'll see an HTTP request
		// for the revision:
		$this->http
			->expects( $this->once() )
			->method( 'get' )
			->with(
				$this->stringContains( '?' ),
				$this->equalTo( [
					'timeout' => RemoteSchema::LOCK_TIMEOUT * 0.8
				] )
			)
			->will( $this->returnValue( FormatJson::encode( $this->statusSchema ) ) );

		$this->assertEquals( $this->statusSchema, $this->schema->get() );
	}

	/**
	 * Tests behavior when content is missing from memcached and an
	 * update lock cannot be acquired.
	 */
	function testSchemaNotInCacheNoUpdate() {
		global $wgEventLoggingDBname;

		// If the revision was not in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( "schema:{$wgEventLoggingDBname}:Test:99" ) )
			->will( $this->returnValue( false ) );

		// ...we'll see an attempt to acquire update lock,
		// which we'll deny:
		$this->cache
			->expects( $this->once() )
			->method( 'add' )
			->with( "schema:{$wgEventLoggingDBname}:Test:99:lock" )
			->will( $this->returnValue( false ) );

		// Without a lock, no HTTP requests will be made:
		$this->http
			->expects( $this->never() )
			->method( 'get' );

		// When unable to retrieve from memcached or acquire an update
		// lock to retrieve via HTTP, getSchema() will return false.
		$this->assertFalse( $this->schema->get() );
	}
}
