<?php

namespace PageImages\Tests;

use MediaWikiTestCase;
use PageImages;
use Title;

/**
 * @covers PageImages
 *
 * @group PageImages
 * @group Database
 *
 * @license WTFPL 2.0
 * @author Thiemo Mättig
 */
class PageImagesTest extends MediaWikiTestCase {

	public function testPagePropertyName() {
		$this->assertSame( 'page_image', PageImages::PROP_NAME );
	}

	public function testConstructor() {
		$pageImages = new PageImages();
		$this->assertInstanceOf( 'PageImages', $pageImages );
	}

	public function testGivenNonExistingPage_getPageImageReturnsFalse() {
		$title = Title::newFromText( wfRandomString() );
		$title->resetArticleID( 0 );

		$this->assertFalse( PageImages::getPageImage( $title ) );
	}

}
