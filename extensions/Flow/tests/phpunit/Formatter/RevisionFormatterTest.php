<?php

namespace Flow\Tests\Formatter;

use Flow\Exception\FlowException;
use Flow\FlowActions;
use Flow\Formatter\FormatterRow;
use Flow\Formatter\RevisionFormatter;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Tests\PostRevisionTestCase;
use RequestContext;
use Title;
use User;

/**
 * @group Flow
 */
class RevisionFormatterTest extends PostRevisionTestCase {
	protected $user;

	protected $topicTitleRevisionUnspecified;

	protected $topicTitleRevisionSpecified;

	protected $postRevisionUnspecified;

	protected $postRevisionSpecified;

	protected function setUp() {
		parent::setUp();

		$this->user = User::newFromName( '127.0.0.1', false );

		// These tests don't provide sufficient data to properly run all listeners
		$this->clearExtraLifecycleHandlers();
	}

	/**
	 * @dataProvider decideContentFormatProvider
	 */
	public function testDecideContentFormat( $expectedFormat, $setContentRequestedFormat, $setContentRevisionId, $revision ) {
		list( $formatter ) = $this->makeFormatter();
		$formatter->setContentFormat( $setContentRequestedFormat, $setContentRevisionId );

		$this->assertEquals(
			$expectedFormat,
			$formatter->decideContentFormat( $revision )
		);
	}

	public function decideContentFormatProvider() {
		$topicTitleRevisionUnspecified = $this->mockTopicTitleRevision();
		$topicTitleRevisionSpecified = $this->mockTopicTitleRevision();

		$postRevisionUnspecified = $this->mockPostRevision();
		$postRevisionSpecified = $this->mockPostRevision();

		return array(
			array(
				'topic-title-html',
				'fixed-html',
				null,
				$topicTitleRevisionUnspecified,
			),
			// Specified for a different revision, so uses canonicalized
			// version of class default (fixed-html => topic-title-html).
			array(
				'topic-title-html',
				'topic-title-wikitext',
				$topicTitleRevisionSpecified->getRevisionId(),
				$topicTitleRevisionUnspecified,
			),
			array(
				'topic-title-wikitext',
				'html',
				null,
				$topicTitleRevisionUnspecified,
			),
			array(
				'topic-title-wikitext',
				'wikitext',
				null,
				$topicTitleRevisionUnspecified,
			),
			array(
				'fixed-html',
				'fixed-html',
				null,
				$postRevisionUnspecified,
			),
			// We've specified it, but for another rev ID, so it uses the class default
			// of fixed-html.
			array(
				'fixed-html',
				'wikitext',
				$postRevisionSpecified->getRevisionId(),
				$postRevisionUnspecified,
			),
			array(
				'html',
				'html',
				null,
				$postRevisionUnspecified,
			),
			array(
				'wikitext',
				'wikitext',
				null,
				$postRevisionUnspecified,
			),
			array(
				'topic-title-html',
				'topic-title-html',
				null,
				$topicTitleRevisionUnspecified,
			),
			array(
				'topic-title-wikitext',
				'topic-title-wikitext',
				null,
				$topicTitleRevisionUnspecified,
			),
			array(
				'topic-title-html',
				'topic-title-html',
				$topicTitleRevisionSpecified->getRevisionId(),
				$topicTitleRevisionSpecified,
			),
			array(
				'topic-title-wikitext',
				'topic-title-wikitext',
				$topicTitleRevisionSpecified->getRevisionId(),
				$topicTitleRevisionSpecified,
			),
			array(
				'fixed-html',
				'fixed-html',
				$postRevisionSpecified->getRevisionId(),
				$postRevisionSpecified,
			),
			array(
				'html',
				'html',
				$postRevisionSpecified->getRevisionId(),
				$postRevisionSpecified,
			),
			array(
				'wikitext',
				'wikitext',
				$postRevisionSpecified->getRevisionId(),
				$postRevisionSpecified,
			),
		);
	}


