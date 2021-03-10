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

	protected function getContentClass() {
		return CampaignContent::class;
	}

	public function makeEmptyContent() {
		$class = $this->getContentClass();
		return new $class( '{"enabled":false}' );
	}
}
