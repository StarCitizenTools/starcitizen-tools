<?php

/**
 * A class for querying translated time units from CLDR data.
 *
 * @author Niklas Laxström
 * @author Ryan Kaldari
 * @copyright Copyright © 2007-2013
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class TimeUnits extends CldrNames {

	private static $cache = [];

	/**
	 * Get localized time units for a particular language, using fallback languages for missing
	 * items. The time units are returned as an associative array. The keys are of the form:
	 * <unit>-<tense>-<ordinality> (for example, 'hour-future-two'). The values include a placeholder
	 * for the number (for example, '{0} months ago').
	 *
	 * @param string $code The language to return the list in
	 * @return array an associative array of time unit codes and localized time units
	 */
	public static function getUnits( $code ) {
		// Load time units localized for the requested language
		$units = self::loadLanguage( $code );

		if ( $units ) {
			return $units;
		}
		// Load missing time units from fallback languages
		$fallbacks = Language::getFallbacksFor( $code );
		foreach ( $fallbacks as $fallback ) {
			if ( $units ) {
				break;
			}
			// Get time units from a fallback language
			$units = self::loadLanguage( $fallback );
		}

		return $units;
	}

	/**
	 * Load time units localized for a particular language. Helper function for getUnits.
	 *
	 * @param string $code The language to return the list in
	 * @return array an associative array of time unit codes and localized time units
	 */
	private static function loadLanguage( $code ) {
		if ( !isset( self::$cache[$code] ) ) {
			/* Load override for wrong or missing entries in cldr */
			$override = __DIR__ . '/LocalNames/' . self::getOverrideFileName( $code );
			if ( Language::isValidBuiltInCode( $code ) && file_exists( $override ) ) {
				$timeUnits = false;

				require $override;

				if ( is_array( $timeUnits ) ) {
					self::$cache[$code] = $timeUnits;
				}
			}

			$filename = __DIR__ . '/CldrNames/' . self::getFileName( $code );
			if ( Language::isValidBuiltInCode( $code ) && file_exists( $filename ) ) {
				$timeUnits = false;
				require $filename;
				if ( is_array( $timeUnits ) ) {
					if ( isset( self::$cache[$code] ) ) {
						// Add to existing list of localized time units
						self::$cache[$code] = self::$cache[$code] + $timeUnits;
					} else {
						// No list exists, so create it
						self::$cache[$code] = $timeUnits;
					}
				}
			} else {
				wfDebug( __METHOD__ . ": Unable to load time units for $filename\n" );
			}
			if ( !isset( self::$cache[$code] ) ) {
				self::$cache[$code] = [];
			}
		}

		return self::$cache[$code];
	}

	/**
	 * Handler for GetHumanTimestamp hook.
	 * Converts the given time into a human-friendly relative format, for
	 * example, '6 days ago', 'In 10 months'.
	 *
	 * @param string &$output The output timestamp
	 * @param MWTimestamp $timestamp The current (user-adjusted) timestamp
	 * @param MWTimestamp $relativeTo The relative (user-adjusted) timestamp
	 * @param User $user User whose preferences are being used to make timestamp
	 * @param Language $lang Language that will be used to render the timestamp
	 * @return bool False means the timestamp was overridden so stop further
	 *     processing. True means the timestamp was not overridden.
	 */
	public static function onGetHumanTimestamp( &$output, $timestamp, $relativeTo, $user, $lang ) {

		// Map PHP's DateInterval property codes to CLDR unit names.
		$units = [
			's' => 'second',
			'i' => 'minute',
			'h' => 'hour',
			'd' => 'day',
			'm' => 'month',
			'y' => 'year',
		];

		// Get the difference between the two timestamps (as a DateInterval object).
		$timeDifference = $timestamp->diff( $relativeTo );

		// Figure out if the timestamp is in the future or the past.
		if ( $timeDifference->invert ) {
			$tense = 'future';
		} else {
			$tense = 'past';
		}

		// Figure out which unit (days, months, etc.) it makes sense to display
		// the timestamp in, and get the number of that unit to use.
		$unit = null;
		foreach ( $units as $code => $testUnit ) {
			$testNumber = $timeDifference->format( '%' . $code );
			if ( (int)$testNumber > 0 ) {
				$unit = $testUnit;
				$number = $testNumber;
			}
		}

		// If it occurred less than 1 second ago, output 'just now' message.
		if ( !$unit ) {
			$output = wfMessage( 'just-now' )->inLanguage( $lang )->text();

			return false;
		}

		// Get the CLDR time unit strings for the user's language.
		// If no strings are returned, abandon the timestamp override.
		$timeUnits = TimeUnits::getUnits( $lang->getCode() );
		if ( !$timeUnits ) {
			return true;
		}

		// Figure out which grammatical number to use.
		// If the template doesn't exist, fall back to 'other' as the default.
		$grammaticalNumber = $lang->getPluralRuleType( $number );
		$timeUnitKey = "{$unit}-{$tense}-{$grammaticalNumber}";
		if ( !isset( $timeUnits[$timeUnitKey] ) ) {
			$timeUnitKey = "{$unit}-{$tense}-other";
		}

		// Not all languages have translations for everything
		if ( !isset( $timeUnits[$timeUnitKey] ) ) {
			return true;
		}

		// Select the appropriate template for the timestamp.
		$timeUnit = $timeUnits[$timeUnitKey];
		// Replace the placeholder with the number.
		$output = str_replace( '{0}', $lang->formatNum( $number ), $timeUnit );

		return false;
	}
}
