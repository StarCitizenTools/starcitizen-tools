<?php

namespace Octfx\WikiSEO\Tests\Generator\Plugin;

use Octfx\WikiSEO\Generator\MetaTag;
use Octfx\WikiSEO\Generator\Plugins\Twitter;
use Octfx\WikiSEO\Tests\Generator\GeneratorTest;

class TwitterTest extends GeneratorTest {
	/**
	 * @covers \Octfx\WikiSEO\Generator\Plugins\Twitter::init
	 * @covers \Octfx\WikiSEO\Generator\Plugins\Twitter::addMetadata
	 */
	public function testAddMetadata() {
		$metadata = [
			'description'  => 'Example Description',
			'keywords'     => 'Keyword 1, Keyword 2',
			'twitter_site' => 'example',
		];

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( $metadata, $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:title', $out->getHeadItemsArray() );
		$this->assertArrayHasKey( 'twitter:description', $out->getHeadItemsArray() );
		$this->assertArrayHasKey( 'twitter:site', $out->getHeadItemsArray() );
	}

	/**
	 * @covers \Octfx\WikiSEO\Generator\Plugins\Twitter::init
	 * @covers \Octfx\WikiSEO\Generator\Plugins\Twitter::addTwitterSiteHandleTag
	 */
	public function testAddTwitterSiteHandle() {
		$this->setMwGlobals( 'wgTwitterSiteHandle', '@TestKey' );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:site', $out->getHeadItemsArray() );
		$this->assertEquals( '<meta property="twitter:site" content="@TestKey"/>', $out->getHeadItemsArray()['twitter:site'] );
	}

	/**
	 * @covers \Octfx\WikiSEO\Generator\Plugins\Twitter::init
	 * @covers \Octfx\WikiSEO\Generator\Plugins\Twitter::addTwitterSiteHandleTag
	 */
	public function testIgnoreMetaIfGlobal() {
		$this->setMwGlobals( 'wgTwitterSiteHandle', '@TestKey' );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( ['twitter_site' => '@NotAdded'], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:site', $out->getHeadItemsArray() );
		$this->assertEquals( '<meta property="twitter:site" content="@TestKey"/>', $out->getHeadItemsArray()['twitter:site'] );
	}
}