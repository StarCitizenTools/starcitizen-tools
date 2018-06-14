<?php
/**
 * Namespace name definitions
 *
 * @file
 * @ingroup EventLogging
 * @ingroup Extensions
 */

$namespaceNames = [];

// For wikis without EventLogging installed.
if ( !defined( 'NS_SCHEMA' ) ) {
	define( 'NS_SCHEMA', 470 );
	define( 'NS_SCHEMA_TALK', 471 );
}

$namespaceNames['en'] = [
	NS_SCHEMA => 'Schema',
	NS_SCHEMA_TALK => 'Schema_talk',
];
