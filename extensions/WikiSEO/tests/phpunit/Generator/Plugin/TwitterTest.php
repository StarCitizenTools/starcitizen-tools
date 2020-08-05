<?php

namespace MediaWiki\Extension\WikiSEO\Tests\Generator\Plugin;

use MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter;
use MediaWiki\Extension\WikiSEO\Tests\Generator\GeneratorTest;

class TwitterTest extends GeneratorTest {
	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::addMetadata
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::addMetadata
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
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::addTwitterSiteHandleTag
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\AbstractBaseGenerator::getConfigValue
	 */
	public function testAddTwitterSiteHandle() {
		$this->setMwGlobals( 'wgTwitterSiteHandle', '@TestKey' );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:site', $out->getHeadItemsArray() );
		$this->assertEquals(
			'<meta property="twitter:site" content="@TestKey"/>',
			$out->getHeadItemsArray()['twitter:site']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::addTwitterSiteHandleTag
	 */
	public function testIgnoreMetaIfGlobal() {
		$this->setMwGlobals( 'wgTwitterSiteHandle', '@TestKey' );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [ 'twitter_site' => '@NotAdded' ], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:site', $out->getHeadItemsArray() );
		$this->assertEquals(
			'<meta property="twitter:site" content="@TestKey"/>',
			$out->getHeadItemsArray()['twitter:site']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::init
	 */
	public function testContainsImage() {
		// Unset default image if set
		$this->setMwGlobals( 'wgWikiSeoDefaultImage', null );
		$this->setMwGlobals( 'wgWikiSeoDisableLogoFallbackImage', false );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:image', $out->getHeadItemsArray() );
		$this->assertContains( 'wiki.png', $out->getHeadItemsArray()['twitter:image'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::init
	 */
	public function testDoesNotContainLogoFallbackImage() {
		// Unset default image if set
		$this->setMwGlobals( 'wgWikiSeoDefaultImage', null );
		$this->setMwGlobals( 'wgWikiSeoDisableLogoFallbackImage', true );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayNotHasKey( 'twitter:image', $out->getHeadItemsArray() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::addMetadata
	 */
	public function testDefaultSummaryType() {
		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:card', $out->getHeadItemsArray() );
		$this->assertEquals(
			'<meta property="twitter:card" content="summary_large_image"/>',
			$out->getHeadItemsArray()['twitter:card']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::addMetadata
	 */
	public function testSummaryType() {
		// Unset default image if set
		$this->setMwGlobals( 'wgTwitterCardType', 'summary' );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'twitter:card', $out->getHeadItemsArray() );
		$this->assertEquals(
			'<meta property="twitter:card" content="summary"/>',
			$out->getHeadItemsArray()['twitter:card']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::addMetadata
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\Twitter::addTwitterSiteHandleTag
	 */
	public function testHandleNotSet() {
		// Unset default image if set
		$this->setMwGlobals( 'wgTwitterSiteHandle', null );

		$out = $this->newInstance();

		$generator = new Twitter();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayNotHasKey( 'twitter:site', $out->getHeadItemsArray() );
	}
}
