<?php

class SpecialCampaigns extends SpecialPage {

	public function __construct() {
		parent::__construct( "Campaigns" );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$dbr = wfGetDB( DB_REPLICA );

		$start = $request->getIntOrNull( 'start' );

		$limit = 50;

		$cond = [ 'campaign_enabled = 1' ];

		if ( $start !== null ) {
			$cond[] = 'campaign_id > ' . $start;
		}

		$res = $dbr->select(
			'uw_campaigns',
			[ 'campaign_id', 'campaign_name' ],
			$cond,
			__METHOD__,
			[ 'LIMIT' => $limit + 1 ]
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
		$campaignTitle = array_key_exists( 'title', $config )
			? $config['title'] : htmlspecialchars( $campaign->getName() );
		$campaignDescription = array_key_exists( 'description', $config ) ? $config['description'] : '';
		$returnHTML =
			Html::rawElement( 'dt', [],
				Html::rawElement( 'a', [ 'href' => $campaignURL ], $campaignTitle )
			) .
				Html::rawElement( 'dd', [], $campaignDescription );
		return $returnHTML;
	}

	private function getHtmlForPagination( $firstId ) {
		$nextHref = $this->getPageTitle()->getLocalURL( [ 'start' => $firstId ] );
		return Html::rawElement( 'div',
			[ 'id' => 'mwe-upload-campaigns-pagination' ],
			Html::element( 'a',
				[ 'href' => $nextHref ],
				$this->msg( 'mwe-upload-campaigns-pagination-next' )->text()
			)
		);
	}

	protected function getGroupName() {
		return 'media';
	}
}
