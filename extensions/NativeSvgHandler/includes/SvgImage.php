<?php

class SvgImage extends MediaTransformOutput {

    function __construct( $file, $url, $width, $height, $path = false, $page = false ) {
        $this->file = $file;
        $this->url = $url;

        $this->width = round( $width ); //paranoid
        $this->height = round( $height ); //paranoid

        $this->path = $path;
        $this->page = $page;
    }

    function toHtml( $options = array() ) {
        if ( count( func_get_args() ) == 2 ) {
            throw new MWException( __METHOD__ .' called in the old style' );
        }

        $alt = empty( $options['alt'] ) ? '' : $options['alt'];

        $query = empty( $options['desc-query'] ) ? '' : $options['desc-query'];
        if ( !empty( $options['custom-url-link'] ) ) {
            $linkAttribs = array( 'href' => $options['custom-url-link'] );
            if ( !empty( $options['title'] ) ) {
                $linkAttribs['title'] = $options['title'];
            }
            if ( !empty( $options['custom-target-link'] ) ) {
                $linkAttribs['target'] = $options['custom-target-link'];
            } elseif ( !empty( $options['parser-extlink-target'] ) ) {
                $linkAttribs['target'] = $options['parser-extlink-target'];
            }
            if ( !empty( $options['parser-extlink-rel'] ) ) {
                $linkAttribs['rel'] = $options['parser-extlink-rel'];
            }
        } elseif ( !empty( $options['custom-title-link'] ) ) {
            $title = $options['custom-title-link'];
            $linkAttribs = array(
                'href' => $title->getLinkURL(),
                'title' => empty( $options['title'] ) ? $title->getFullText() : $options['title']
            );
        } elseif ( !empty( $options['desc-link'] ) ) {
            $linkAttribs = $this->getDescLinkAttribs( empty( $options['title'] ) ? null : $options['title'], $query );
        } elseif ( !empty( $options['file-link'] ) ) {
            $linkAttribs = array( 'href' => $this->file->getURL() );
        } else {
            $linkAttribs = false;
        }
        
        $attribs = array(
            'alt' => $alt,
            'src' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
        );
        if ( !empty( $options['valign'] ) ) {
            $attribs['style'] = "vertical-align: {$options['valign']}";
        }
        if ( !empty( $options['img-class'] ) ) {
            $attribs['class'] = $options['img-class'];
        }
        return $this->linkWrap( $linkAttribs, Xml::element( 'img', $attribs ) );
    }
}