	/**
	 * @expectedException \Flow\Exception\FlowException
	 * @dataProvider decideContentInvalidFormatProvider
	 */
	public function testDecideContentInvalidFormat( $setContentRequestedFormat, $setContentRevisionId, $revision ) {
		list( $formatter ) = $this->makeFormatter();
		$formatter->setContentFormat( $setContentRequestedFormat, $setContentRevisionId );
		$formatter->decideContentFormat( $revision );
	}

	public function decideContentInvalidFormatProvider() {
		$topicTitleRevisionSpecified = $this->mockTopicTitleRevision();
		$postRevisionSpecified = $this->mockPostRevision();
		$postRevisionUnspecified = $this->mockPostRevision();

		return array(
			array(
				'wikitext',
				$topicTitleRevisionSpecified->getRevisionId(),
				$topicTitleRevisionSpecified,
			),
			array(
				'topic-title-html',
				$postRevisionSpecified->getRevisionId(),
				$postRevisionSpecified,
			),
			array(
				'topic-title-html',
				null,
				$postRevisionUnspecified,
			),
			array(
				'topic-title-wikitext',
				$postRevisionSpecified->getRevisionId(),
				$postRevisionSpecified,
			),
			array(
				'topic-title-wikitext',
				null,
				$postRevisionUnspecified,
			),
		);
	}

	/**
	 * @expectedException \Flow\Exception\FlowException
	 * @dataProvider setContentFormatInvalidProvider
	 */
	public function testSetContentFormatInvalidProvider( $requestedFormat, $revisionId) {
		list( $formatter ) = $this->makeFormatter();
		$formatter->setContentFormat( $requestedFormat, $revisionId );
	}

	public function setContentFormatInvalidProvider() {
		$postRevisionSpecified = $this->mockPostRevision();

		return array(
			array(
				'fake-format',
				null
			),
			array(
				'another-fake-format',
				$postRevisionSpecified->getRevisionId()
			),
		);
	}

	public function testMockFormatterBasicallyWorks() {
		list( $formatter, $ctx ) = $this->makeFormatter();
		$result = $formatter->formatApi( $this->generateFormatterRow( 'my new topic' ), $ctx );
		$this->assertEquals( 'new-post', $result['changeType'] );
		$this->assertEquals( 'my new topic', $result['content']['content'] );
	}

	public function testFormattingEditedTitle() {
		list( $formatter, $ctx ) = $this->makeFormatter();
		$row = $this->generateFormatterRow();
		$row->previousRevision = $row->revision;
		$row->revision = $row->revision->newNextRevision(
			$this->user,
			'replacement content',
			'topic-title-wikitext',
			'edit-title',
			$row->workflow->getArticleTitle()
		);
		$result = $formatter->formatApi( $row, $ctx );
		$this->assertEquals( 'edit-title', $result['changeType'] );
		$this->assertEquals( 'replacement content', $result['content']['content'] );
	}

	public function testFormattingContentLength() {
		$content = 'something something';
		$nextContent = 'ברוכים הבאים לוויקיפדיה!';

		list( $formatter, $ctx, $permissions, $templating, $usernames, $actions ) = $this->makeFormatter( true );

		$row = $this->generateFormatterRow( $content );
		$result = $formatter->formatApi( $row, $ctx );
		$this->assertEquals(
			strlen( $content ),
			$result['size']['new'],
			'New topic content reported correctly'
		);
		$this->assertEquals(
			0,
			$result['size']['old'],
			'With no previous revision the old size is 0'
		);

		$row->previousRevision = $row->revision;
		// @todo newNextRevision feels too generic, there should be an editTitle method?
		$row->revision = $row->currentRevision = $row->revision->newNextRevision(
			$this->user,
			$nextContent,
			'topic-title-wikitext',
			'edit-title',
			$row->workflow->getArticleTitle()
		);
		$result = $formatter->formatApi( $row, $ctx );
		$this->assertEquals(
			mb_strlen( $nextContent ),
			$result['size']['new'],
			'After editing topic content the new size has been updated'
		);
		$this->assertEquals(
			mb_strlen( $content ),
			$result['size']['old'],
			'After editing topic content the old size has been updated'
		);
	}

