<?php

namespace PageImages\Tests\Hooks;

use LinksUpdate;
use PageImages\Hooks\LinksUpdateHookHandler;
use PageImages;
use ParserOutput;
use PHPUnit_Framework_TestCase;
use RepoGroup;

/**
 * @covers PageImages\Hooks\LinksUpdateHookHandler
 *
 * @group PageImages
 *
 * @license WTFPL 2.0
 * @author Thiemo Mättig
 */
class LinksUpdateHookHandlerTest extends PHPUnit_Framework_TestCase {

	public function tearDown() {
		// remove mock added in testGetMetadata()
		RepoGroup::destroySingleton();
		parent::tearDown();
	}

	/**
	 * @return LinksUpdate
	 */
	private function getLinksUpdate() {
		$parserOutput = new ParserOutput();
		$parserOutput->setExtensionData( 'pageImages', array(
			array( 'filename' => 'A.jpg', 'fullwidth' => 100, 'fullheight' => 50 ),
		) );

		$linksUpdate = $this->getMockBuilder( 'LinksUpdate' )
			->disableOriginalConstructor()
			->getMock();
		$linksUpdate->expects( $this->any() )
			->method( 'getParserOutput' )
			->will( $this->returnValue( $parserOutput ) );

		return $linksUpdate;
	}

	/**
	 * @return RepoGroup
	 */
	private function getRepoGroup() {
		$file = $this->getMockBuilder( 'File' )
			->disableOriginalConstructor()
			->getMock();
		// ugly hack to avoid all the unmockable crap in FormatMetadata
		$file->expects( $this->any() )
			->method( 'isDeleted' )
			->will( $this->returnValue( true ) );

		$repoGroup = $this->getMockBuilder( 'RepoGroup' )
			->disableOriginalConstructor()
			->getMock();
		$repoGroup->expects( $this->any() )
			->method( 'findFile' )
			->will( $this->returnValue( $file ) );

		return $repoGroup;
	}

	public function testOnLinksUpdate() {
		$linksUpdate = $this->getLinksUpdate();

		LinksUpdateHookHandler::onLinksUpdate( $linksUpdate );

		$this->assertTrue( property_exists( $linksUpdate, 'mProperties' ), 'precondition' );
		$this->assertSame( 'A.jpg', $linksUpdate->mProperties[PageImages::PROP_NAME] );
	}

	public function testFetchingExtendedMetadataFromFile() {
		// Required to make wfFindFile in LinksUpdateHookHandler::getScore return something.
		RepoGroup::setSingleton( $this->getRepoGroup() );
		$linksUpdate = $this->getLinksUpdate();

		LinksUpdateHookHandler::onLinksUpdate( $linksUpdate );

		$this->assertTrue( true, 'no errors in getMetadata' );
	}

}
