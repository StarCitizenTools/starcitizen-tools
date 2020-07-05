<?php

namespace MediaWiki\Extension\WikiSEO\Tests\Generator;

use MediaWiki\Extension\WikiSEO\Generator\MetaTag;

class MetaTagTest extends GeneratorTest {
	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addMetadata
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\AbstractBaseGenerator::getConfigValue
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
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addGoogleSiteVerification
	 */
	public function testAddGoogleSiteKey() {
		$this->setMwGlobals( 'wgGoogleSiteVerificationKey', 'google-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'google-site-verification', 'google-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addNortonSiteVerification
	 */
	public function testAddNortonSiteVerification() {
		$this->setMwGlobals( 'wgNortonSiteVerificationKey', 'norton-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains(
			[
			'norton-safeweb-site-verification',
			'norton-key',
			], $out->getMetaTags()
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addPinterestSiteVerification
	 */
	public function testAddPinterestSiteVerification() {
		$this->setMwGlobals( 'wgPinterestSiteVerificationKey', 'pinterest-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'p:domain_verify', 'pinterest-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addAlexaSiteVerification
	 */
	public function testAddAlexaSiteVerification() {
		$this->setMwGlobals( 'wgAlexaSiteVerificationKey', 'alexa-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'alexaVerifyID', 'alexa-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addYandexSiteVerification
	 */
	public function testAddYandexSiteVerification() {
		$this->setMwGlobals( 'wgYandexSiteVerificationKey', 'yandex-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'yandex-verification', 'yandex-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addBingSiteVerification
	 */
	public function testAddBingSiteVerification() {
		$this->setMwGlobals( 'wgBingSiteVerificationKey', 'bing-key' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertContains( [ 'msvalidate.01', 'bing-key' ], $out->getMetaTags() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addFacebookAppId
	 */
	public function testAddFacebookAppId() {
		$this->setMwGlobals( 'wgFacebookAppId', '0011223344' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'fb:app_id', $out->getHeadItemsArray() );
		$this->assertEquals(
			'<meta property="fb:app_id" content="0011223344"/>',
			$out->getHeadItemsArray()['fb:app_id']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::init
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addFacebookAdmins
	 */
	public function testAddFacebookAdmins() {
		$this->setMwGlobals( 'wgFacebookAdmins', '0011223344' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'fb:admins', $out->getHeadItemsArray() );
		$this->assertEquals(
			'<meta property="fb:admins" content="0011223344"/>',
			$out->getHeadItemsArray()['fb:admins']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addHrefLangs
	 */
	public function testAddDefaultLanguageLink() {
		$this->setMwGlobals( 'wgWikiSeoDefaultLanguage', 'de-de' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init( [], $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'de-de', $out->getHeadItemsArray() );
		$this->assertContains( 'hreflang="de-de"', $out->getHeadItemsArray()['de-de'] );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addHrefLangs
	 */
	public function testAddLanguageLinks() {
		$this->setMwGlobals( 'wgWikiSeoDefaultLanguage', 'de-de' );

		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init(
			[
			'hreflang_de-de' => 'https://example.de',
			'hreflang_nl-nl' => 'https://example.nl',
			'hreflang_en-us' => 'https://example.com',
			], $out
		);
		$generator->addMetadata();

		$this->assertArrayHasKey( 'hreflang_de-de', $out->getHeadItemsArray() );
		$this->assertArrayHasKey( 'hreflang_nl-nl', $out->getHeadItemsArray() );
		$this->assertArrayHasKey( 'hreflang_en-us', $out->getHeadItemsArray() );

		$this->assertContains(
			'https://example.de"',
			$out->getHeadItemsArray()['hreflang_de-de']
		);
		$this->assertContains(
			'https://example.nl"',
			$out->getHeadItemsArray()['hreflang_nl-nl']
		);
		$this->assertContains(
			'https://example.com"', $out->getHeadItemsArray()
			['hreflang_en-us']
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\Generator\MetaTag::addHrefLangs
	 */
	public function testAddLanguageLinksWrongFormatted() {
		$out = $this->newInstance();

		$generator = new MetaTag();
		$generator->init(
			[
			'hrefWRONGlang_de-de' => 'https://example.de',
			], $out
		);
		$generator->addMetadata();

		$this->assertArrayNotHasKey( 'hrefWRONGlang_de-de', $out->getHeadItemsArray() );
	}
}
