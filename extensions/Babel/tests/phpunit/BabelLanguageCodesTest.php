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
		$testData = [
			[ 'invalidLanguageCode', false ],
			[ 'en', 'en' ],
			[ 'eng', 'en' ],
			[ 'en-gb', 'en-gb' ],
			[ 'de', 'de' ],
			[ 'be-x-old', 'be-tarask' ],
		];
		// True BCP 47 normalization was added in MW 1.32
		if ( BabelLanguageCodes::bcp47( 'simple' ) === 'en-simple' ) {
			// ensure BCP 47-compliant codes are mapped to MediaWiki's
			// nonstandard internal codes
			$testData = array_merge( $testData, [
				[ 'en-simple', 'simple' ],
				[ 'cbk', 'cbk-zam' ],
				[ 'nrf', 'nrm' ],
			] );
		}
		return $testData;
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

	/**
	 * @dataProvider getCategoryCodeProvider
	 */
	public function testGetCategoryCode( $code, $expected ) {
		$this->assertSame( $expected, BabelLanguageCodes::getCategoryCode( $code ) );
	}

	public function getCategoryCodeProvider() {
		return [
			[ 'en', 'en' ],
			[ 'de', 'de' ],
			[ 'simple', 'simple' ],
			[ 'zh-hant', 'zh-Hant' ],
		];
	}

}
