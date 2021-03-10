<?php
/**
 * DisambiguationPageLinks SpecialPage for Disambiguator extension
 * This page lists all pages that link to disambiguation pages.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialDisambiguationPageLinks extends QueryPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'DisambiguationPageLinks' );
	}

	function isExpensive() {
		return true;
	}

	function isSyndicated() {
		return false;
	}

	function getQueryInfo() {
		return [
			'tables' => [
				'p1' => 'page',
				'p2' => 'page',
				'pagelinks',
				'page_props'
			],
			// The fields we are selecting correspond with fields in the
			// querycachetwo table so that the results are cachable.
			'fields' => [
				'value' => 'pl_from',
				'namespace' => 'p2.page_namespace',
				'title' => 'p2.page_title',
				'to_namespace' => 'p1.page_namespace',
				'to_title' => 'p1.page_title',
			],
			'conds' => [
				'p1.page_id = pp_page',
				'pp_propname' => 'disambiguation',
				'pl_namespace = p1.page_namespace',
				'pl_title = p1.page_title',
				'p2.page_id = pl_from',
				'p2.page_namespace' => MWNamespace::getContentNamespaces(),
				'p2.page_is_redirect != 1'
			]
		];
	}

	/**
	 * Order the results by ID of the linking page (value), namespace of the
	 * linked page, and title of the linked page. This function only affects
	 * ordering when not using $wgMiserMode.
	 *
	 * @return array
	 */
	function getOrderFields() {
		return [ 'value', 'to_namespace', 'to_title' ];
	}

	function sortDescending() {
		return false;
	}

	function formatResult( $skin, $result ) {
		$fromTitle = Title::newFromID( $result->value );
		$toTitle = Title::newFromText( $result->to_title, $result->to_namespace );

		$linkRenderer = $this->getLinkRenderer();
		$from = $linkRenderer->makeKnownLink( $fromTitle );
		$edit = $linkRenderer->makeLink(
			$fromTitle,
			$this->msg( 'parentheses', $this->msg( 'editlink' )->text() )->text(),
			[],
			[ 'redirect' => 'no', 'action' => 'edit' ]
		);
		$arr = $this->getLanguage()->getArrow();
		$to = $linkRenderer->makeKnownLink( $toTitle );

		return "$from $edit $arr $to";
	}

	protected function getGroupName() {
		return 'pages';
	}

	/**
	 * Clear the cache and save new results
	 *
	 * @param int|bool $limit Limit for SQL statement
	 * @param bool $ignoreErrors Whether to ignore database errors
	 * @throws DBError|Exception
	 * @return bool|int
	 */
	function recache( $limit, $ignoreErrors = true ) {
		if ( !$this->isCacheable() ) {
			return 0;
		}

		$fname = get_class( $this ) . '::recache';
		$dbw = wfGetDB( DB_MASTER );
		if ( !$dbw ) {
			return false;
		}

		try {
			// Do query
			$res = $this->reallyDoQuery( $limit, false );
			$num = false;
			if ( $res ) {
				$num = $res->numRows();
				// Fetch results
				$vals = [];
				foreach ( $res as $row ) {
					if ( isset( $row->value ) ) {
						if ( $this->usesTimestamps() ) {
							$value = wfTimestamp( TS_UNIX, $row->value );
						} else {
							$value = intval( $row->value );
						}
					} else {
						$value = 0;
					}

					$vals[] = [
						'qcc_type' => $this->getName(),
						'qcc_value' => $value,
						'qcc_namespace' => $row->namespace,
						'qcc_title' => $row->title,
						'qcc_namespacetwo' => $row->to_namespace,
						'qcc_titletwo' => $row->to_title,
					];
				}

				$dbw->startAtomic( __METHOD__ );
				// Clear out any old cached data
				$dbw->delete( 'querycachetwo', [ 'qcc_type' => $this->getName() ], $fname );
				// Save results into the querycachetwo table on the master
				if ( count( $vals ) ) {
					$dbw->insert( 'querycachetwo', $vals, __METHOD__ );
				}
				// Update the querycache_info record for the page
				$dbw->delete( 'querycache_info', [ 'qci_type' => $this->getName() ], $fname );
				$dbw->insert(
					'querycache_info',
					[ 'qci_type' => $this->getName(), 'qci_timestamp' => $dbw->timestamp() ],
					$fname
				);
				$dbw->endAtomic( __METHOD__ );
			}
		} catch ( DBError $e ) {
			if ( !$ignoreErrors ) {
				throw $e; // Report query error
			}
			$num = false; // Set result to false to indicate error
		}

		return $num;
	}

	/**
	 * Fetch the query results from the query cache
	 *
	 * @param int|bool $limit Numerical limit or false for no limit
	 * @param int|bool $offset Numerical offset or false for no offset
	 * @return ResultWrapper
	 */
	function fetchFromCache( $limit, $offset = false ) {
		$dbr = wfGetDB( DB_REPLICA );
		$options = [];
		if ( $limit !== false ) {
			$options['LIMIT'] = intval( $limit );
		}
		if ( $offset !== false ) {
			$options['OFFSET'] = intval( $offset );
		}
		// Set sort order. This should match the ordering in getOrderFields().
		if ( $this->sortDescending() ) {
			$options['ORDER BY'] = 'qcc_value, qcc_namespacetwo, qcc_titletwo DESC';
		} else {
			$options['ORDER BY'] = 'qcc_value, qcc_namespacetwo, qcc_titletwo ASC';
		}
		$res = $dbr->select(
			'querycachetwo',
			[
				'value' => 'qcc_value',
				'namespace' => 'qcc_namespace',
				'title' => 'qcc_title',
				'to_namespace' => 'qcc_namespacetwo',
				'to_title' => 'qcc_titletwo',
			],
			[ 'qcc_type' => $this->getName() ],
			__METHOD__,
			$options
		);
		return $res;
	}

}
