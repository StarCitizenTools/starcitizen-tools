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
	/**
	 * Takes a language code, and attempt to obtain a better variant of it,
	 * checks the MediaWiki language codes for a match, otherwise checks the
	 * Babel language codes CDB (preferring ISO 639-1 over ISO 639-3).
	 *
	 * @param string $code Code to try and get a "better" code for.
	 * @return string|bool Language code, or false for invalid language code.
	 */
	public static function getCode( $code ) {
		// Is the code one of MediaWiki's legacy fake codes? If so, return the modern
		// equivalent code (T101086)
		if ( method_exists( 'LanguageCode', 'getDeprecatedCodeMapping' ) ) {
			$mapping = LanguageCode::getDeprecatedCodeMapping();
			if ( isset( $mapping[strtolower( $code )] ) ) {
				return $mapping[strtolower( $code )];
			}
		}

		// Is the code known to MediaWiki?
		$mediawiki = Language::fetchLanguageName( $code );
		if ( $mediawiki !== '' ) {
			return $code;
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
	 * Take a code as input, and search a language name for it in
	 * a given language via Language::fetchLanguageNames() or
	 * else via the Babel language names CDB
	 *
	 * @param string $code Code to get name for.
	 * @param string $language Code of language to attempt to get name in,
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
}
