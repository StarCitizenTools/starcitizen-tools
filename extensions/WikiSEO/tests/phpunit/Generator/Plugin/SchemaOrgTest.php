<?php

namespace MediaWiki\Extension\WikiSEO\Tests\Generator\Plugin;

use MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg;
use MediaWiki\Extension\WikiSEO\Tests\Generator\GeneratorTest;

class SchemaOrgTest extends GeneratorTest {
	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::addMetadata
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getRevisionTimestamp
	 */
	public function testAddMetadata() {
		$metadata = [
		'description' => 'Example Description',
		'type'        => 'website',
		];

		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init( $metadata, $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'jsonld-metadata', $out->getHeadItemsArray() );

		$this->assertContains( '@type', $out->getHeadItemsArray()['jsonld-metadata'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getAuthorMetadata
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getConfigValue
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getLogoMetadata
	 */
	public function testContainsOrganization() {
		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( 'Organization', $out->getHeadItemsArray()['jsonld-metadata'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getSearchActionMetadata
	 */
	public function testContainsSearchAction() {
		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( 'SearchAction', $out->getHeadItemsArray()['jsonld-metadata'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getAuthorMetadata
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getConfigValue
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getLogoMetadata
	 */
	public function testContainsAuthorAndPublisher() {
		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( 'author', $out->getHeadItemsArray()['jsonld-metadata'] );
		$this->assertContains( 'publisher', $out->getHeadItemsArray()['jsonld-metadata'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getRevisionTimestamp
	 */
	public function testContainsRevisionTimestamp() {
		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( 'datePublished', $out->getHeadItemsArray()['jsonld-metadata'] );
		$this->assertContains( 'dateModified', $out->getHeadItemsArray()['jsonld-metadata'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getRevisionTimestamp
	 */
	public function testContainsPublishedTimestampManual() {
		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init(
			[
			'published_time' => '2012-01-01',
			], $out
		);
		$generator->addMetadata();

		$this->assertContains( '2012-01-01', $out->getHeadItemsArray()['jsonld-metadata'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getImageMetadata
	 */
	public function testContainsImageObject() {
		$this->setMwGlobals( 'wgWikiSeoDisableLogoFallbackImage', false );

		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( 'wiki.png', $out->getHeadItemsArray()['jsonld-metadata'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\Plugins\SchemaOrg::getTypeMetadata
	 */
	public function testTypeMetadata() {
		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init(
			[
			'type' => 'test-type',
			 ], $out
		);
		$generator->addMetadata();

		$this->assertContains( 'test-type', $out->getHeadItemsArray()['jsonld-metadata'] );
	}
}
