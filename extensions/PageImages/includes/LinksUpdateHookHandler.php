<?php

namespace PageImages\Hooks;

use DerivativeContext;
use Exception;
use File;
use FormatMetadata;
use Http;
use LinksUpdate;
use PageImages;
use Title;

/**
 * Handler for the "LinksUpdate" hook.
 *
 * @license WTFPL 2.0
 * @author Max Semenik
 * @author Thiemo Mättig
 */
class LinksUpdateHookHandler {

	/**
	 * LinksUpdate hook handler, sets at most 2 page properties depending on images on page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinksUpdate
	 *
	 * @param LinksUpdate $linksUpdate
	 */
	public static function onLinksUpdate( LinksUpdate $linksUpdate ) {
		$handler = new self();
		$handler->doLinksUpdate( $linksUpdate );
	}

	/**
	 * @param LinksUpdate $linksUpdate
	 */
	public function doLinksUpdate( LinksUpdate $linksUpdate ) {
		$images = $linksUpdate->getParserOutput()->getExtensionData( 'pageImages' );

		if ( $images === null ) {
			return;
		}

		$scores = array();
		$counter = 0;

		foreach ( $images as $image ) {
			$fileName = $image['filename'];

			if ( !isset( $scores[$fileName] ) ) {
				$scores[$fileName] = -1;
			}

			$scores[$fileName] = max( $scores[$fileName], $this->getScore( $image, $counter++ ) );
		}

		$image = false;

		foreach ( $scores as $name => $score ) {
			if ( $score > 0 && ( !$image || $score > $scores[$image] ) ) {
				$image = $name;
			}
		}

		if ( $image ) {
			$linksUpdate->mProperties[PageImages::PROP_NAME] = $image;
		}
	}

	/**
	 * Returns score for image, the more the better, if it is less than zero,
	 * the image shouldn't be used for anything
	 *
	 * @param array $image Associative array describing an image
	 * @param int $position Image order on page
	 *
	 * @return int
	 */
	private function getScore( array $image, $position ) {
		global $wgPageImagesScores;

		$file = wfFindFile( $image['filename'] );
		if ( $file ) {
			$image += $this->getMetadata( $file );
		}

		if ( isset( $image['handler'] ) ) {
			// Standalone image
			$score = $this->scoreFromTable( $image['handler']['width'], $wgPageImagesScores['width'] );
		} else {
			// From gallery
			$score = $this->scoreFromTable( $image['fullwidth'], $wgPageImagesScores['galleryImageWidth'] );
		}

		if ( isset( $wgPageImagesScores['position'][$position] ) ) {
			$score += $wgPageImagesScores['position'][$position];
		}

		$ratio = intval( $this->getRatio( $image ) * 10 );
		$score += $this->scoreFromTable( $ratio, $wgPageImagesScores['ratio'] );

		if ( isset( $image['rights'] ) && isset( $wgPageImagesScores['rights'][$image['rights']] ) ) {
			$score += $wgPageImagesScores['rights'][$image['rights']];
		}

		$blacklist = $this->getBlacklist();
		if ( isset( $blacklist[$image['filename']] ) ) {
			$score = -1000;
		}

		return $score;
	}

	/**
	 * Returns score based on table of ranges
	 *
	 * @param int $value
	 * @param int[] $scores
	 *
	 * @return int
	 */
	private function scoreFromTable( $value, array $scores ) {
		$lastScore = 0;

		foreach ( $scores as $boundary => $score ) {
			if ( $value <= $boundary ) {
				return $score;
			}

			$lastScore = $score;
		}

		return $lastScore;
	}

	/**
	 * Return some file metadata (only what's relevant to page image scores).
	 *
	 * @param File $file
	 *
	 * @return string[]
	 */
	private function getMetadata( File $file ) {
		$format = new FormatMetadata;
		$context = new DerivativeContext( $format->getContext() );
		$format->setSingleLanguage( true ); // we don't care and it's slightly faster
		$context->setLanguage( 'en' ); // we don't care so avoid splitting the cache
		$format->setContext( $context );
		$extmetadata = $format->fetchExtendedMetadata( $file );
		$processedMetadata = array();

		// process copyright metadata from CommonsMetadata, if present
		if ( !empty( $extmetadata['NonFree']['value'] ) ) { // not '0' or unset
			$processedMetadata['rights'] = 'nonfree';
		}

		return $processedMetadata;
	}

	/**
	 * Returns width/height ratio of an image as displayed or 0 is not available
	 *
	 * @param array $image
	 *
	 * @return float|int
	 */
	private function getRatio( array $image ) {
		$width = $image['fullwidth'];
		$height = $image['fullheight'];

		if ( !$width || !$height ) {
			return 0;
		}

		return $width / $height;
	}

	/**
	 * Returns a list of images blacklisted from influencing this extension's output
	 *
	 * @throws Exception
	 * @return int[] Flipped associative array in format "image BDB key" => int
	 */
	private function getBlacklist() {
		global $wgPageImagesBlacklist, $wgPageImagesBlacklistExpiry, $wgMemc;
		static $list = false;

		if ( $list !== false ) {
			return $list;
		}

		$key = wfMemcKey( 'pageimages', 'blacklist' );
		$list = $wgMemc->get( $key );
		if ( $list !== false ) {
			return $list;
		}

		wfDebug( __METHOD__ . "(): cache miss\n" );
		$list = array();

		foreach ( $wgPageImagesBlacklist as $source ) {
			switch ( $source['type'] ) {
				case 'db':
					$list = array_merge( $list, $this->getDbBlacklist( $source['db'], $source['page'] ) );
					break;
				case 'url':
					$list = array_merge( $list, $this->getUrlBlacklist( $source['url'] ) );
					break;
				default:
					throw new Exception( __METHOD__ . "(): unrecognized image blacklist type '{$source['type']}'" );
			}
		}

		$list = array_flip( $list );
		$wgMemc->set( $key, $list, $wgPageImagesBlacklistExpiry );
		return $list;
	}

	/**
	 * Returns list of images linked by the given blacklist page
	 *
	 * @param string|bool $dbName Database name or false for current database
	 * @param string $page
	 *
	 * @return string[]
	 */
	private function getDbBlacklist( $dbName, $page ) {
		$dbr = wfGetDB( DB_SLAVE, array(), $dbName );
		$title = Title::newFromText( $page );
		$list = array();

		$id = $dbr->selectField(
			'page',
			'page_id',
			array( 'page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey() ),
			__METHOD__
		);

		if ( $id ) {
			$res = $dbr->select( 'pagelinks',
				'pl_title',
				array( 'pl_from' => $id, 'pl_namespace' => NS_FILE ),
				__METHOD__
			);
			foreach ( $res as $row ) {
				$list[] = $row->pl_title;
			}
		}

		return $list;
	}

	/**
	 * Returns list of images on given remote blacklist page.
	 * Not quite 100% bulletproof due to localised namespaces and so on.
	 * Though if you beat people if they add bad entries to the list... :)
	 *
	 * @param string $url
	 *
	 * @return string[]
	 */
	private function getUrlBlacklist( $url ) {
		global $wgFileExtensions;

		$list = array();
		$text = Http::get( $url, 3 );
		$regex = '/\[\[:([^|\#]*?\.(?:' . implode( '|', $wgFileExtensions ) . '))/i';

		if ( $text && preg_match_all( $regex, $text, $matches ) ) {
			foreach ( $matches[1] as $s ) {
				$t = Title::makeTitleSafe( NS_FILE, $s );

				if ( $t ) {
					$list[] = $t->getDBkey();
				}
			}
		}

		return $list;
	}

}