	public function generateFormatterRow( $wikitext = 'titlebar content' ) {
		$row = new FormatterRow;

		$row->workflow = Workflow::create( 'topic', Title::newMainPage() );
		$this->workflows[$row->workflow->getId()->getAlphadecimal()] = $row->workflow;

		$row->rootPost = PostRevision::createTopicPost( $row->workflow, $this->user, $wikitext );
		$row->revision = $row->currentRevision = $row->rootPost;
		$this->store( $row->revision );

		return $row;
	}

	protected function mockActions() {
		return $this->getMockBuilder( 'Flow\FlowActions' )
			->disableOriginalConstructor()
			->getMock();
	}

	protected function mockPermissions( FlowActions $actions ) {
		$permissions = $this->getMockBuilder( 'Flow\RevisionActionPermissions' )
			->disableOriginalConstructor()
			->getMock();
		// bit of a code smell, should pass actions directly in constructor?
		$permissions->expects( $this->any() )
			->method( 'getActions' )
			->will( $this->returnValue( $actions ) );
		// perhaps another code smell, should have a method that does whatever this
		// uses the user for
		$permissions->expects( $this->any() )
			->method( 'getUser' )
			->will( $this->returnValue( $this->user ) );

		return $permissions;
	}

	protected function mockPostRevision() {
		$postRevision = $this->getMockBuilder( 'Flow\Model\PostRevision' )->getMock();
		$postRevision->expects( $this->any() )
			->method( 'isTopicTitle' )
			->will( $this->returnValue( false ) );
		$postRevision->expects( $this->any() )
			->method( 'getRevisionId' )
			->will( $this->returnValue( UUID::create() ) );
		return $postRevision;
	}

	protected function mockTemplating() {
		$templating = $this->getMockBuilder( 'Flow\Templating' )
			->disableOriginalConstructor()
			->getMock();
		$templating->expects( $this->any() )
			->method( 'getModeratedRevision' )
			->will( $this->returnArgument( 0 ) );
		$templating->expects( $this->any() )
			->method( 'getContent' )
			->will( $this->returnCallback( function( $revision, $contentFormat ) {
				return $revision->getContent( $contentFormat );
			} ) );

		return $templating;
	}

	protected function mockTopicTitleRevision() {
		$topicTitleRevision = $this->getMockBuilder( 'Flow\Model\PostRevision' )->getMock();
		$topicTitleRevision->expects( $this->any() )
			->method( 'isTopicTitle' )
			->will( $this->returnValue( true ) );
		$topicTitleRevision->expects( $this->any() )
			->method( 'getRevisionId' )
			->will( $this->returnValue( UUID::create() ) );
		return $topicTitleRevision;
	}

	protected function mockUserNameBatch() {
		return $this->getMockBuilder( 'Flow\Repository\UserNameBatch' )
			->disableOriginalConstructor()
			->getMock();
	}

	public function makeFormatter( $returnAll = false ) {
		$actions = $this->mockActions();
		$permissions = $this->mockPermissions( $actions );
		// formatting only proceedes when this is true
		$permissions->expects( $this->any() )
			->method( 'isAllowed' )
			->will( $this->returnValue( true ) );
		$templating = $this->mockTemplating();
		$usernames = $this->mockUserNameBatch();
		$formatter = new RevisionFormatter( $permissions, $templating, $usernames, 3 );

		$ctx = RequestContext::getMain();
		$ctx->setUser( $this->user );


		if ( $returnAll ) {
			return array( $formatter, $ctx, $permissions, $templating, $usernames, $actions );
		} else {
			return array( $formatter, $ctx );
		}
	}
}
