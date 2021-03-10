<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Disambiguator' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Disambiguator'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['DisambiguatorAlias'] = __DIR__ . '/Disambiguator.i18n.alias.php';
	$wgExtensionMessagesFiles['DisambiguatorMagic'] = __DIR__ . '/Disambiguator.i18n.magic.php';
	/* wfWarn(
		'Deprecated PHP entry point used for Disambiguator extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the Disambiguator extension requires MediaWiki 1.25+' );
}
