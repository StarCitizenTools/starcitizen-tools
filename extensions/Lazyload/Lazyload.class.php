<?php
/**
 * Lazyload class
 */

class Lazyload {
    public static function LinkerMakeExternalImage(&$url, &$alt, &$img) {
        global $wgRequest;
        if (defined('MW_API') && $wgRequest->getVal('action') == 'parse') return true;
        $url = preg_replace('/^(http|https):/', '', $url);
        $img = '<img class="external-image" alt="' . htmlentities($alt) . '" data-url="' . htmlentities($url) . '">&nbsp;</img>';
        return false;
    }

    public static function ThumbnailBeforeProduceHTML($thumb, &$attribs, &$linkAttribs) {
        global $wgRequest;
        if (defined('MW_API') && $wgRequest->getVal('action') == 'parse') return true;
        $attribs['data-url'] = $attribs['src'];
        $attribs['src'] = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        if (isset($attribs['srcset'])) {
            $attribs['data-srcset'] = $attribs['srcset'];
            unset($attribs['srcset']);
        }
        return true;
    }

    public static function BeforePageDisplay($out, $skin) {
        $out->addModules( 'ext.lazyload' );
        return true;
    }
}
