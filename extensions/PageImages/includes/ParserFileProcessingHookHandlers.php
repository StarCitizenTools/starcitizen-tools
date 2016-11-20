<?php

namespace PageImages\Hooks;

use File;
use ImageGalleryBase;
use Parser;
use Title;

/**
 * Handler for the "ParserMakeImageParams" and "AfterParserFetchFileAndTitle" hooks.
 *
 * @license WTFPL 2.0
 * @author Max Semenik
 * @author Thiemo Mättig
 */
class ParserFileProcessingHookHandlers {

	/**
	 * ParserMakeImageParams hook handler, saves extended information about images used on page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserMakeImageParams
	 *
	 * @param Title $title
	 * @param File|bool $file
	 * @param array[] &$params
	 * @param Parser $parser
	 */
	public static function onParserMakeImageParams(
		Title $title,
		$file,
		array &$params,
		Parser $parser
	) {
		$handler = new self();
		$handler->doParserMakeImageParams( $title, $file, $params, $parser );
	}

	/**
	 * AfterParserFetchFileAndTitle hook handler, saves information about gallery images
	 *
	 * @param Parser $parser
	 * @param ImageGalleryBase $gallery
	 */
	public static function onAfterParserFetchFileAndTitle( Parser $parser, ImageGalleryBase $gallery ) {
		$handler = new self();
		$handler->doAfterParserFetchFileAndTitle( $parser, $gallery );
	}

	/**
	 * @param Title $title
	 * @param File|bool $file
	 * @param array[] &$params
	 * @param Parser $parser
	 */
	public function doParserMakeImageParams(
		Title $title,
		$file,
		array &$params,
		Parser $parser
	) {
		$this->processFile( $parser, $file, $params );
	}

	/**
	 * @param Parser $parser
	 * @param ImageGalleryBase $gallery
	 */
	public function doAfterParserFetchFileAndTitle( Parser $parser, ImageGalleryBase $gallery ) {
		foreach ( $gallery->getImages() as $image ) {
			$this->processFile( $parser, $image[0], null );
		}
	}

	/**
	 * @param Parser $parser
	 * @param File|Title|null $file
	 * @param array[]|null $handlerParams
	 */
	private function processFile( Parser $parser, $file, $handlerParams ) {
		if ( !$file || !$this->processThisTitle( $parser->getTitle() ) ) {
			return;
		}

		if ( !( $file instanceof File ) ) {
			$file = wfFindFile( $file );
			if ( !$file ) {
				return;
			}
		}

		if ( is_array( $handlerParams ) ) {
			$myParams = $handlerParams;
			$this->calcWidth( $myParams, $file );
		} else {
			$myParams = array();
		}

		$myParams['filename'] = $file->getTitle()->getDBkey();
		$myParams['fullwidth'] = $file->getWidth();
		$myParams['fullheight'] = $file->getHeight();

		$out = $parser->getOutput();
		$pageImages = $out->getExtensionData( 'pageImages' ) ?: array();
		$pageImages[] = $myParams;
		$out->setExtensionData( 'pageImages', $pageImages );
	}

	/**
	 * Returns true if data for this title should be saved
	 *
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function processThisTitle( Title $title ) {
		global $wgPageImagesNamespaces;
		static $flipped = false;

		if ( $flipped === false ) {
			$flipped = array_flip( $wgPageImagesNamespaces );
		}

		return isset( $flipped[$title->getNamespace()] );
	}

	/**
	 * Estimates image size as displayed if not explicitly provided. We don't follow the core size
	 * calculation algorithm precisely because it's not required and editor's intentions are more
	 * important than the precise number.
	 *
	 * @param array[] &$params
	 * @param File $file
	 */
	private function calcWidth( array &$params, File $file ) {
		global $wgThumbLimits, $wgDefaultUserOptions;

		if ( isset( $params['handler']['width'] ) ) {
			return;
		}

		if ( isset( $params['handler']['height'] ) && $file->getHeight() > 0 ) {
			$params['handler']['width'] =
				$file->getWidth() * ( $params['handler']['height'] / $file->getHeight() );
		} elseif ( isset( $params['frame']['thumbnail'] )
			|| isset( $params['frame']['thumb'] )
			|| isset( $params['frame']['frameless'] ) )
		{
			$params['handler']['width'] = isset( $wgThumbLimits[$wgDefaultUserOptions['thumbsize']] )
				? $wgThumbLimits[$wgDefaultUserOptions['thumbsize']]
				: 250;
		} else {
			$params['handler']['width'] = $file->getWidth();
		}
	}

}
