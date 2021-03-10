<?php
/**
 * Add a link to user's personal sandbox to personal tools menu.
 *
 * https://www.mediawiki.org/wiki/Extension:SandboxLink
 *
 * @file
 * @license MIT
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'SandboxLink' );
	$wgMessagesDirs['SandboxLink'] = __DIR__ . '/i18n';
} else {
	die( 'This version of the SandboxLink extension requires MediaWiki 1.25+' );
}
