<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'RelatedArticles' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['RelatedArticles'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['RelatedArticlesMagic'] = __DIR__ . '/RelatedArticles.i18n.magic.php';
	wfWarn(
		'Deprecated PHP entry point used for RelatedArticles extension. ' .
		'Please use wfLoadExtension instead, see ' .
		'https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the RelatedArticles extension requires MediaWiki 1.25+' );
}
