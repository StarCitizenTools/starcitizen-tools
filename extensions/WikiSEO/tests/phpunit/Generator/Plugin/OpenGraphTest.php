<?php

namespace MediaWiki\Extension\WikiSEO\Tests\Generator\Plugin;

use MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph;
use MediaWiki\Extension\WikiSEO\Tests\Generator\GeneratorTest;

class OpenGraphTest extends GeneratorTest {
	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::addMetadata
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::addTitleMeta
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::addSiteName
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\AbstractBaseGenerator::getConfigValue
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\AbstractBaseGenerator::getRevisionTimestamp
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::protocolizeUrl
	 */
	public function testAddMetadata() {
		$metadata = [
		'description' => 'Example Description',
		'keywords'    => 'Keyword 1, Keyword 2',
		];

		$out = $this->newInstance();

		$generator = new OpenGraph();
		$generator->init( $metadata, $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'og:title', $out->getHeadItemsArray() );
		$this->assertArrayHasKey( 'og:description', $out->getHeadItemsArray() );
		$this->assertArrayHasKey( 'article:tag', $out->getHeadItemsArray() );

		$this->assertContains( $out->getTitle()->getFullURL(), $out->getHeadItemsArray()['og:url'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::getRevisionTimestamp
	 */
	public function testRevisionTimestamp() {
		$out = $this->newInstance();

		$generator = new OpenGraph();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'article:published_time', $out->getHeadItemsArray() );
		$this->assertArrayHasKey( 'article:modified_time', $out->getHeadItemsArray() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::getRevisionTimestamp
	 */
	public function testPublishedTimestampManual() {
		$out = $this->newInstance();

		$generator = new OpenGraph();
		$generator->init(
			[
			'published_time' => '2012-01-01',
			], $out
		);
		$generator->addMetadata();

		$this->assertArrayHasKey( 'article:published_time', $out->getHeadItemsArray() );
		$this->assertContains(
			'2012-01-01',
			$out->getHeadItemsArray()['article:published_time']
		);
		$this->assertArrayHasKey( 'article:modified_time', $out->getHeadItemsArray() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::preprocessFileMetadata
	 */
	public function testContainsImage() {
		// Unset default image if set
		$this->setMwGlobals( 'wgWikiSeoDefaultImage', null );
		$this->setMwGlobals( 'wgWikiSeoDisableLogoFallbackImage', false );

		$out = $this->newInstance();

		$generator = new OpenGraph();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'og:image', $out->getHeadItemsArray() );
		$this->assertContains( 'wiki.png', $out->getHeadItemsArray()['og:image'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\OpenGraph::preprocessFileMetadata
	 */
	public function testDefaultImage() {
		$this->setMwGlobals( 'wgWikiSeoDefaultImage', 'wiki.png' );
		$this->setMwGlobals( 'wgWikiSeoDisableLogoFallbackImage', true );

		$out = $this->newInstance();

		$generator = new OpenGraph();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'og:image', $out->getHeadItemsArray() );
		$this->assertContains( 'wiki.png', $out->getHeadItemsArray()['og:image'] );
	}
}
