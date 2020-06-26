<?php

/**
 * Integration tests for the Thanks API module
 *
 * @covers ApiCoreThank
 *
 * @group Thanks
 * @group Database
 * @group medium
 * @group API
 *
 * @author Addshore
 */
class ApiCoreThankIntegrationTest extends ApiTestCase {

	/**
	 * @var int filled in setUp
	 */
	private $revId;

	/**
	 * @var int The ID of a deletion log entry.
	 */
	protected $logId;

	public function setUp() {
		parent::setUp();

		// You can't thank yourself, kind of hacky but just use this other user
		$this->doLogin( 'uploader' );

		$pageName = __CLASS__;
		$content = __CLASS__;
		$pageTitle = Title::newFromText( $pageName );
		// If the page already exists, delete it, otherwise our edit will not result in a new revision
		if ( $pageTitle->exists() ) {
			$wikiPage = WikiPage::factory( $pageTitle );
			$wikiPage->doDeleteArticleReal( '' );
		}
		$result = $this->editPage( $pageName, $content );
		/** @var Status $result */
		$result = $result->getValue();
		/** @var Revision $revision */
		$revision = $result['revision'];
		$this->revId = $revision->getId();

		// Create a 2nd page and delete it, so we can thank for the log entry.
		$pageToDeleteTitle = Title::newFromText( 'Page to delete' );
		$pageToDelete = WikiPage::factory( $pageToDeleteTitle );
		$pageToDelete->doEditContent( ContentHandler::makeContent( '', $pageToDeleteTitle ), '' );
		$deleteStatus = $pageToDelete->doDeleteArticleReal( '' );
		$this->logId = $deleteStatus->getValue();

		$this->doLogin( 'sysop' );
		DeferredUpdates::clearPendingUpdates();
	}

	public function testRequestWithoutToken() {
		$this->setExpectedException( 'ApiUsageException', 'The "token" parameter must be set.' );
		$this->doApiRequest( [
			'action' => 'thank',
			'source' => 'someSource',
			'rev' => 1,
		] );
	}

	public function testValidRevRequest() {
		list( $result,, ) = $this->doApiRequestWithToken( [
			'action' => 'thank',
			'rev' => $this->revId,
		] );
		$this->assertSuccess( $result );
	}

	public function testValidLogRequest() {
		list( $result,, ) = $this->doApiRequestWithToken( [
			'action' => 'thank',
			'log' => $this->logId,
		] );
		$this->assertSuccess( $result );
	}

	public function testLogRequestWithDisallowedLogType() {
		// Empty the log-type whitelist.
		$this->setMwGlobals( [ 'wgThanksLogTypeWhitelist' => [] ] );
		$this->setExpectedException(
			ApiUsageException::class,
			"Log type 'delete' is not in the whitelist of permitted log types."
		);
		$this->doApiRequestWithToken( [
			'action' => 'thank',
			'log' => $this->logId,
		] );
	}

	public function testLogThanksForADeletedLogEntry() {
		global $wgUser;

		// Mark our test log entry as deleted.
		// To do this we briefly switch back to our 'uploader' test user.
		$this->doLogin( 'uploader' );
		$wgUser->mRights[] = 'deletelogentry';
		$this->doApiRequestWithToken( [
			'action' => 'revisiondelete',
			'type'   => 'logging',
			'ids'    => $this->logId,
			'hide'   => 'content',
		] );
		$this->doLogin( 'sysop' );

		// Then try to thank for it, and we should get an exception.
		$this->setExpectedException(
			ApiUsageException::class,
			"The requested log entry has been deleted and thanks cannot be given for it."
		);
		$this->doApiRequestWithToken( [
			'action' => 'thank',
			'log' => $this->logId,
		] );
	}

	public function testValidRequestWithSource() {
		list( $result,, ) = $this->doApiRequestWithToken( [
			'action' => 'thank',
			'source' => 'someSource',
			'rev' => $this->revId,
		] );
		$this->assertSuccess( $result );
	}

	protected function assertSuccess( $result ) {
		$this->assertEquals( [
			'result' => [
				'success' => 1,
				'recipient' => self::$users['uploader']->getUser()->getName(),
			],
		], $result );
	}

	public function testInvalidRequest() {
		$this->setExpectedException( 'ApiUsageException' );
		$this->doApiRequestWithToken( [ 'action' => 'thank' ] );
	}

}
