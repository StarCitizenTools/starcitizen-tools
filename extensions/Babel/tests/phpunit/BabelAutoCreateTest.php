<?php

namespace Babel\Tests;

use BabelAutoCreate;
use MediaWikiTestCase;
use Title;
use WikiPage;

/**
 * @covers BabelAutoCreate
 *
 * @group Babel
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class BabelAutoCreateTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setContentLang( 'qqx' );
	}

	public function testOnUserGetReservedNames() {
		$names = [];
		$this->assertSame( [], $names, 'Precondition' );

		$this->assertTrue( BabelAutoCreate::onUserGetReservedNames( $names ) );
		$this->assertSame( [ 'msg:babel-autocreate-user' ], $names );
	}

	/**
	 * @dataProvider createProvider
	 */
	public function testCreate( $category, $code, $level, $expected ) {
		BabelAutoCreate::create( $category, $code, $level );
		$page = WikiPage::factory( Title::newFromText( 'Category:' . $category ) );
		$this->assertTrue( $page->exists() );
		$this->assertSame( $expected, $page->getContent()->getText() );
	}

	public function createProvider() {
		return [
			[
				'category-1', 'en', null,
				'(babel-autocreate-text-main: English, en)'
			],
			[
				'category-2', 'en', 'level-2',
				'(babel-autocreate-text-levels: level-2, English, en)'
			],
		];
	}

	public function testUser() {
		$user = BabelAutoCreate::user();
		$this->assertInstanceOf( 'User', $user );
	}

}
