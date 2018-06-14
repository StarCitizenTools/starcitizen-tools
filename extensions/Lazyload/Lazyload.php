<?php
/**
 * Lazyload extension
 *
 * @file
 * @ingroup Extensions
 */

if ( function_exists( 'wfLoadExtension' ) ) {
    wfLoadExtension( 'Lazyload' );
    $wgMessagesDirs['Lazyload'] = __DIR__ . '/i18n';
    wfWarn(
        'Deprecated PHP entry point used for the Lazyload extension. ' .
        'Please use wfLoadExtension instead, ' .
        'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
    );
    return;
} else {
    die( 'This version of the Lazyload extension requires MediaWiki 1.25+' );
}