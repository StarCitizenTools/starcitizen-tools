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

use MediaWiki\MediaWikiServices;

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
		$rl = MediaWikiServices::getInstance()->getResourceLoader();
		// FIXME: This string exists nowhere in Wikimedia Gerrit outside this file.
		return $rl->isModuleRegistered( 'ext.campaigns' );
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
		$gallery->setWidths( '180' );
		$gallery->setHeights( '180' );
		$gallery->setShowBytes( false );

		$this->context->getOutput()->setCdnMaxage(
			UploadWizardConfig::getSetting( 'campaignSquidMaxAge' )
		);
		$this->context->getOutput()->setHTMLTitle( $this->context->msg( 'pagetitle', $campaignTitle ) );
		$this->context->getOutput()->enableOOUI();

		$images = $this->campaign->getUploadedMedia();

		if ( $this->context->getUser()->isAnon() ) {
			$urlParams = [ 'returnto' => $this->campaign->getTitle()->getPrefixedText() ];

			if ( $this->isCampaignExtensionEnabled() ) {
				$campaignTemplate = UploadWizardConfig::getSetting( 'campaignCTACampaignTemplate' );
				$urlParams['campaign'] = str_replace( '$1', $this->campaign->getName(), $campaignTemplate );
			}
			$createAccountUrl = Skin::makeSpecialUrlSubpage( 'UserLogin', 'signup', $urlParams );
			$uploadLink = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'mwe-upwiz-campaign-create-account-button' )->text(),
				'flags' => [ 'progressive', 'primary' ],
				'href' => $createAccountUrl
			] );
		} else {
			$uploadUrl = Skin::makeSpecialUrl(
				'UploadWizard', [ 'campaign' => $this->campaign->getName() ]
			);
			$uploadLink = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'mwe-upwiz-campaign-upload-button' )->text(),
				'flags' => [ 'progressive', 'primary' ],
				'href' => $uploadUrl
			] );
		}

		if ( $images === [] ) {
			$body = Html::element(
				'div',
				[ 'id' => 'mw-campaign-no-uploads-yet' ],
				wfMessage( 'mwe-upwiz-campaign-no-uploads-yet' )->plain()
			);
		} else {
			foreach ( $images as $image ) {
				$gallery->add( $image );
			}

			$body =
				Html::rawElement( 'div', [ 'id' => 'mw-campaign-images' ], $gallery->toHTML() ) .
				Html::rawElement( 'a',
					[ 'id' => 'mw-campaign-view-all', 'href' => $campaignViewMoreLink ],
					Html::rawElement(
						'span',
						[ 'class' => 'mw-campaign-chevron mw-campaign-float-left' ], '&nbsp'
					) .
					wfMessage( 'mwe-upwiz-campaign-view-all-media' )->escaped() .
					Html::rawElement(
						'span',
						[ 'class' => 'mw-campaign-chevron mw-campaign-float-right' ], '&nbsp'
					)
				);
		}

		if ( UploadWizardConfig::getSetting( 'campaignExpensiveStatsEnabled' ) === true ) {
			$uploaderCount = $this->campaign->getTotalContributorsCount();
			$campaignExpensiveStats =
				Html::rawElement( 'div', [ 'class' => 'mw-campaign-number-container' ],
					Html::element( 'div', [ 'class' => 'mw-campaign-number' ],
						$this->context->getLanguage()->formatNum( $uploaderCount ) ) .
					Html::element( 'span',
						[ 'class' => 'mw-campaign-number-desc' ],
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
			Html::rawElement( 'div', [ 'id' => 'mw-campaign-container' ],
				Html::rawElement( 'div', [ 'id' => 'mw-campaign-header' ],
					Html::rawElement( 'div', [ 'id' => 'mw-campaign-primary-info' ],
						// No need to escape these, since they are just parsed wikitext
						// Any stripping that needed to be done should've been done by the parser
						Html::rawElement( 'p', [ 'id' => 'mw-campaign-title' ], $campaignTitle ) .
						Html::rawElement( 'p', [ 'id' => 'mw-campaign-description' ], $campaignDescription ) .
					$uploadLink
					) .
					Html::rawElement( 'div', [ 'id' => 'mw-campaign-numbers' ],
						$campaignExpensiveStats .
						Html::rawElement( 'div', [ 'class' => 'mw-campaign-number-container' ],
							Html::element( 'div', [ 'class' => 'mw-campaign-number' ],
								$this->context->getLanguage()->formatNum( $uploadCount )
							) .
							Html::element( 'span',
								[ 'class' => 'mw-campaign-number-desc' ],
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
