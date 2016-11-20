<?php
/**
 * tab2txt: Converts the original tabulated data file of ISO codes to a three
 * column text file (ISO 639-1, ISO 639-3, Natural Name).
 *
 * Usage: <tab file> | php tab2txt.php > codes.txt
 */

$dir = __DIR__;
$IP = "$dir/../..";
require_once "$IP/maintenance/commandLine.inc";

$fr = fopen( 'php://stdin', 'r' );
$fw = fopen( 'php://stdout', 'w' );

// Read and discard header line.
fgets( $fr );

while ( true ) {
	$line = fgets( $fr );
	if ( !$line ) {
		break;
	}

	$line = explode( "\t", $line );
	$iso1 = trim( $line[3] );
	if ( $iso1 === '' ) {
		$iso1 = '-';
	}
	$iso3 = trim( $line[0] );
	$name = $line[6];
	fwrite( $fw, "$iso1 $iso3 \"$name\"\n" );
}
fclose( $fr );
fclose( $fw );
