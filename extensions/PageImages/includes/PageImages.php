<?php

/**
 * @license WTFPL 2.0
 * @author Max Semenik
 * @author Brad Jorsch
 * @author Thiemo Mättig
 */
class PageImages {

	/**
	 * Page property used to store the page image information
	 */
	const PROP_NAME = 'page_image';

	/**
	 * Returns page image for a given title
	 *
	 * @param Title $title: Title to get page image for
	 *
	 * @return File|bool
	 */
	public static function getPageImage( Title $title ) {
		$dbr = wfGetDB( DB_SLAVE );
		$name = $dbr->selectField( 'page_props',
			'pp_value',
			array( 'pp_page' => $title->getArticleID(), 'pp_propname' => self::PROP_NAME ),
			__METHOD__
		);

		$file = false;

		if ( $name ) {
			$file = wfFindFile( $name );
		}

		return $file;
	}

	/**
	 * InfoAction hook handler, adds the page image to the info=action page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 *
	 * @param IContextSource $context
	 * @param array[] &$pageInfo
	 */
	public static function onInfoAction( IContextSource $context, &$pageInfo ) {
		global $wgThumbLimits;

		$imageFile = self::getPageImage( $context->getTitle() );
		if ( !$imageFile ) {
			// The page has no image
			return;
		}

		$thumbSetting = $context->getUser()->getOption( 'thumbsize' );
		$thumbSize = $wgThumbLimits[$thumbSetting];

		$thumb = $imageFile->transform( array( 'width' => $thumbSize ) );
		if ( !$thumb ) {
			return;
		}
		$imageHtml = $thumb->toHtml(
			array(
				'alt' => $imageFile->getTitle()->getText(),
				'desc-link' => true,
			)
		);

		$pageInfo['header-basic'][] = array(
			$context->msg( 'pageimages-info-label' ),
			$imageHtml
		);
	}

	/**
	 * ApiOpenSearchSuggest hook handler, enhances ApiOpenSearch results with this extension's data
	 *
	 * @param array[] &$results
	 */
	public static function onApiOpenSearchSuggest( array &$results ) {
		global $wgPageImagesExpandOpenSearchXml;

		if ( !$wgPageImagesExpandOpenSearchXml || !count( $results ) ) {
			return;
		}

		$pageIds = array_keys( $results );
		$data = self::getImages( $pageIds, 50 );
		foreach ( $pageIds as $id ) {
			if ( isset( $data[$id]['thumbnail'] ) ) {
				$results[$id]['image'] = $data[$id]['thumbnail'];
			} else {
				$results[$id]['image'] = null;
			}
		}
	}

	/**
	 * SpecialMobileEditWatchlist::images hook handler, adds images to mobile watchlist A-Z view
	 *
	 * @param IContextSource $context
	 * @param array[] $watchlist
	 * @param array[] &$images
	 */
	public static function onSpecialMobileEditWatchlist_images( IContextSource $context, array $watchlist,
		array &$images
	) {
		$ids = array();
		foreach ( $watchlist as $ns => $pages ) {
			foreach ( array_keys( $pages ) as $dbKey ) {
				$title = Title::makeTitle( $ns, $dbKey );
				// Getting page ID here is safe because SpecialEditWatchlist::getWatchlistInfo()
				// uses LinkBatch
				$id = $title->getArticleID();
				if ( $id ) {
					$ids[$id] = $dbKey;
				}
			}
		}

		$data = self::getImages( array_keys( $ids ) );
		foreach ( $data as $id => $page ) {
			if ( isset( $page['pageimage'] ) ) {
				$images[ $page['ns'] ][ $ids[$id] ] = $page['pageimage'];
			}
		}
	}

	/**
	 * Returns image information for pages with given ids
	 *
	 * @param int[] $pageIds
	 * @param int $size
	 *
	 * @return array[]
	 */
	private static function getImages( array $pageIds, $size = 0 ) {
		$request = array(
			'action' => 'query',
			'prop' => 'pageimages',
			'piprop' => 'name',
			'pageids' => implode( '|', $pageIds ),
			'pilimit' => 'max',
		);

		if ( $size ) {
			$request['piprop'] = 'thumbnail';
			$request['pithumbsize'] = $size;
		}

		$api = new ApiMain( new FauxRequest( $request ) );
		$api->execute();

		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			return (array)$api->getResult()->getResultData( array( 'query', 'pages' ),
				array( 'Strip' => 'base' ) );
		} else {
			$data = $api->getResultData();
			if ( isset( $data['query']['pages'] ) ) {
				return $data['query']['pages'];
			}
			return array();
		}
	}

}
