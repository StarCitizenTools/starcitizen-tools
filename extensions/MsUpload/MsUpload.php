<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'MsUpload' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['MsUpload'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for MsUpload extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the MsUpload extension requires MediaWiki 1.25+' );
}
