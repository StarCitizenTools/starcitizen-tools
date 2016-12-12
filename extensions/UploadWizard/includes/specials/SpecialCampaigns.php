<?php

class SpecialCampaigns extends SpecialPage {

	function __construct() {
		parent::__construct( "Campaigns" );
	}

	public function execute( $subPage ) {
		$request = $this->getRequest();
		$dbr = wfGetDB( DB_SLAVE );

		$start = (int)$request->getVal( 'start' );

		$limit = 50;

		$cond = array( 'campaign_enabled = 1' );

		if ( $start !== null ) {
			// Not SQL Injection, since $start is cast to (int)
			$cond[] = "campaign_id > $start";
		}

		$res = $dbr->select(
			'uw_campaigns',
			array( 'campaign_id', 'campaign_name' ),
			$cond,
			__METHOD__,
			array( 'LIMIT' => $limit + 1 )
		);

		$this->getOutput()->setPageTitle( $this->msg( 'mwe-upload-campaigns-list-title' ) );
		$this->getOutput()->addModules( 'ext.uploadWizard.uploadCampaign.list' );
		$this->getOutput()->addHTML( '<dl>' );

		$curCount = 0;
		$lastId = null;

		foreach ( $res as $row ) {
			$curCount++;

			if ( $curCount > $limit ) {
				// We've an extra element. Paginate!
				$lastId = $row->campaign_id;
				break;
			} else {
				$campaign = UploadWizardCampaign::newFromName( $row->campaign_name );
				$this->getOutput()->addHTML( $this->getHtmlForCampaign( $campaign ) );
			}
		}
		$this->getOutput()->addHTML( '</dl>' );

		// Pagination links!
		if ( $lastId !== null ) {
			$this->getOutput()->addHTML( $this->getHtmlForPagination( $lastId ) );
		}
	}

	private function getHtmlForCampaign( UploadWizardCampaign $campaign ) {
		$config = $campaign->getParsedConfig();
		$campaignURL = $campaign->getTitle()->getLocalURL();
		$campaignTitle = array_key_exists( 'title', $config ) ? $config['title'] : $campaign->getName();
		$campaignDescription = array_key_exists( 'description', $config ) ? $config['description'] : '';
		$returnHTML =
			Html::rawElement( 'dt', array(),
				Html::rawElement( 'a', array( 'href' => $campaignURL ), $campaignTitle )
			) .
				Html::element( 'dd', array(), $campaignDescription );
		return $returnHTML;
	}

	private function getHtmlForPagination( $firstId ) {
		$nextHref = $this->getPageTitle()->getLocalURL( array( 'start' => $firstId ) );
		return Html::rawElement( 'div',
			array( 'id' => 'mwe-upload-campaigns-pagination' ),
			Html::element( 'a',
				array( 'href' => $nextHref ),
				$this->msg( 'mwe-upload-campaigns-pagination-next' )->text()
			)
		);
	}

	protected function getGroupName() {
		return 'media';
	}
}
