<?php

class CCFilters {

	/**
	 * Hook: ChangesListSpecialPageQuery
	 */
	public static function user(
		$name,
		&$tables,
		&$fields,
		&$conds,
		&$query_options,
		&$join_conds,
		FormOptions $opts
	) {
		global $wgRequest, $wgCCUserFilter;

		if ( !$wgCCUserFilter ) {
			return;
		}

		$opts->add( 'users', '' );
		$users = $wgRequest->getVal( 'users' );
		if ( $users === null ) {
			return;
		}

		$idfilters = [];
		$userArr = explode( '|', $users );
		foreach ( $userArr as $u ) {
			$id = User::idFromName( $u );
			if ( $id !== null ) {
				$idfilters[] = $id;
			}
		}
		if ( count( $idfilters ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			$conds[] = 'rc_user IN (' . $dbr->makeList( $idfilters ) . ')';
			$opts->setValue( 'users', $users );
		}
	}

	/**
	 * Hook: SpecialRecentChangesPanel
	 */
	public static function userForm( &$items, FormOptions $opts ) {
		global $wgRequest, $wgCCUserFilter;

		if ( !$wgCCUserFilter ) {
			return;
		}

		$opts->consumeValue( 'users' );

		$default = $wgRequest->getVal( 'users', '' );
		$items['users'] = Xml::inputLabelSep(
			wfMessage( 'cleanchanges-users' )->text(),
			'users',
			'mw-users',
			40,
			$default
		);
	}

	/**
	 * Hook: ChangesListSpecialPageQuery
	 */
	public static function trailer(
		$name,
		&$tables,
		&$fields,
		&$conds,
		&$query_options,
		&$join_conds,
		FormOptions $opts
	) {
		global $wgRequest, $wgCCTrailerFilter;

		if ( !$wgCCTrailerFilter ) {
			return;
		}

		$opts->add( 'trailer', '' );
		$trailer = $wgRequest->getVal( 'trailer' );
		if ( $trailer === null ) {
			return;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$conds[] = 'rc_title ' . $dbr->buildLike( $dbr->anyString(), $trailer );
		$opts->setValue( 'trailer', $trailer );
	}

	/**
	 * Hook: SpecialRecentChangesPanel
	 */
	public static function trailerForm( &$items, FormOptions $opts ) {
		/**
		 * @var Language $wgLang
		 */
		global $wgLang, $wgRequest, $wgCCTrailerFilter;

		if ( !$wgCCTrailerFilter ) {
			return;
		}

		$opts->consumeValue( 'trailer' );

		$default = $wgRequest->getVal( 'trailer', '' );

		if ( is_callable( [ 'LanguageNames', 'getNames' ] ) ) {
			$languages = LanguageNames::getNames( $wgLang->getCode(),
				LanguageNames::FALLBACK_NORMAL,
				LanguageNames::LIST_MW
			);
		} else {
			$languages = Language::fetchLanguageNames( null, 'mw' );
		}
		ksort( $languages );
		$options = Xml::option( wfMessage( 'cleanchanges-language-na' )->text(), '', $default === '' );
		foreach ( $languages as $code => $name ) {
			$selected = ( "/$code" === $default );
			$options .= Xml::option( "$code - $name", "/$code", $selected ) . "\n";
		}
		$str =
		Xml::openElement( 'select', [
			'name' => 'trailer',
			'class' => 'mw-language-selector',
			'id' => 'sp-rc-language',
		] ) .
		$options .
		Xml::closeElement( 'select' );

		$items['tailer'] = [ wfMessage( 'cleanchanges-language' )->escaped(), $str ];
	}
}
