<?php

class SpecialCheckUserLog extends SpecialPage {
	/**
	 * @var string $target
	 */
	protected $target;

	public function __construct() {
		parent::__construct( 'CheckUserLog', 'checkuser-log' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();

		$out = $this->getOutput();
		$request = $this->getRequest();

		if ( $this->getUser()->isAllowed( 'checkuser' ) ) {
			$subtitleLink = Linker::linkKnown(
				SpecialPage::getTitleFor( 'CheckUser' ),
				$this->msg( 'checkuser-showmain' )->escaped()
			);
			$out->addSubtitle( $subtitleLink );
		}

		$this->target = trim( $request->getVal( 'cuSearch', $par ) );
		$type = $request->getVal( 'cuSearchType', 'target' );

		$this->displaySearchForm();

		// Default to all log entries - we'll add conditions below if a target was provided
		$searchConds = array();

		if ( $this->target !== '' ) {
			$searchConds = ( $type === 'initiator' )
				? $this->getPerformerSearchConds()
				: $this->getTargetSearchConds();
		}

		if ( $searchConds === null ) {
			// Invalid target was input so show an error message and stop from here
			$out->wrapWikiMsg( "<div class='errorbox'>\n$1\n</div>", 'checkuser-user-nonexistent' );
			return;
		}

		$pager = new CheckUserLogPager(
			$this->getContext(),
			array(
				'queryConds' => $searchConds,
				'year' => $request->getInt( 'year' ),
				'month' => $request->getInt( 'month' ),
			)
		);

		$out->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	/**
	 * Use an HTMLForm to create and output the search form used on this page.
	 */
	protected function displaySearchForm() {
		$request = $this->getRequest();
		$fields = array(
			'target' => array(
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => true,
				'name' => 'cuSearch',
				'size' => 40,
				'label-message' => 'checkuser-log-search-target',
				'default' => $this->target,
			),
			'type' => array(
				'type' => 'radio',
				'name' => 'cuSearchType',
				'label-message' => 'checkuser-log-search-type',
				'options-messages' => array(
					'checkuser-search-target' => 'target',
					'checkuser-search-initiator' => 'initiator',
				),
				'flatlist' => true,
				'default' => 'target',
			),
			// @todo hack until HTMLFormField has a proper date selector
			'monthyear' => array(
				'type' => 'info',
				'default' => Xml::dateMenu( $request->getInt( 'year' ), $request->getInt( 'month' ) ),
				'raw' => true,
			),
		);

		$form = HTMLForm::factory( 'table', $fields, $this->getContext() );
		$form->setMethod( 'get' )
			->setWrapperLegendMsg( 'checkuser-search' )
			->setSubmitTextMsg( 'checkuser-search-submit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Get DB search conditions depending on the CU performer/initiator
	 * Use this only for searches by 'initiator' type
	 *
	 * @return array|null array if valid target, null if invalid
	 */
	protected function getPerformerSearchConds() {
		$initiator = User::newFromName( $this->target );
		if ( $initiator && $initiator->getId() ) {
			return array( 'cul_user' => $initiator->getId() );
		}
		return null;
	}

	/**
	 * Get DB search conditions according to the CU target given.
	 *
	 * @return array|null array if valid target, null if invalid target given
	 */
	protected function getTargetSearchConds() {
		list( $start, $end ) = IP::parseRange( $this->target );
		$conds = null;

		if ( $start !== false ) {
			$dbr = wfGetDB( DB_SLAVE );
			if ( $start === $end ) {
				// Single IP address
				$conds = array(
					'cul_target_hex = ' . $dbr->addQuotes( $start ) . ' OR ' .
					'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_range_start <= ' . $dbr->addQuotes( $start ) . ')'
				);
			} else {
				// IP range
				$conds = array(
					'(cul_target_hex >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_target_hex <= ' . $dbr->addQuotes( $end ) . ') OR ' .
					'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_range_start <= ' . $dbr->addQuotes( $end ) . ')'
				);
			}
		} else {
			$user = User::newFromName( $this->target );
			if ( $user && $user->getId() ) {
				// Registered user
				$conds = array(
					'cul_type' => array( 'userips', 'useredits' ),
					'cul_target_id' => $user->getId(),
				);
			}
		}
		return $conds;
	}

	protected function getGroupName() {
		return 'changes';
	}
}
