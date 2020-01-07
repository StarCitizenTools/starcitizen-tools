<?php
/**
 * Code for language code and name processing.
 *
 * @file
 * @author Robert Leverington
 * @license GPL-2.0-or-later
 */

/**
 * Handle language code and name processing for the Babel extension, it can also
 * be used by other extension which need such functionality.
 */
class BabelLanguageCodes {

	private static $mapToMediaWikiCodeCache = null;
	/**
	 * Map BCP 47 codes or old deprecated internal codes to current MediaWiki
	 * internal codes (which may not be standard BCP 47 codes).
	 *
	 * @param string $code Code to try and get an internal code for
	 * @return string|bool Language code, or false if code is not mapped
	 */
	private static function mapToMediaWikiCode( $code ) {
		if ( !self::$mapToMediaWikiCodeCache ) {
			self::$mapToMediaWikiCodeCache = [];
			// Is the code a proper BCP 47 code for one of MediaWiki's nonstandard codes?
			// If so, return the internal MediaWiki code.
			if ( method_exists( 'LanguageCode', 'getNonstandardLanguageCodeMapping' ) ) {
				$mapping = LanguageCode::getNonstandardLanguageCodeMapping();
				foreach ( $mapping as $mwCode => $bcp47code ) {
					// Careful, because the nonstandardlanguagecodemapping
					// also maps deprecated codes to bcp-47 equivalents; we
					// don't want to return a deprecated code.
					self::$mapToMediaWikiCodeCache[ strtolower( $bcp47code ) ] =
						LanguageCode::replaceDeprecatedCodes( $mwCode );
				}
			}
			// Is the code one of MediaWiki's legacy fake codes? If so, return the modern
			// equivalent code (T101086)
			if ( method_exists( 'LanguageCode', 'getDeprecatedCodeMapping' ) ) {
				$mapping = LanguageCode::getDeprecatedCodeMapping();
				foreach ( $mapping as $deprecatedCode => $mwCode ) {
					self::$mapToMediaWikiCodeCache[ strtolower( $deprecatedCode ) ] =
						$mwCode;
				}
			}
		}
		if ( isset( self::$mapToMediaWikiCodeCache[ strtolower( $code ) ] ) ) {
			return self::$mapToMediaWikiCodeCache[ strtolower( $code ) ];
		}
		return false;
	}

	/**
	 * Takes a language code, and attempt to obtain a better variant of it,
	 * checks the MediaWiki language codes for a match, otherwise checks the
	 * Babel language codes CDB (preferring ISO 639-1 over ISO 639-3).
	 *
	 * @param string $code Code to try and get a "better" code for.
	 * @return string|bool Mediawiki-internal language code, or false
	 *   for invalid language code.
	 */
	public static function getCode( $code ) {
		// Map BCP 47 codes and/or deprecated codes to internal MediaWiki codes
		$mediawiki = self::mapToMediaWikiCode( $code );
		if ( $mediawiki !== false ) {
			return $mediawiki;
		}

		// Is the code known to MediaWiki?
		$mediawiki = Language::fetchLanguageName( $code );
		if ( $mediawiki !== '' ) {
			return strtolower( $code );
		}

		// Otherwise, fall back to the ISO 639 codes database
		$codes = false;
		try {
			$codesCdb = Cdb\Reader::open( __DIR__ . '/../codes.cdb' );
			$codes = $codesCdb->get( $code );
		} catch ( Cdb\Exception $e ) {
			wfDebug( __METHOD__ . ": CdbException caught, error message was "
				. $e->getMessage() );
		}

		return $codes;
	}

	/**
	 * Get the normalised IETF language tag.
	 * @param string $code The language code.
	 * @deprecated This provides backward compatibility; replace with
	 *   \LanguageCode::bcp47() once MW 1.30 is no longer supported.
	 */
	public static function bcp47( $code ) {
		if ( !is_callable( [ 'LanguageCode', 'bcp47' ] ) ) {
			return wfBCP47( $code );
		}
		return LanguageCode::bcp47( $code );
	}

	/**
	 * Take a code as input, and search a language name for it in
	 * a given language via Language::fetchLanguageNames() or
	 * else via the Babel language names CDB
	 *
	 * @param string $code Code to get name for.
	 * @param string|null $language Code of language to attempt to get name in,
	 *  defaults to language of code.
	 * @return string|bool Name of language, or false for invalid language code.
	 */
	public static function getName( $code, $language = null ) {
		// Get correct code, even though it should already be correct.
		$code = self::getCode( $code );
		if ( $code === false ) {
			return false;
		}
		$code = strtolower( $code );

		$language = $language === null ? $code : $language;
		$names = Language::fetchLanguageNames( $language, 'all' );
		if ( isset( $names[$code] ) ) {
			return $names[$code];
		}

		$codes = false;
		try {
			$namesCdb = Cdb\Reader::open( __DIR__ . '/../names.cdb' );
			$codes = $namesCdb->get( $code );
		} catch ( Cdb\Exception $e ) {
			wfDebug( __METHOD__ . ": CdbException caught, error message was "
				. $e->getMessage() );
		}

		return $codes;
	}

	/**
	 * Return an appropriate category name, given a MediaWiki-internal
	 * language code.  MediaWiki-internal codes are all-lowercase, but
	 * historically our category codes have been partially uppercase
	 * in the style of BCP 47.  Eventually we should probably use true
	 * BCP 47 for category names, but historically we've had internal
	 * codes like `simple` which we don't want to rename to `en-simple`
	 * quite yet.
	 *
	 * @param string $code MediaWiki-internal code.
	 * @return string A backwards-compatible category name for this code.
	 * @since 1.32
	 */
	public static function getCategoryCode( $code ) {
		if ( strpos( $code, '-' ) !== false ) {
			return self::bcp47( $code );
		}

		return $code;
	}
}
