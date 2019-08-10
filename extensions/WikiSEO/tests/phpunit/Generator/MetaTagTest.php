<?php

namespace Octfx\WikiSEO\Tests\Generator;

use Octfx\WikiSEO\Generator\MetaTag;

class MetaTagTest extends GeneratorTest {
	/**
	 * @covers \Octfx\WikiSEO\Generator\MetaTag::init
	 * @covers \Octfx\WikiSEO\Generator\MetaTag::addMetadata
	 */
	public function testAddMetadata() {
		$metadata = [
			'description' => 'Example Description',
			'keywords'    => 'Keyword 1, Keyword 2',
		];

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( $metadata, $out );
		$generator->addMetadata();

		$this->assertContains( [ 'description', 'Example Description' ], $out->getMetaTags() );
		$this->assertContains( [ 'keywords', 'Keyword 1, Keyword 2' ], $out->getMetaTags() );
	}

	/**
	 * @covers \Octfx\WikiSEO\Generator\MetaTag::init
	 * @covers \Octfx\WikiSEO\Generator\MetaTag::addGoogleSiteVerification
	 */
	public function testAddGoogleSiteKey() {
		$this->setMwGlobals( 'wgGoogleSiteVerificationKey', 'TestKey' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'google-site-verification', 'TestKey' ], $out->getMetaTags() );
	}

	/**
	 * @covers \Octfx\WikiSEO\Generator\MetaTag::init
	 * @covers \Octfx\WikiSEO\Generator\MetaTag::addFacebookAppId
	 */
	public function testAddFacebookAppId() {
		$this->setMwGlobals( 'wgFacebookAppId', '0011223344' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'fb:app_id', $out->getHeadItemsArray() );
		$this->assertEquals( '<meta property="fb:app_id" content="0011223344"/>', $out->getHeadItemsArray()['fb:app_id'] );
	}
}