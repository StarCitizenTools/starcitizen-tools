<?php
/**
 * Upload Campaign Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup UploadWizard
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */


/**
 * Represents the configuration of an Upload Campaign
 */
class CampaignContent extends JsonContent {

	function __construct( $text ) {
		parent::__construct( $text, 'Campaign' );
	}

	/**
	 * Checks user input JSON to make sure that it produces a valid campaign object
	 *
	 * @throws JsonSchemaException: If invalid.
	 * @return bool: True if valid.
	 */
	function validate() {
		$campaign = $this->getJsonData();
		if ( !is_array( $campaign ) ) {
			throw new JsonSchemaException( wfMessage( 'eventlogging-invalid-json' )->parse() );
		}

		$schema = include ( __DIR__ . '/CampaignSchema.php' );

		// Only validate fields we care about
		$campaignFields = array_keys( $schema['properties'] );

		$fullConfig = UploadWizardConfig::getConfig();

		$defaultCampaignConfig = array();

		foreach ( $fullConfig as $key => $value ) {
			if ( in_array( $key, $campaignFields ) ) {
				$defaultCampaignConfig[ $key ] = $value;
			}
		}

		$mergedConfig = UploadWizardConfig::array_replace_sanely( $defaultCampaignConfig, $campaign );
		return efSchemaValidate( $mergedConfig, $schema );
	}

	/**
	 * @return bool: Whether content is valid JSON Schema.
	 */
	function isValid() {
		try {
			return parent::isValid() && $this->validate();
		} catch ( JsonSchemaException $e ) {
			return false;
		}
	}

	/**
	 * Override getParserOutput, since we require $title to generate our output
	 */
	function getParserOutput( Title $title,
		$revId = null,
		ParserOptions $otpions = null, $generateHtml = true
	) {
		$po = new ParserOutput();
		$campaign = new UploadWizardCampaign( $title, $this->getJsonData() );

		if ( $generateHtml ) {
			$po->setText( $this->generateHtml( $campaign ) );
		}

		// Register template usage
		// FIXME: should we be registering other stuff??
		foreach ( $campaign->getTemplates() as $ns => $templates ) {
			foreach ( $templates as $dbk => $ids ) {
				$title = Title::makeTitle( $ns, $dbk );
				$po->addTemplate( $title, $ids[0], $ids[1] );
			}
		}

		// Add some styles
		$po->addModuleStyles( 'ext.uploadWizard.uploadCampaign.display' );

		return $po;
	}

	function generateHtml( $campaign ) {

		$formatter = new CampaignPageFormatter( $campaign );

		return $formatter->generateReadHtml();
	}

	/**
	 * Deprecated in JsonContent but still useful here because we need to merge the schema's data
	 * with a config array
	 *
	 * @return array|null
	 */
	public function getJsonData() {
		return FormatJson::decode( $this->getNativeData(), true );
	}
}
