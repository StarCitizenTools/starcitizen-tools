<?php

/**
 * A class for querying translated country names from CLDR data.
 *
 * @author Niklas Laxström
 * @author Ryan Kaldari
 * @copyright Copyright © 2007-2011
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class CountryNames extends CldrNames {

	private static $cache = [];

	/**
	 * Get localized country names for a particular language, using fallback languages for missing
	 * items.
	 *
	 * @param string $code The language to return the list in
	 * @return array an associative array of country codes and localized country names
	 */
	public static function getNames( $code ) {
		// Load country names localized for the requested language
		$names = self::loadLanguage( $code );

		// Load missing country names from fallback languages
		$fallbacks = Language::getFallbacksFor( $code );
		foreach ( $fallbacks as $fallback ) {
			// Overwrite the things in fallback with what we have already
			$names = array_merge( self::loadLanguage( $fallback ), $names );
		}

		return $names;
	}

	/**
	 * Load country names localized for a particular language. Helper function for getNames.
	 *
	 * @param string $code The language to return the list in
	 * @return array an associative array of country codes and localized country names
	 */
	private static function loadLanguage( $code ) {
		if ( !isset( self::$cache[$code] ) ) {
			/* Load override for wrong or missing entries in cldr */
			$override = __DIR__ . '/LocalNames/' . self::getOverrideFileName( $code );
			if ( Language::isValidBuiltInCode( $code ) && file_exists( $override ) ) {
				$countryNames = false;
				require $override;
				if ( is_array( $countryNames ) ) {
					self::$cache[$code] = $countryNames;
				}
			}

			$filename = __DIR__ . '/CldrNames/' . self::getFileName( $code );
			if ( Language::isValidBuiltInCode( $code ) && file_exists( $filename ) ) {
				$countryNames = false;
				require $filename;
				if ( is_array( $countryNames ) ) {
					if ( isset( self::$cache[$code] ) ) {
						// Add to existing list of localized country names
						self::$cache[$code] = self::$cache[$code] + $countryNames;
					} else {
						// No list exists, so create it
						self::$cache[$code] = $countryNames;
					}
				}
			} else {
				wfDebug( __METHOD__ . ": Unable to load country names for $filename\n" );
			}
		}

		return isset( self::$cache[$code] ) ? self::$cache[$code] : [];
	}
}
