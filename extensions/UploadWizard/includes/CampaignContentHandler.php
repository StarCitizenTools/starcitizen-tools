<?php
/**
 * JSON Schema Content Handler
 *
 * @file
 * @ingroup Extensions
 * @ingroup UploadWizard
 *
 * @author Ori Livneh <ori@wikimedia.org>
 * @author Yuvi Panda <yuvipanda@gmail.com>
 */

class CampaignContentHandler extends JsonContentHandler {

	public function __construct( $modelId = 'Campaign' ) {
		parent::__construct( $modelId );
	}

	public function canBeUsedOn( Title $title ) {
		return $title->inNamespace( NS_CAMPAIGN );
	}

	protected function getContentClass() {
		return 'CampaignContent';
	}
}
