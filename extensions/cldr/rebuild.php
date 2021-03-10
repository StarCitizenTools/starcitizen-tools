<?php

/**
 * Extract data from cldr XML.
 *
 * @author Niklas Laxström
 * @author Ryan Kaldari
 * @author Santhosh Thottingal
 * @author Sam Reed
 * @copyright Copyright © 2007-2015
 * @license GPL-2.0-or-later
 */

// Standard boilerplate to define $IP
if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$dir = __DIR__;
	$IP = "$dir/../..";
}
require_once "$IP/maintenance/Maintenance.php";

class CLDRRebuild extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Extract data from CLDR XML' );
		$this->addOption(
			'datadir', 'Directory containing CLDR data. Default is core/common/main',
			/* required */ false,
			/* param */ true
		);
		$this->addOption(
			'outputdir', 'Output directory. Default is current directory',
			/* required */ false,
			/* param */ true
		);

		$this->requireExtension( 'CLDR' );
	}

	public function execute() {
		$dir = __DIR__;

		$DATA = $this->getOption( 'datadir', "$dir/core/common/main" );
		$OUTPUT = $this->getOption( 'outputdir', $dir );

		if ( !file_exists( $DATA ) ) {
			$this->error( "CLDR data not found at $DATA\n", 1 );
		}

		// Get an array of all MediaWiki languages ( $wgLanguageNames + $wgExtraLanguageNames )
		$languages = Language::fetchLanguageNames();
		# hack to get pt-pt too
		$languages['pt-pt'] = 'Foo';
		ksort( $languages );

		foreach ( $languages as $code => $name ) {
			// Construct the correct name for the input file
			$codeParts = explode( '-', $code );
			if ( count( $codeParts ) > 1 ) {
				// ISO 15924 alpha-4 script code
				if ( strlen( $codeParts[1] ) === 4 ) {
					$codeParts[1] = ucfirst( $codeParts[1] );
				}

				// ISO 3166-1 alpha-2 country code
				if ( strlen( $codeParts[1] ) === 2 ) {
					$codeParts[2] = $codeParts[1];
					unset( $codeParts[1] );
				}
				if ( isset( $codeParts[2] ) && strlen( $codeParts[2] ) === 2 ) {
					$codeParts[2] = strtoupper( $codeParts[2] );
				}
				$codeCLDR = implode( '_', $codeParts );
			} else {
				$codeCLDR = $code;
			}
			$input = "$DATA/$codeCLDR.xml";

			// If the file exists, parse it, otherwise display an error
			if ( file_exists( $input ) ) {
				$outputFileName = Language::getFileName( 'CldrNames', getRealCode( $code ), '.php' );
				$p = new CLDRParser();
				$p->parse( $input, "$OUTPUT/CldrNames/$outputFileName" );
			} else {
				$this->output( "File $input not found\n" );
			}
		}

		// Now parse out what we want form the supplemental file
		$this->output( "Parsing Supplemental Data...\n" );
		// argh! If $DATA defaulted to something slightly more general in the
		// CLDR dump, this wouldn't have to be this way.
		$input = "$DATA/../supplemental/supplementalData.xml";
		if ( file_exists( $input ) ) {
			$p = new CLDRParser();
			$p->parse_supplemental( $input, "$OUTPUT/CldrSupplemental/Supplemental.php" );
		} else {
			$this->output( "File $input not found\n" );
		}
		$this->output( "Done parsing supplemental data.\n" );

		$this->output( "Parsing Currency Symbol Data...\n" );
		$p = new CLDRParser();
		$p->parse_currency_symbols( $DATA, "$OUTPUT/CldrCurrency/Symbols.php" );
		$this->output( "Done parsing currency symbols.\n" );
	}
}

/**
 * Get the code for the MediaWiki localisation,
 * these are same as the fallback.
 *
 * @param string $code
 * @return string
 */
function getRealCode( $code ) {
	$realCode = $code;
	if ( !strcmp( $code, 'kk' ) ) {
		$realCode = 'kk-cyrl';
	} elseif ( !strcmp( $code, 'ku' ) ) {
		$realCode = 'ku-latn';
	} elseif ( !strcmp( $code, 'sr' ) ) {
		$realCode = 'sr-ec';
	} elseif ( !strcmp( $code, 'tg' ) ) {
		$realCode = 'tg-cyrl';
	} elseif ( !strcmp( $code, 'zh' ) ) {
		$realCode = 'zh-hans';
	} elseif ( !strcmp( $code, 'pt' ) ) {
		$realCode = 'pt-br';
	} elseif ( !strcmp( $code, 'pt-pt' ) ) {
		$realCode = 'pt';
	} elseif ( !strcmp( $code, 'az-arab' ) ) {
		$realCode = 'azb';
	}

	return $realCode;
}

$maintClass = CLDRRebuild::class;
require_once RUN_MAINTENANCE_IF_MAIN;
