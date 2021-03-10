<?php
/**
 * txt2cdb: Converts the text file of ISO codes to a constant database.
 *
 * Usage: php txt2cdb.php
 */

$dir = __DIR__;
$IP = "$dir/../..";

require_once "$IP/maintenance/commandLine.inc";

$dir = __DIR__;
$names = "$dir/names.cdb";
$codes = "$dir/codes.cdb";
$fr = fopen( "$dir/codes.txt", 'r' );

try {
	$names = Cdb\Writer::open( $names );
	$codes = Cdb\Writer::open( $codes );

	while ( true ) {
		$line = fgets( $fr );
		if ( !$line ) {
			break;
		}

		// Format is code1 code2 "language name"
		$line = explode( ' ', $line, 3 );
		$iso1 = trim( $line[0] );
		$iso3 = trim( $line[1] );
		// Strip quotes
		$name = substr( trim( $line[2] ), 1, -1 );
		if ( $iso1 !== '-' ) {
			$codes->set( $iso1, $iso1 );
			if ( $iso3 !== '-' ) {
				$codes->set( $iso3, $iso1 );
			}
			$names->set( $iso1, $name );
			$names->set( $iso3, $name );
		} elseif ( $iso3 !== '-' ) {
			$codes->set( $iso3, $iso3 );
			$names->set( $iso3, $name );
		}
	}
} catch ( Cdb\Exception $e ) {
	throw new Exception( $e->getMessage() );
}

fclose( $fr );
