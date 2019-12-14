<?php

/*
    Copyright 2012 Povilas Kanapickas <povilas@radix.lt>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Handler for SVG images that will be resized by the clients.
 *
 * @ingroup Media
 */
class NativeSvgHandler extends SvgHandler {

    function isEnabled() {
        return true;
    }

    function normaliseParams($image, &$params) {
        global $wgSVGMaxSize;
        if (!ImageHandler::normaliseParams($image, $params)) {
            return false;
        }
        return true;
    }

    function doTransform($image, $dstPath, $dstUrl, $params, $flags = 0) {
        if ( !$this->normaliseParams( $image, $params ) ) {
            return new TransformParameterError( $params );
        }

        return new SvgImage($image, $image->getURL(), $params['width'],
                            $params['height'], $image->getPath() );
    }

    function getThumbType($ext, $mime, $params = null) {
        return array( 'svg', 'image/svg+xml' );
    }
}
