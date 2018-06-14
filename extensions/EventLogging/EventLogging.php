<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'EventLogging' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['EventLogging'] = __DIR__ . '/i18n/core';
	$wgMessagesDirs['JsonSchema'] = __DIR__ . '/i18n/jsonschema';
	$wgExtensionMessagesFiles['EventLoggingNamespaces'] = __DIR__ . '/EventLogging.namespaces.php';
	/* wfWarn(
		'Deprecated PHP entry point used for EventLogging extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the EventLogging extension requires MediaWiki 1.25+' );
}
