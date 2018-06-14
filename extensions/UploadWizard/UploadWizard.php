<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'UploadWizard' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['UploadWizard'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['UploadWizardAlias'] = __DIR__ . '/UploadWizard.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for UploadWizard extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the UploadWizard extension requires MediaWiki 1.25+' );
}
