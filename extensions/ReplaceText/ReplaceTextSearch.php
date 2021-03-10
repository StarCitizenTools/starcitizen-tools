<?php

class ReplaceTextSearch {
	public static function doSearchQuery( $search, $namespaces, $category, $prefix, $use_regex = false ) {
		$dbr = wfGetDB( DB_SLAVE );
		$tables = array( 'page', 'revision', 'text' );
		$vars = array( 'page_id', 'page_namespace', 'page_title', 'old_text' );
		if ( $use_regex ) {
			$comparisonCond = self::regexCond( $dbr, 'old_text', $search );
		} else {
			$any = $dbr->anyString();
			$comparisonCond = 'old_text ' . $dbr->buildLike( $any, $search, $any );
		}
		$conds = array(
			$comparisonCond,
			'page_namespace' => $namespaces,
			'rev_id = page_latest',
			'rev_text_id = old_id'
		);

		self::categoryCondition( $category, $tables, $conds );
		self::prefixCondition( $prefix, $conds );
		$sort = array( 'ORDER BY' => 'page_namespace, page_title' );

		return $dbr->select( $tables, $vars, $conds, __METHOD__ , $sort );
	}

	public static function categoryCondition( $category, &$tables, &$conds ) {
		if ( strval( $category ) !== '' ) {
			$category = Title::newFromText( $category )->getDbKey();
			$tables[] = 'categorylinks';
			$conds[] = 'page_id = cl_from';
			$conds['cl_to'] = $category;
		}
	}

	public static function prefixCondition( $prefix, &$conds ) {
		if ( strval( $prefix ) === '' ) {
			return;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$title = Title::newFromText( $prefix );
		if ( !is_null( $title ) ) {
			$prefix = $title->getDbKey();
		}
		$any = $dbr->anyString();
		$conds[] = 'page_title ' . $dbr->buildLike( $prefix, $any );
	}

	public static function regexCond( $dbr, $column, $regex ) {
		if ( $dbr instanceof DatabasePostgres ) {
			$op = '~';
		} else {
			$op = 'REGEXP';
		}
		return "$column $op " . $dbr->addQuotes( $regex );
	}
}