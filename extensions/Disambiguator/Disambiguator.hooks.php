<?php
/**
 * Hooks for Disambiguator extension
 *
 * @file
 * @ingroup Extensions
 */

class DisambiguatorHooks {
	/**
	 * @param array &$doubleUnderscoreIDs
	 */
	public static function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ) {
		$doubleUnderscoreIDs[] = 'disambiguation';
	}

	/**
	 * Add the Disambiguator special pages to the list of QueryPages. This
	 * allows direct access via the API.
	 * @param array &$queryPages
	 */
	public static function onwgQueryPages( &$queryPages ) {
		$queryPages[] = [ 'SpecialDisambiguationPages', 'DisambiguationPages' ];
		$queryPages[] = [ 'SpecialDisambiguationPageLinks', 'DisambiguationPageLinks' ];
	}

	/**
	 * Modify query parameters to ignore disambiguation pages
	 * @param array &$tables
	 * @param array &$conds
	 * @param array &$joinConds
	 */
	private static function excludeDisambiguationPages( &$tables, &$conds, &$joinConds ) {
		$tables[] = 'page_props';
		$conds['pp_page'] = null;
		$joinConds['page_props'] = [
			'LEFT JOIN', [ 'page_id = pp_page', 'pp_propname' => 'disambiguation' ]
		];
	}

	/**
	 * Modify the Special:LonelyPages query to ignore disambiguation pages
	 * @param array &$tables
	 * @param array &$conds
	 * @param array &$joinConds
	 */
	public static function onLonelyPagesQuery( &$tables, &$conds, &$joinConds ) {
		self::excludeDisambiguationPages( $tables, $conds, $joinConds );
	}

	/**
	 * Modify the Special:ShortPages query to ignore disambiguation pages
	 * @param array &$tables
	 * @param array &$conds
	 * @param array &$joinConds
	 * @param array &$options
	 */
	public static function onShortPagesQuery( &$tables, &$conds, &$joinConds, &$options ) {
		self::excludeDisambiguationPages( $tables, $conds, $joinConds );
	}

	/**
	 * Modify the Special:Random query to ignore disambiguation pages
	 * @param array &$tables
	 * @param array &$conds
	 * @param array &$joinConds
	 */
	public static function onRandomPageQuery( &$tables, &$conds, &$joinConds ) {
		self::excludeDisambiguationPages( $tables, $conds, $joinConds );
	}

	/**
	 * Convenience function for testing whether or not a page is a disambiguation page
	 * @param Title $title object of a page
	 * @param bool $includeRedirects Whether to consider redirects to disambiguations as
	 *   disambiguations.
	 * @return bool
	 */
	public static function isDisambiguationPage( Title $title, $includeRedirects = true ) {
		$res = static::filterDisambiguationPageIds(
			[ $title->getArticleID() ], $includeRedirects );
		return (bool)count( $res );
	}

	/**
	 * Convenience function for testing whether or not pages are disambiguation pages
	 * @param int[] $pageIds
	 * @param bool $includeRedirects Whether to consider redirects to disambiguations as
	 *   disambiguations.
	 * @return int[] The page ids corresponding to pages that are disambiguations
	 */
	private static function filterDisambiguationPageIds(
		array $pageIds, $includeRedirects = true
	) {
		// Don't needlessly check non-existent and special pages
		$pageIds = array_filter(
			$pageIds,
			function ( $id ) {
				return $id > 0;
			}
		);

		$output = [];
		if ( $pageIds ) {
			$dbr = wfGetDB( DB_REPLICA );

			$redirects = [];
			if ( $includeRedirects ) {
				// resolve redirects
				$res = $dbr->select(
					[ 'page', 'redirect' ],
					[ 'page_id', 'rd_from' ],
					[ 'rd_from' => $pageIds ],
					__METHOD__,
					[],
					[ 'page' => [ 'INNER JOIN', [
						'rd_namespace=page_namespace',
						'rd_title=page_title'
					] ] ]
				);

				foreach ( $res as $row ) {
					// Key is the destination page ID, value is the source page ID
					$redirects[$row->page_id] = $row->rd_from;
				}
			}
			$pageIdsWithRedirects = array_merge( array_keys( $redirects ),
				array_diff( $pageIds, array_values( $redirects ) ) );
			$res = $dbr->select(
				'page_props',
				'pp_page',
				[ 'pp_page' => $pageIdsWithRedirects, 'pp_propname' => 'disambiguation' ],
				__METHOD__
			);

			foreach ( $res as $row ) {
				$disambiguationPageId = $row->pp_page;
				if ( array_key_exists( $disambiguationPageId, $redirects ) ) {
					$output[] = $redirects[$disambiguationPageId];
				}
				if ( in_array( $disambiguationPageId, $pageIds ) ) {
					$output[] = $disambiguationPageId;
				}
			}
		}

		return $output;
	}

	/**
	 * Add 'mw-disambig' CSS class to links to disambiguation pages.
	 * @param array $pageIdToDbKey Prefixed DB keys of the pages linked to, indexed by page_id
	 * @param array &$colours CSS classes, indexed by prefixed DB keys
	 */
	public static function onGetLinkColours( $pageIdToDbKey, &$colours ) {
		global $wgDisambiguatorIndicateLinks;
		if ( !$wgDisambiguatorIndicateLinks ) {
			return;
		}

		$pageIds = static::filterDisambiguationPageIds( array_keys( $pageIdToDbKey ) );
		foreach ( $pageIds as $pageId ) {
			if ( isset( $colours[ $pageIdToDbKey[$pageId] ] ) ) {
				$colours[ $pageIdToDbKey[$pageId] ] .= ' mw-disambig';
			} else {
				$colours[ $pageIdToDbKey[$pageId] ] = 'mw-disambig';
			}
		}
	}
}
