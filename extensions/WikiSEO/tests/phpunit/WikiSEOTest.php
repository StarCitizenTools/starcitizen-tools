<?php

namespace MediaWiki\Extension\WikiSEO\Tests;

use MediaWiki\Extension\WikiSEO\Tests\Generator\GeneratorTest;
use MediaWiki\Extension\WikiSEO\WikiSEO;

class WikiSEOTest extends GeneratorTest {
	private $replacementTitle = 'Replaced Title';

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleReplace() {
		$seo = new WikiSEO();
		$out = $this->newInstance();

		$out->setProperty( 'WikiSEO', json_encode( [
			'title'      => $this->replacementTitle,
			'title_mode' => 'replace'
		] ) );

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals( $this->replacementTitle, $out->getHTMLTitle() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleAppend() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$out->setProperty( 'WikiSEO', json_encode( [
			'title'      => $this->replacementTitle,
			'title_mode' => 'append'
		] ) );

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals( sprintf( '%s - %s', $origTitle, $this->replacementTitle ),
			$out->getHTMLTitle() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitlePrepend() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$out->setProperty( 'WikiSEO', json_encode( [
			'title'      => $this->replacementTitle,
			'title_mode' => 'prepend'
		] ) );

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals( sprintf( '%s - %s', $this->replacementTitle, $origTitle ),
			$out->getHTMLTitle() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleAppendChangedSeparator() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$out->setProperty( 'WikiSEO', json_encode( [
			'title'           => $this->replacementTitle,
			'title_mode'      => 'append',
			'title_separator' => 'SEP__SEP'
		] ) );

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals( sprintf( '%sSEP__SEP%s', $origTitle, $this->replacementTitle ),
			$out->getHTMLTitle() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleHtmlEntities() {
		$seo = new WikiSEO();
		$out = $this->newInstance();

		$out->setProperty( 'WikiSEO', json_encode( [
			'title'           => $this->replacementTitle,
			'title_mode'      => 'append',
			'title_separator' => '&nbsp;&nbsp;--&nbsp;&nbsp;'
		] ) );

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertNotContains( '&nbsp;', $out->getHTMLTitle() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::protocolizeUrl
	 */
	public function testProtocolizeUrlProtoMissing() {
		$out = $this->newInstance();
		$url = '//localhost/Main_Page';

		$this->assertContains( 'http', WikiSEO::protocolizeUrl( $url, $out->getRequest() ) );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::protocolizeUrl
	 */
	public function testProtocolizeUrl() {
		$out = $this->newInstance();
		$url = 'http://localhost/Main_Page';

		$this->assertEquals( $url, WikiSEO::protocolizeUrl( $url, $out->getRequest() ) );
	}
}
