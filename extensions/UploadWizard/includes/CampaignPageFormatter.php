<?php
/**
 * Upload Campaign Formatter
 *
 * @file
 * @ingroup Extensions
 * @ingroup UploadWizard
 *
 * @author Yuvi Panda <yuvipanda@gmail.com>
 */


/**
 * Helper class to produce formatted HTML output for Campaigns
 */
class CampaignPageFormatter {
	/** @var UploadWizardCampaign|null $campaign */
	protected $campaign = null;
	/** @var IContextSource|null $context */
	protected $context = null;

	public function __construct( $campaign, $context = null ) {
		$this->campaign = $campaign;
		if ( $context === null ) {
			$this->context = RequestContext::getMain();
		} else {
			$this->context = $context;
		}
	}

	private function isCampaignExtensionEnabled() {
		global $wgResourceModules;
		return isset( $wgResourceModules['ext.campaigns'] );
	}

	public function generateReadHtml() {
		$config = $this->campaign->getParsedConfig();

		$campaignTitle = array_key_exists( 'title', $config ) ?
			$config['title'] :
			$this->campaign->getName();
		$campaignDescription = array_key_exists( 'description', $config ) ? $config['description'] : '';
		$campaignViewMoreLink = $this->campaign->getTrackingCategory()->getFullURL();

		$gallery = ImageGalleryBase::factory( 'packed-hover' );
		$gallery->setContext( $this->context );
		$gallery->setWidths( 180 );
		$gallery->setHeights( 180 );
		$gallery->setShowBytes( false );

		$this->context->getOutput()->setSquidMaxage(
			UploadWizardConfig::getSetting( 'campaignSquidMaxAge' )
		);
		$this->context->getOutput()->setHTMLTitle( $this->context->msg( 'pagetitle', $campaignTitle ) );

		$images = $this->campaign->getUploadedMedia();

		if ( $this->context->getUser()->isAnon() ) {
			$urlParams = array( 'returnto' => $this->campaign->getTitle()->getPrefixedText() );

			if ( $this->isCampaignExtensionEnabled() ) {
				$campaignTemplate = UploadWizardConfig::getSetting( 'campaignCTACampaignTemplate' );
				$urlParams['campaign'] = str_replace( '$1', $this->campaign->getName(), $campaignTemplate );
			}
			$createAccountUrl = Skin::makeSpecialUrlSubpage( 'UserLogin', 'signup', $urlParams );
			$uploadLink =
						Html::element( 'a',
							array( 'class' => 'mw-ui-big mw-ui-button mw-ui-primary', 'href' => $createAccountUrl ),
							wfMessage( 'mwe-upwiz-campaign-create-account-button' )->text()
						);
		} else {
			$uploadUrl = Skin::makeSpecialUrl(
				'UploadWizard', array( 'campaign' => $this->campaign->getName() )
			);
			$uploadLink =
						Html::element( 'a',
							array( 'class' => 'mw-ui-big mw-ui-button mw-ui-primary', 'href' => $uploadUrl ),
							wfMessage( 'mwe-upwiz-campaign-upload-button' )->text()
						);
		}

		if ( count( $images ) === 0 ) {
			$body = Html::element(
				'div',
				array( 'id' => 'mw-campaign-no-uploads-yet' ),
				wfMessage( 'mwe-upwiz-campaign-no-uploads-yet' )->plain()
			);
		} else {
			foreach ( $images as $image ) {
				$gallery->add( $image );
			}

			$body =
				Html::rawElement( 'div', array( 'id' => 'mw-campaign-images' ), $gallery->toHTML() ) .
				Html::rawElement( 'a',
					array( 'id' => 'mw-campaign-view-all', 'href' => $campaignViewMoreLink ),
					Html::rawElement(
						'span',
						array( 'class' => 'mw-campaign-chevron mw-campaign-float-left' ), '&nbsp'
					) .
					wfMessage( 'mwe-upwiz-campaign-view-all-media' )->escaped() .
					Html::rawElement(
						'span',
						array( 'class' => 'mw-campaign-chevron mw-campaign-float-right' ), '&nbsp'
					)
				);
		}

		if ( UploadWizardConfig::getSetting( 'campaignExpensiveStatsEnabled' ) === true ) {
			$uploaderCount = $this->campaign->getTotalContributorsCount();
			$campaignExpensiveStats =
				Html::rawElement( 'div', array( 'class' => 'mw-campaign-number-container' ),
					Html::element( 'div', array( 'class' => 'mw-campaign-number' ),
						$this->context->getLanguage()->formatNum( $uploaderCount ) ) .
					Html::element( 'span',
						array( 'class' => 'mw-campaign-number-desc' ),
						wfMessage( 'mwe-upwiz-campaign-contributors-count-desc' )
						->numParams( $uploaderCount )
						->text()
					)
				);
		} else {
			$campaignExpensiveStats = '';
		}

		$uploadCount = $this->campaign->getUploadedMediaCount();
		$result =
			Html::rawElement( 'div', array( 'id' => 'mw-campaign-container' ),
				Html::rawElement( 'div', array( 'id' => 'mw-campaign-header' ),
					Html::rawElement( 'div', array( 'id' => 'mw-campaign-primary-info' ),
						// No need to escape these, since they are just parsed wikitext
						// Any stripping that needed to be done should've been done by the parser
						Html::rawElement( 'p', array( 'id' => 'mw-campaign-title' ), $campaignTitle ) .
						Html::rawElement( 'p', array( 'id' => 'mw-campaign-description' ), $campaignDescription ) .
					$uploadLink
					) .
					Html::rawElement( 'div', array( 'id' => 'mw-campaign-numbers' ),
						$campaignExpensiveStats .
						Html::rawElement( 'div', array( 'class' => 'mw-campaign-number-container' ),
							Html::element( 'div', array( 'class' => 'mw-campaign-number' ),
								$this->context->getLanguage()->formatNum( $uploadCount )
							) .
							Html::element( 'span',
								array( 'class' => 'mw-campaign-number-desc' ),
								wfMessage( 'mwe-upwiz-campaign-media-count-desc' )
								->numParams( $uploadCount )
								->text()
							)
						)
					)
				) .
				$body
			);
		return $result;
	}
}
