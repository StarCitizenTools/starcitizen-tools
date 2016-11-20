<?php

/**
 * A class for getting currency-related data from cldr
 *
 * @author Katie Horn
 * @copyright Copyright © 2007-2013
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class CldrCurrency {
	private static $cache = [];

	/**
	 * Loads the file which contains the relevant data
	 * @param string $data 'symbols' | 'fractions' | 'locale'
	 * @throws Exception
	 */
	private static function loadData( $data ) {

		// bail if we already have it
		if ( isset( self::$cache[$data] ) ) {
			return;
		}

		$filename = null;
		switch ( $data ) {
			case 'symbols' :
				$filename = __DIR__ . '/CldrCurrency/Symbols.php';
				$value['symbols'] = 'currencySymbols';
				break;
			case 'fractions' :
			case 'locale' :
				$filename = __DIR__ . '/CldrSupplemental/Supplemental.php';
				$value['fractions'] = 'currencyFractions';
				$value['locale'] = 'localeCurrencies';
				break;
			default:
				throw new Exception( "Invalid 'data' parameter:\$data in " . __METHOD__ );
		}

		// go get it
		if ( file_exists( $filename ) ) {
			require_once $filename;
		}

		foreach ( $value as $dataname => $varname ) {
			self::$cache[$dataname] = $$varname;
		}
	}

	/**
	 * getSymbol returns a symbol for the relevant currency that should be
	 * recognized notation for that currency in the specified language and
	 * optionally specified country.
	 *
	 * NOTE: This function will always perform more reliably if a country is
	 * specified
	 *
	 * @param string $currency_code ISO 4217 3-character currency code.
	 * @param string $language_code ISO 639 2-character language code.
	 * @param string $country_code ISO 3166-1 Alpha-2 country code (optional)
	 * @return string The symbol for the specified currency, language, and country
	 */
	public static function getSymbol( $currency_code, $language_code, $country_code = null ) {
		self::loadData( 'symbols' );

		if ( array_key_exists( strtoupper( $currency_code ), self::$cache['symbols'] ) ) {
			$currency_code = strtoupper( $currency_code );
			$language_code = strtolower( $language_code );
			if ( $country_code !== null ) {
				$country_code = strtoupper( $country_code );
				if ( $country_code === 'UK' ) {
					$country_code = 'GB'; // dang iso overlap...
				}
			}

			// get the default (either the 'root' language, or the original ISO code)
			$default = $currency_code;
			if ( array_key_exists( 'root', self::$cache['symbols'][$currency_code] ) ) {
				$default = self::$cache['symbols'][$currency_code]['root'];
			}

			// language code might or might not exist
			if ( array_key_exists( $language_code, self::$cache['symbols'][$currency_code] ) ) {
				if ( is_array( self::$cache['symbols'][$currency_code][$language_code] ) ) {

					// did we specify a country? If not: Default.
					if ( $country_code !== null &&
						array_key_exists(
							$country_code,
							self::$cache['symbols'][$currency_code][$language_code]
						)
					) {
						return self::$cache['symbols'][$currency_code][$language_code][$country_code];
					} elseif ( array_key_exists(
						'DEFAULT',
						self::$cache['symbols'][$currency_code][$language_code]
					) ) {
						return self::$cache['symbols'][$currency_code][$language_code]['DEFAULT'];
					} else {
						return $default;
					}
				} else {
					return self::$cache['symbols'][$currency_code][$language_code];
				}
			} else {
				return $default;
			}
		} else {
			// we have no idea what you were going for, so you can have your old code back.
			return $currency_code;
		}
	}

	/**
	 * getCurrenciesByCountry returns an ordered list of ISO 4217 3-character
	 * currency codes that are valid in the specified country.
	 *
	 * @param string $country_code ISO 3166-1 Alpha-2 country code
	 * @return array An array of indicies and currency codes, or an empty array
	 * if no valid currency is found.
	 */
	public static function getCurrenciesByCountry( $country_code ) {
		self::loadData( 'locale' );
		$country_code = strtoupper( $country_code );
		if ( $country_code === 'UK' ) {
			$country_code = 'GB'; // iso overlap again
		}
		if ( array_key_exists( $country_code, self::$cache['locale'] ) ) {
			return self::$cache['locale'][$country_code];
		} else {
			return [];
		}
	}

	/**
	 * getDecimalPlaces returns a number specifying how many decimal places the
	 * requested currency supports.
	 *
	 * @param string $currency_code ISO 4217 3-character currency code.
	 * @return int The number of decimal places for the relevant currency (0 for nonfractional)
	 */
	public static function getDecimalPlaces( $currency_code ) {
		self::loadData( 'fractions' );
		$currency_code = strtoupper( $currency_code );
		if ( array_key_exists( $currency_code, self::$cache['fractions'] ) ) {
			return (int)self::$cache['fractions'][$currency_code]['digits'];
		} else {
			return (int)self::$cache['fractions']['DEFAULT']['digits'];
		}
	}
}
