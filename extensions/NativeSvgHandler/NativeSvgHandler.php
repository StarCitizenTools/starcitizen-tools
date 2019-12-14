<?php
/**
 * NativeSvgHandler – Serves SVG images directly to clients
 */

if ( function_exists( 'wfLoadExtension' ) ) {
    wfLoadExtension( 'NativeSvgHandler' );
    // Keep i18n globals so mergeMessageFileList.php doesn't break
    $wgMessagesDirs['NativeSvgHandler'] = __DIR__ . '/i18n';
    wfWarn(
        'Deprecated PHP entry point used for NativeSvgHandler extension. ' .
        'Please use wfLoadExtension instead, ' .
        'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
    );
    return;
} else {
    die( 'This version of the NativeSvgHandler extension requires MediaWiki 1.31+' );
}
