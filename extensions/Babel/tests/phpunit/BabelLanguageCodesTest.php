<?php

namespace Babel\Tests;

use BabelLanguageCodes;

/**
 * @covers BabelLanguageCodes
 *
 * @group Babel
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class BabelLanguageCodesTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider getCodeProvider
	 */
	public function testGetCode( $code, $expected ) {
		$this->assertSame( $expected, BabelLanguageCodes::getCode( $code ) );
	}

	public function getCodeProvider() {
		return [
			[ 'invalidLanguageCode', false ],
			[ 'en', 'en' ],
			[ 'eng', 'en' ],
			[ 'en-gb', 'en-gb' ],
			[ 'de', 'de' ],
			[ 'be-x-old', 'be-tarask' ],
		];
	}

	/**
	 * @dataProvider getNameProvider
	 */
	public function testGetName( $code, $language, $expected ) {
		$this->assertSame( $expected, BabelLanguageCodes::getName( $code, $language ) );
	}

	public function getNameProvider() {
		return [
			[ 'invalidLanguageCode', null, false ],
			[ 'en', null, 'English' ],
			[ 'en', 'en', 'English' ],
			[ 'eng', null, 'English' ],
			[ 'en-gb', null, 'British English' ],
			[ 'de', null, 'Deutsch' ],
		];
	}

}
