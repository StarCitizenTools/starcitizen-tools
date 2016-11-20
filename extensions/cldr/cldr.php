<?php
/**
 * An extension which provides localised language names for other extensions.
 *
 * @file
 * @ingroup Extensions
 * @author Niklas Laxström
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'cldr' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['cldr'] = __DIR__ . '/i18n';
	/*wfWarn(
		'Deprecated PHP entry point used for cldr extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);*/
	return;
} else {
	die( 'This version of the cldr extension requires MediaWiki 1.25+' );
}
