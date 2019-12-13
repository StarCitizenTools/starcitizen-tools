<?php
/**
 * Short Description – Adds the required magic word and API to mimic the short description on Wikimedia projects
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ShortDescription' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ShortDescription'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for Description2 extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the Short Description extension requires MediaWiki 1.31+' );
}
