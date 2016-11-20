<?php
/**
 * Replace Text - a MediaWiki extension that provides a special page to
 * allow administrators to do a global string find-and-replace on all the
 * content pages of a wiki.
 *
 * https://www.mediawiki.org/wiki/Extension:Replace_Text
 *
 * The special page created is 'Special:ReplaceText', and it provides
 * a form to do a global search-and-replace, with the changes to every
 * page showing up as a wiki edit, with the administrator who performed
 * the replacement as the user, and an edit summary that looks like
 * "Text replace: 'search string' * to 'replacement string'".
 *
 * If the replacement string is blank, or is already found in the wiki,
 * the page provides a warning prompt to the user before doing the
 * replacement, since it is not easily reversible.
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ReplaceText' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ReplaceText'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['ReplaceTextAlias'] = __DIR__ . '/ReplaceText.alias.php';
	/* wfWarn(
	'Deprecated PHP entry point used for Replace Text extension. Please use wfLoadExtension instead, ' .
	'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
}

if ( !defined( 'MEDIAWIKI' ) ) { die(); }

define( 'REPLACE_TEXT_VERSION', '1.1.1' );

// credits
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Replace Text',
	'version' => REPLACE_TEXT_VERSION,
	'author' => array( 'Yaron Koren', 'Niklas Laxström', '...' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:Replace_Text',
	'descriptionmsg' => 'replacetext-desc',
	'license-name' => 'GPL-2.0+'
);

$wgMessagesDirs['ReplaceText'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['ReplaceText'] = __DIR__ . '/ReplaceText.i18n.php';
$wgExtensionMessagesFiles['ReplaceTextAlias'] = __DIR__ . '/ReplaceText.alias.php';
$wgJobClasses['replaceText'] = 'ReplaceTextJob';

// This extension uses its own permission type, 'replacetext'
$wgAvailableRights[] = 'replacetext';
$wgGroupPermissions['sysop']['replacetext'] = true;

$wgHooks['AdminLinks'][] = 'ReplaceTextHooks::addToAdminLinks';

$wgSpecialPages['ReplaceText'] = 'SpecialReplaceText';
$wgAutoloadClasses['ReplaceTextHooks'] = __DIR__ . '/ReplaceText.hooks.php';
$wgAutoloadClasses['SpecialReplaceText'] = __DIR__ . '/SpecialReplaceText.php';
$wgAutoloadClasses['ReplaceTextJob'] = __DIR__ . '/ReplaceTextJob.php';
$wgAutoloadClasses['ReplaceTextSearch'] = __DIR__ . '/ReplaceTextSearch.php';
