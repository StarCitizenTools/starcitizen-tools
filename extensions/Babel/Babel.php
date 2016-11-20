<?php
/**
 * Babel Extension
 *
 * Adds a parser function to allow automated generation of a babel userbox
 * column with the ability to include custom templates.
 *
 * @file
 * @ingroup Extensions
 *
 * @link http://www.mediawiki.org/wiki/Extension:Babel
 *
 * @author Robert Leverington <robert@rhl.me.uk>
 * @copyright Copyright © 2008 - 2011 Robert Leverington.
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Babel' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$GLOBALS['wgMessagesDirs']['Babel'] = __DIR__ . '/i18n';
	$GLOBALS['wgExtensionMessagesFiles']['BabelMagic'] = __DIR__ . '/Babel.i18n.magic.php';
	/* wfWarn(
		'Deprecated PHP entry point used for Babel extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the Babel extension requires MediaWiki 1.25+' );
}
