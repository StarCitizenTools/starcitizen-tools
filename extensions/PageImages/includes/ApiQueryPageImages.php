<?php

/**
 * Expose image information for a page via a new prop=pageimages API.
 *
 * @see https://www.mediawiki.org/wiki/Extension:PageImages#API
 *
 * @license WTFPL 2.0
 * @author Max Semenik
 * @author Ryan Kaldari
 * @author Yuvi Panda
 * @author Sam Smith
 */
class ApiQueryPageImages extends ApiQueryBase {

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'pi' );
	}

	/**
	 * Gets the set of titles to get page images for.
	 *
	 * Note well that the set of titles comprises the set of "good" titles
	 * (see {@see ApiPageSet::getGoodTitles}) union the set of "missing"
	 * titles in the File namespace that might correspond to foreign files.
	 * The latter are included because titles in the File namespace are
	 * expected to be found with {@see wfFindFile}.
	 *
	 * @return Title[] A map of page ID, which will be negative in the case
	 *  of missing titles in the File namespace, to Title object
	 */
	protected function getTitles() {
		$pageSet = $this->getPageSet();
		$titles = $pageSet->getGoodTitles();

		// T98791: We want foreign files to be treated like local files
		// in #execute, so include the set of missing filespace pages,
		// which were initially rejected in ApiPageSet#execute.
		$missingTitles = $pageSet->getMissingTitlesByNamespace();
		$missingFileTitles = isset( $missingTitles[NS_FILE] )
			? $missingTitles[NS_FILE]
			: array();

		// $titles is a map of ID to title object, which is ideal,
		// whereas $missingFileTitles is a map of title text to ID.
		$missingFileTitles = array_map( function ( $text ) {
			return Title::newFromText( $text, NS_FILE );
		}, array_flip( $missingFileTitles ) );

		// N.B. We can't use array_merge here as it doesn't preserve
		// keys.
		foreach ( $missingFileTitles as $id => $title ) {
			$titles[$id] = $title;
		}

		return $titles;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$prop = array_flip( $params['prop'] );
		if ( !count( $prop ) ) {
			$this->dieUsage( 'No properties selected', '_noprop' );
		}
		$size = $params['thumbsize'];
		$limit = $params['limit'];

		$allTitles = $this->getTitles();

		if ( count( $allTitles ) === 0 ) {
			return;
		}

		// Find the offset based on the continue param
		$offset = 0;
		if ( isset( $params['continue'] ) ) {
			// Get the position (not the key) of the 'continue' page within the
			// array of titles. Set this as the offset.
			$pageIds = array_keys( $allTitles );
			$offset = array_search( intval( $params['continue'] ), $pageIds );
			// If the 'continue' page wasn't found, die with error
			if ( !$offset ) {
				$this->dieUsage( 'Invalid continue param. You should pass the original value returned by the previous query' , '_badcontinue' );
			}
		}

		// Slice the part of the array we want to find images for
		$titles = array_slice( $allTitles, $offset, $limit, true );

		// Get the next item in the title array and use it to set the continue value
		$nextItemArray = array_slice( $allTitles, $offset + $limit, 1, true );
		if ( $nextItemArray ) {
			$this->setContinueEnumParameter( 'continue', key( $nextItemArray ) );
		}

		// Find any titles in the file namespace so we can handle those separately
		$filePageTitles = array();
		foreach ( $titles as $id => $title ) {
			if ( $title->inNamespace( NS_FILE ) ) {
				$filePageTitles[$id] = $title;
				unset( $titles[$id] );
			}
		}

		// Extract page images from the page_props table
		if ( count( $titles ) > 0 ) {
			$this->addTables( 'page_props' );
			$this->addFields( array( 'pp_page', 'pp_propname', 'pp_value' ) );
			$this->addWhere( array( 'pp_page' => array_keys( $titles ), 'pp_propname' => PageImages::PROP_NAME ) );

			$res = $this->select( __METHOD__ );

			foreach ( $res as $row ) {
				$pageId = $row->pp_page;
				$fileName = $row->pp_value;
				$this->setResultValues( $prop, $pageId, $fileName, $size );
			}
		} // End page props image extraction

		// Extract images from file namespace pages. In this case we just use
		// the file itself rather than searching for a page_image. (Bug 50252)
		foreach ( $filePageTitles as $pageId => $title ) {
			$fileName = $title->getDBkey();
			$this->setResultValues( $prop, $pageId, $fileName, $size );
		}
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	/**
	 * For a given page, set API return values for thumbnail and pageimage as needed
	 *
	 * @param array $prop The prop values from the API request
	 * @param int $pageId The ID of the page
	 * @param string $fileName The name of the file to transform
	 * @param int $size The thumbsize value from the API request
	 */
	protected function setResultValues( array $prop, $pageId, $fileName, $size ) {
		$vals = array();
		if ( isset( $prop['thumbnail'] ) || isset( $prop['original'] ) ) {
			$file = wfFindFile( $fileName );

			if ( isset( $prop['thumbnail'] ) ) {
				if ( $file ) {
					$thumb = $file->transform( array( 'width' => $size, 'height' => $size ) );
					if ( $thumb && $thumb->getUrl() ) {
						// You can request a thumb 1000x larger than the original
						// which (in case of bitmap original) will return a Thumb object
						// that will lie about its size but have the original as an image.
						$reportedSize = $thumb->fileIsSource() ? $file : $thumb;
						$vals['thumbnail'] = array(
							'source' => wfExpandUrl( $thumb->getUrl(), PROTO_CURRENT ),
							'width' => $reportedSize->getWidth(),
							'height' => $reportedSize->getHeight(),
						);
					}
				}
			}

			if ( isset( $prop['original'] ) ) {
				$original_url = wfExpandUrl( $file->getUrl(), PROTO_CURRENT );
				if ( isset( $vals['thumbnail'] ) ) {
					$vals['thumbnail']['original'] = $original_url;
				} else {
					$vals['thumbnail'] = array(
						'original' => $original_url,
					);
				}
			}
		}

		if ( isset( $prop['name'] ) ) {
			$vals['pageimage'] = $fileName;
		}

		$this->getResult()->addValue( array( 'query', 'pages' ), $pageId, $vals );
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Returns information about images on the page such as thumbnail and presence of photos.';
	}

	public function getAllowedParams() {
		return array(
			'prop' => array(
				ApiBase::PARAM_TYPE => array( 'thumbnail', 'name', 'original' ),
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_DFLT => 'thumbnail|name',
			),
			'thumbsize' => array(
				ApiBase::PARAM_TYPE => 'integer',
				APiBase::PARAM_DFLT => 50,
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 1,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 50,
				ApiBase::PARAM_MAX2 => 100,
			),
			'continue' => array(
				ApiBase::PARAM_TYPE => 'integer',
				/** @todo Once support for MediaWiki < 1.25 is dropped, just use ApiBase::PARAM_HELP_MSG directly */
				defined( 'ApiBase::PARAM_HELP_MSG' ) ? ApiBase::PARAM_HELP_MSG : '' => 'api-help-param-continue',
			),
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array(
			'prop' => array( 'What information to return',
				' thumbnail - URL and dimensions of image associated with page, if any',
				' name - image title',
				' original - URL to the image original',
			),
			'thumbsize' => 'Maximum thumbnail dimension',
			'limit' => 'Properties of how many pages to return',
			'continue' => 'When more results are available, use this to continue',
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=query&prop=pageimages&titles=Albert%20Einstein&pithumbsize=100' =>
				'apihelp-query+pageimages-example-1',
		);
	}

}
