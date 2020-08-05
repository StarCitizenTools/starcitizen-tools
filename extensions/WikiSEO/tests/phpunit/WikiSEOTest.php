<?php

namespace MediaWiki\Extension\WikiSEO\Tests;

use MediaWiki\Extension\WikiSEO\Tests\Generator\GeneratorTest;
use MediaWiki\Extension\WikiSEO\Validator;
use MediaWiki\Extension\WikiSEO\WikiSEO;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RequestContext;

/**
 * Class WikiSEOTest
 *
 * @package MediaWiki\Extension\WikiSEO\Tests
 * @group   Database
 */
class WikiSEOTest extends GeneratorTest {
	private $replacementTitle = 'Replaced Title';

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleReplace() {
		$seo = new WikiSEO();
		$out = $this->newInstance();

		$this->setProperties(
			[
			'title' => $this->replacementTitle,
			'title_mode' => 'replace',
			], $out
		);

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

		$this->setProperties(
			[
			'title' => $this->replacementTitle,
			'title_mode' => 'append',
			], $out
		);

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals(
			sprintf( '%s - %s', $origTitle, $this->replacementTitle ),
			$out->getHTMLTitle()
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitlePrepend() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$this->setProperties(
			[
			'title' => $this->replacementTitle,
			'title_mode' => 'prepend',
			], $out
		);

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals(
			sprintf( '%s - %s', $this->replacementTitle, $origTitle ),
			$out->getHTMLTitle()
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleAppendChangedSeparator() {
		$seo = new WikiSEO();
		$out = $this->newInstance();
		$origTitle = $out->getHTMLTitle();

		$this->setProperties(
			[
			'title' => $this->replacementTitle,
			'title_mode' => 'append',
			'title_separator' => 'SEP__SEP',
			], $out
		);

		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals(
			sprintf( '%sSEP__SEP%s', $origTitle, $this->replacementTitle ),
			$out->getHTMLTitle()
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 */
	public function testModifyTitleHtmlEntities() {
		$seo = new WikiSEO();
		$out = $this->newInstance();

		$this->setProperties(
			[
			'title' => $this->replacementTitle,
			'title_mode' => 'append',
			'title_separator' => '&nbsp;&nbsp;--&nbsp;&nbsp;',
			], $out
		);

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

	/**
	 * Tests the parser setting and saving page props
	 *
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::fromParserFunction
	 * @covers \MediaWiki\Extension\WikiSEO\TagParser::parseArgs
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::saveMetadataToProps
	 * @throws \MWException
	 */
	public function testPropsParse() {
		$page =
		$this->insertPage(
			'PagePropParse', '{{#seo:|title=Test Title|title_mode=prepend}}',
			NS_MAIN
		);

		/**
		 * @var \Title $title
		 */
		$title = $page['title'];

		$page = $this->getExistingTestPage( $title );

		$result = $this->loadPropForPageId( $page->getId() );

		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'title_mode', $result );
	}

	/**
	 * Tests the parsing from Tag
	 *
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::__construct
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::instantiateMetadataPlugins
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::setMetadataFromPageProps
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::fromTag
	 * @covers \MediaWiki\Extension\WikiSEO\TagParser::expandWikiTextTagArray
	 * @throws \MWException
	 */
	public function testTagParse() {
		$pageTitle = 'Tag Parse Test2';

		$page =
			$this->insertPage(
				$pageTitle,
				'<seo title="Test Title :: {{FULLPAGENAME}}"></seo>', NS_MAIN
			);

		$context = new RequestContext();
		$context->setTitle( $page['title'] );
		$out = new OutputPage( $context );

		$seo = new WikiSEO();
		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		// HACK
		$this->assertEquals( 'Test Title&nbsp;:: ' . $pageTitle, htmlentities( $out->getHTMLTitle() ) );
	}

	/**
	 * Tests the parsing from Tag Body
	 *
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::__construct
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::instantiateMetadataPlugins
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::setMetadataFromPageProps
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::fromTag
	 * @covers \MediaWiki\Extension\WikiSEO\TagParser::expandWikiTextTagArray
	 * @throws \MWException
	 */
	public function testTagParseBody() {
		$pageTitle = 'Tag Parse Test Body';

		$page =
			$this->insertPage(
				$pageTitle,
				'<seo>title=Test Title :: {{FULLPAGENAME}}</seo>', NS_MAIN
			);

		$context = new RequestContext();
		$context->setTitle( $page['title'] );
		$out = new OutputPage( $context );

		$seo = new WikiSEO();
		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		// HACK
		$this->assertEquals( 'Test Title&nbsp;:: ' . $pageTitle, htmlentities( $out->getHTMLTitle() ) );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::__construct
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::instantiateMetadataPlugins
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @throws \MWException
	 */
	public function testNoHtmlEntitiesInTitleAmpersand() {
		$page =
		$this->insertPage(
			'Title with &',
			'{{#seo:|title={{FULLPAGENAME}}|title_mode=replace}}', NS_MAIN
		);

		$context = new RequestContext();
		$context->setTitle( $page['title'] );
		$out = new OutputPage( $context );

		$seo = new WikiSEO();
		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals( 'Title with &', $out->getHTMLTitle() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @throws \MWException
	 */
	public function testNoHtmlEntitiesInTitleApostrophe() {
		$page = $this->insertPage( 'Title with \'', '{{#seo:|title={{FULLPAGENAME}}}}', NS_MAIN );

		$context = new RequestContext();
		$context->setTitle( $page['title'] );
		$out = new OutputPage( $context );

		$seo = new WikiSEO();
		$seo->setMetadataFromPageProps( $out );
		$seo->addMetadataToPage( $out );

		$this->assertEquals( 'Title with \'', $out->getHTMLTitle() );
	}

	/**
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::__construct
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::instantiateMetadataPlugins
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::setMetadataFromPageProps
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::addMetadataToPage
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::modifyPageTitle
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::finalize
	 * @covers \MediaWiki\Extension\WikiSEO\WikiSEO::makeErrorHtml
	 * @throws \MWException
	 */
	public function testNoArgs() {
		$page = $this->insertPage( 'No Args Title', '{{#seo:}}', NS_MAIN );

		$context = new RequestContext();
		$context->setTitle( $page['title'] );
		$out = new OutputPage( $context );

		$errorMessage = wfMessage( 'wiki-seo-empty-attr-parser' )->parse();

		$this->assertContains( $errorMessage, $out->parseAsContent( "{{#seo:}}" ) );
	}

	/**
	 * Sets props on outputpage
	 *
	 * @param array      $props
	 * @param OutputPage $out
	 */
	private function setProperties( array $props, OutputPage $out ) {
		foreach ( $props as $key => $value ) {
			$out->setProperty( $key, $value );
		}
	}

	/**
	 * Loads the page props for a given page id.
	 *
	 * @param  int $id
	 * @return array
	 */
	private function loadPropForPageId( int $id ) {
		$dbl = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$db = $dbl->getConnection( DB_REPLICA );

		$propValue = $db->select(
			'page_props', [ 'pp_propname', 'pp_value' ], [
			'pp_page' => $id,
			'pp_propname' => Validator::$validParams,
			], __METHOD__
		);

		$result = [];

		if ( $propValue !== false ) {
			foreach ( $propValue as $row ) {
				$result[$row->pp_propname] = $row->pp_value;
			}
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 * @return     bool
	 */
	public function needsDB() {
		return true;
	}
}
