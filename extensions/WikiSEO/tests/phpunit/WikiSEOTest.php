<?php

namespace Octfx\WikiSEO\Tests;

use Octfx\WikiSEO\Tests\Generator\GeneratorTest;
use Octfx\WikiSEO\WikiSEO;

class WikiSEOTest extends GeneratorTest {
	private $replacementTitle = 'Replaced Title';

	/**
	 * @covers \Octfx\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \Octfx\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleReplace() {
		$seo = new WikiSEO();
		$out = $this->newInstance();

		$seo->setMetadataArray( [
			'title'      => $this->replacementTitle,
			'title_mode' => 'replace'
		] );

		$seo->addMetadataToPage( $out );

		$this->assertEquals( $this->replacementTitle, $out->getHTMLTitle() );
	}

	/**
	 * @covers \Octfx\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \Octfx\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleAppend() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$seo->setMetadataArray( [
			'title'      => $this->replacementTitle,
			'title_mode' => 'append'
		] );

		$seo->addMetadataToPage( $out );

		$this->assertEquals( sprintf( '%s - %s', $origTitle, $this->replacementTitle ), $out->getHTMLTitle() );
	}

	/**
	 * @covers \Octfx\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \Octfx\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitlePrepend() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$seo->setMetadataArray( [
			'title'      => $this->replacementTitle,
			'title_mode' => 'prepend'
		] );

		$seo->addMetadataToPage( $out );

		$this->assertEquals( sprintf( '%s - %s', $this->replacementTitle, $origTitle ), $out->getHTMLTitle() );
	}

	/**
	 * @covers \Octfx\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \Octfx\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleAppendChangedSeparator() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$seo->setMetadataArray( [
			'title'           => $this->replacementTitle,
			'title_mode'      => 'append',
			'title_separator' => 'SEP__SEP'
		] );

		$seo->addMetadataToPage( $out );

		$this->assertEquals( sprintf( '%sSEP__SEP%s', $origTitle, $this->replacementTitle ), $out->getHTMLTitle() );
	}

	/**
	 * @covers \Octfx\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \Octfx\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleHtmlEntities() {
		$seo = new WikiSEO();
		$out = $this->newInstance();

		$seo->setMetadataArray( [
			'title'           => $this->replacementTitle,
			'title_mode'      => 'append',
			'title_separator' => '&nbsp;&nbsp;--&nbsp;&nbsp;'
		] );

		$seo->addMetadataToPage( $out );

		$this->assertNotContains( '&nbsp;', $out->getHTMLTitle() );
	}
}