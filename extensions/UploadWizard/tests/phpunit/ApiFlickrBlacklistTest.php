<?php

/**
 * Test the Flickr blacklist API.
 * Note that these tests trigger actual API requests to Flickr. The relevant settings need to
 * be configured (see the Flickr upload section of the UploadWizard docs).
 *
 * @group API
 * @group medium
 */
class ApiFlickrBlacklistTest extends ApiTestCase {
	/**
	 * Page used to store the blacklist. Will be created by the tests.
	 */
	const BLACKLIST_PAGE = 'TestFlickrBlacklistPage';

	/**
	 * NSID of the test user (actually, a real Flickr user chosen randomly
	 * from the blacklist at Commons).
	 */
	const PASFAM_NSID = '26011645@N00';

	/**
	 * Username of the test Flickr user.
	 */
	const PASFAM_USERNAME = 'pasfam';

	/**
	 * A photo from the test user.
	 */
	const PASFAM_IMAGE_PHOTO = 'http://www.flickr.com/photos/pasfam/147426941/';

	/**
	 * Static image URL for the test photo.
	 */
	const PASFAM_IMAGE_STATIC = 'http://farm1.staticflickr.com/44/147426941_98baf36fd1_o.jpg';

	/**
	 * A non-existing userid that won't match anything.
	 */
	const FAKE_NSID = '11111111@N00';

	public function testBlacklistMatchByNsid() {
		$this->checkApiSetup();
		$this->setFlickrBlacklistPage( self::BLACKLIST_PAGE );
		$this->editPage( self::BLACKLIST_PAGE, self::PASFAM_NSID );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_STATIC,
		) );
		$this->assertBlacklistMatch( $response );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_PHOTO,
		) );
		$this->assertBlacklistMatch( $response );
	}

	public function testBlacklistMatchByUsername() {
		$this->checkApiSetup();
		$this->setFlickrBlacklistPage( self::BLACKLIST_PAGE );
		$this->editPage( self::BLACKLIST_PAGE, self::PASFAM_USERNAME );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_STATIC,
		) );
		$this->assertBlacklistMatch( $response );
	}

	public function testBlacklistMatchWithMultipleItems() {
		$this->checkApiSetup();
		$this->setFlickrBlacklistPage( self::BLACKLIST_PAGE );
		$this->editPage( self::BLACKLIST_PAGE, 'foo bar ' . self::PASFAM_NSID . ' baz' );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_STATIC,
		) );
		$this->assertBlacklistMatch( $response );
	}

	public function testBlacklistNoMatch() {
		$this->checkApiSetup();
		$this->setFlickrBlacklistPage( self::BLACKLIST_PAGE );
		$this->editPage( self::BLACKLIST_PAGE, self::FAKE_NSID );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_STATIC,
		) );
		$this->assertNotBlacklistMatch( $response );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_PHOTO,
		) );
		$this->assertNotBlacklistMatch( $response );
	}

	/**
	 * Lines starting with # are comments.
	 */
	public function testBlacklistComment() {
		$this->checkApiSetup();
		$this->setFlickrBlacklistPage( self::BLACKLIST_PAGE );
		$this->editPage( self::BLACKLIST_PAGE, '# ' . self::PASFAM_NSID );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_STATIC,
		) );
		$this->assertNotBlacklistMatch( $response );
	}

	public function testGetFullBlacklist() {
		$this->checkApiSetup();
		$this->setFlickrBlacklistPage( self::BLACKLIST_PAGE );
		$this->editPage( self::BLACKLIST_PAGE, '26011645@N00' );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'list' => 1,
		) );

		$this->assertArrayHasKey( 'flickrblacklist', $response );
		$this->assertArrayHasKey( 'list', $response['flickrblacklist'] );
		$this->assertContains( self::PASFAM_NSID, $response['flickrblacklist']['list'] );
	}

	/**
	 * When the blacklist page does not exist, things should not break.
	 * This is the last test, to make sure that the blacklist cache (which is a static property so
	 * changes to it survive between tests) is left empty.
	 */
	public function testNoBlacklist() {
		$this->checkApiSetup();
		$this->setFlickrBlacklistPage( 'TestFlickrBlacklistPageDoesNotExist' );

		list( $response, , ) = $this->doApiRequest( array(
			'action' => 'flickrblacklist',
			'url' => self::PASFAM_IMAGE_STATIC,
		) );

		$this->assertNotBlacklistMatch( $response );
		// there should be no API call to Flickr; we have no good way of asserting that
	}

	protected function checkApiSetup() {
		// this is needed to initialize the global $wgUploadWizardConfig
		$wgUploadWizardConfig = UploadWizardConfig::getConfig();

		if ( !isset( $wgUploadWizardConfig['flickrApiKey'] ) ) {
			$this->markTestSkipped( 'This test needs a Flickr API key to work' );
		}
		if ( !isset( $wgUploadWizardConfig['flickrApiUrl'] )
			|| Http::get( $wgUploadWizardConfig['flickrApiUrl'] ) === false
		) {
			// Http::get returns false if the server is unreachable.
			// Sometimes unit tests may be run in places without network access.
			$this->markTestSkipped( $wgUploadWizardConfig['flickrApiUrl'] . ' is unreachable.' );
		}
	}
	/**
	 * Changes global parameter for blacklist page in such a way that the change can be
	 * unrolled after the test. Also clears the cache for the blacklist.
	 * @param string $page
	 */
	protected function setFlickrBlacklistPage( $page ) {
		global $wgUploadWizardConfig;
		$this->setMwGlobals( array(
			'wgUploadWizardConfig' => array_merge( $wgUploadWizardConfig, array(
				'flickrBlacklistPage' => $page,
			) ),
		) );

		// clear blacklist cache
		$reflection = new ReflectionClass( 'UploadWizardFlickrBlacklist' );
		$property = $reflection->getProperty( 'blacklist' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * @param array $result api call result
	 * @param string $message
	 */
	protected function assertBlacklistMatch( $result, $message = '' ) {
		$this->assertArrayHasKey( 'flickrblacklist', $result, $message ?: 'API result missing' );
		$this->assertArrayHasKey(
			'result',
			$result['flickrblacklist'],
			$message ?: 'API result missing'
		);
		$this->assertEquals(
			'bad',
			$result['flickrblacklist']['result'],
			$message ?: 'blacklist does not match'
		);
	}

	/**
	 * @param array $result api call result
	 * @param string $message
	 */
	protected function assertNotBlacklistMatch( $result, $message = '' ) {
		$this->assertArrayHasKey( 'flickrblacklist', $result, $message ?: 'API result missing' );
		$this->assertArrayHasKey(
			'result',
			$result['flickrblacklist'],
			$message ?: 'API result missing'
		);
		$this->assertEquals(
			'ok',
			$result['flickrblacklist']['result'],
			$message ?: 'blacklist does match'
		);
	}
}
