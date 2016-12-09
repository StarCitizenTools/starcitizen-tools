<?php
/**
 * Special:UploadWizard
 *
 * Easy to use multi-file upload page.
 *
 * @file
 * @ingroup SpecialPage
 * @ingroup Upload
 */

class SpecialUploadWizard extends SpecialPage {
	// the HTML form without javascript
	private $simpleForm;

	/**
	 * The name of the upload wizard campaign, or null when none is specified.
	 *
	 * @since 1.2
	 * @var string|null
	 */
	protected $campaign = null;

	// $request is the request (usually wgRequest)
	// $par is everything in the URL after Special:UploadWizard. Not sure what we can use it for
	public function __construct( $request = null, $par = null ) {
		parent::__construct( 'UploadWizard', 'upload' );

		// create a simple form for non-JS fallback, which targets the old Special:Upload page.
		// at some point, if we completely subsume its functionality, change that to point here again,
		// but then we'll need to process non-JS uploads in the same way Special:Upload does.
		$this->simpleForm = new UploadWizardSimpleForm();
		$this->simpleForm->setTitle(
			SpecialPage::getTitleFor( 'Upload' )
		);
	}

	/**
	 * Replaces default execute method
	 * Checks whether uploading enabled, user permissions okay,
	 * @param $subPage, e.g. the "foo" in Special:UploadWizard/foo.
	 */
	public function execute( $subPage ) {
		// side effects: if we can't upload, will print error page to wgOut
		// and return false
		if ( !( $this->isUploadAllowed() && $this->isUserUploadAllowed( $this->getUser() ) ) ) {
			return;
		}

		$this->setHeaders();
		$this->outputHeader();

		$req = $this->getRequest();

		// if query string includes 'skiptutorial=true' set config variable to true
		$skipTutorial = $req->getCheck( 'skiptutorial' );
		if ( $skipTutorial ) {
			$skip = in_array( $skipTutorial, array( '1', 'true' ) );
			if ( $skip === true ) {
				UploadWizardConfig::setUrlSetting( 'tutorial', array() );
			}
		}

		$urlArgs = array( 'description', 'lat', 'lon', 'alt' );

		$urlDefaults = array();
		foreach ( $urlArgs as $arg ) {
			$value = $req->getText( $arg );
			if ( $value ) {
				$urlDefaults[$arg] = $value;
			}
		}

		$categories = $req->getText( 'categories' );
		if ( $categories ) {
			$urlDefaults['categories'] = explode( '|', $categories );
		}

		$urlDefaults['objref'] = $req->getText( 'objref' ) ?: '';
		$urlDefaults['updateList'] = $req->getText( 'updateList' ) ?: '';

		UploadWizardConfig::setUrlSetting( 'defaults', $urlDefaults );

		$fields = $req->getArray( 'fields' );
		$fieldDefaults = array();

		# Support id and id2 for field0 and field1
		# Legacy support for old URL structure. They override fields[]
		if ( $req->getText( 'id' ) ) {
			$fields[0] = $req->getText( 'id' );
		}

		if ( $req->getText( 'id2' ) ) {
			$fields[1] = $req->getText( 'id2' );
		}

		if ( $fields ) {
			foreach ( $fields as $index => $value ) {
				$fieldDefaults[$index]['initialValue'] = $value;
			}
		}

		UploadWizardConfig::setUrlSetting( 'fields', $fieldDefaults );

		$this->handleCampaign();

		$out = $this->getOutput();

		// fallback for non-JS
		$out->addHTML( '<div class="mwe-upwiz-unavailable">' );
		$out->addHTML( '<p class="errorbox">' . $this->msg( 'mwe-upwiz-unavailable' )->parse() . '</p>' );
		$this->simpleForm->show();
		$out->addHTML( '</div>' );

		// global javascript variables
		$this->addJsVars( $subPage );

		// dependencies (css, js)
		$out->addModules( 'ext.uploadWizard.page' );
		$out->addModuleStyles( 'ext.uploadWizard.page.styles' );

		// where the uploadwizard will go
		// TODO import more from UploadWizard's createInterface call.
		$out->addHTML( $this->getWizardHtml() );
	}

	/**
	 * Handles the campaign parameter.
	 *
	 * @since 1.2
	 */
	protected function handleCampaign() {
		$campaignName = $this->getRequest()->getVal( 'campaign' );

		if ( !is_null( $campaignName ) && $campaignName !== '' ) {
			$campaign = UploadWizardCampaign::newFromName( $campaignName );

			if ( $campaign === false ) {
				$this->displayError( $this->msg( 'mwe-upwiz-error-nosuchcampaign', $campaignName )->text() );
			} else {
				if ( $campaign->getIsEnabled() ) {
					$this->campaign = $campaignName;
				} else {
					$this->displayError( $this->msg( 'mwe-upwiz-error-campaigndisabled', $campaignName )->text() );
				}
			}
		}
	}

	/**
	 * Display an error message.
	 *
	 * @since 1.2
	 *
	 * @param string $message
	 */
	protected function displayError( $message ) {
		$this->getOutput()->addHTML( Html::element(
			'span',
			array( 'class' => 'errorbox' ),
			$message
		) . '<br /><br /><br />' );
	}

	/**
	 * Adds some global variables for our use, as well as initializes the UploadWizard
	 *
	 * TODO once bug https://bugzilla.wikimedia.org/show_bug.cgi?id=26901
	 * is fixed we should package configuration with the upload wizard instead of
	 * in uploadWizard output page.
	 *
	 * @param subpage, e.g. the "foo" in Special:UploadWizard/foo
	 */
	public function addJsVars( $subPage ) {
		global $wgSitename, $wgIllegalFileChars;

		$config = UploadWizardConfig::getConfig( $this->campaign );

		if ( array_key_exists( 'trackingCategory', $config ) ) {
			if ( array_key_exists( 'campaign', $config['trackingCategory'] ) ) {
				if ( $this->campaign !== null ) {
					$config['trackingCategory']['campaign'] = str_replace(
						'$1',
						$this->campaign,
						$config['trackingCategory']['campaign']
					);
				} else {
					unset( $config['trackingCategory']['campaign'] );
				}
			}
		}
		// UploadFromUrl parameter set to true only if the user is allowed to upload a file
		// from a URL which we need to check in our Javascript implementation.
		if ( UploadFromUrl::isEnabled() && UploadFromUrl::isAllowed( $this->getUser() ) === true ) {
			$config['UploadFromUrl'] = true;
		} else {
			$config['UploadFromUrl'] = false;
		}

		// Get the user's default license. This will usually be 'default', but
		// can be a specific license like 'ownwork-cc-zero'.
		$userDefaultLicense = $this->getUser()->getOption( 'upwiz_deflicense' );

		if ( $userDefaultLicense !== 'default' ) {
			$licenseParts = explode( '-', $userDefaultLicense, 2 );
			$userLicenseType = $licenseParts[0];
			$userDefaultLicense = $licenseParts[1];

			// Determine if the user's default license is valid for this campaign
			switch ( $config['licensing']['ownWorkDefault'] ) {
				case "own":
					$defaultInAllowedLicenses = in_array(
						$userDefaultLicense, $config['licensing']['ownWork']['licenses']
					);
					break;
				case "notown":
					$defaultInAllowedLicenses = in_array(
						$userDefaultLicense, UploadWizardConfig::getThirdPartyLicenses()
					);
					break;
				case "choice":
					$defaultInAllowedLicenses = ( in_array(
							$userDefaultLicense, $config['licensing']['ownWork']['licenses']
						) ||
						in_array( $userDefaultLicense, UploadWizardConfig::getThirdPartyLicenses() ) );
					break;
			}

			if ( $defaultInAllowedLicenses ) {
				if ( $userLicenseType === 'ownwork' ) {
					$userLicenseGroup = 'ownWork';
				} else {
					$userLicenseGroup = 'thirdParty';
				}
				$config['licensing'][$userLicenseGroup]['defaults'] = array( $userDefaultLicense );
				$config['licensing']['defaultType'] = $userLicenseType;

				if ( $userDefaultLicense === 'custom' ) {
					$config['licenses']['custom']['defaultText'] =
						$this->getUser()->getOption( 'upwiz_deflicense_custom' );
				}
			}
		}

		$bitmapHandler = new BitmapHandler();
		$this->getOutput()->addJsConfigVars(
			array(
				'UploadWizardConfig' => $config,

				// Site name is a true global not specific to Upload Wizard
				'wgSiteName' => $wgSitename,
				'wgFileCanRotate' => $bitmapHandler->canRotate(),
				'wgIllegalFileChars' => $wgIllegalFileChars . '#',
			)
		);
	}

	/**
	 * Check if anyone can upload (or if other sitewide config prevents this)
	 * Side effect: will print error page to wgOut if cannot upload.
	 * @return boolean -- true if can upload
	 */
	private function isUploadAllowed() {
		// Check uploading enabled
		if ( !UploadBase::isEnabled() ) {
			$this->getOutput()->showErrorPage( 'uploaddisabled', 'uploaddisabledtext' );
			return false;
		}

		// XXX does wgEnableAPI affect all uploads too?

		// Check whether we actually want to allow changing stuff
		if ( wfReadOnly() ) {
			$this->getOutput()->readOnlyPage();
			return false;
		}

		// we got all the way here, so it must be okay to upload
		return true;
	}

	/**
	 * Check if the user can upload
	 * Side effect: will print error page to wgOut if cannot upload.
	 * @param User
	 * @return boolean -- true if can upload
	 */
	private function isUserUploadAllowed( $user ) {
		// Check permissions
		$permissionRequired = UploadBase::isAllowed( $user );
		if ( $permissionRequired !== true ) {
			throw new PermissionsError( $permissionRequired );
		}

		// Check blocks
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Global blocks
		if ( $user->isBlockedGlobally() ) {
			throw new UserBlockedError( $user->getGlobalBlock() );
		}

		// we got all the way here, so it must be okay to upload
		return true;
	}

	/**
	 * Return the basic HTML structure for the entire page
	 * Will be enhanced by the javascript to actually do stuff
	 * @return {string} html
	 */
	protected function getWizardHtml() {
		global $wgExtensionAssetsPath;

		$config = UploadWizardConfig::getConfig( $this->campaign );

		if ( array_key_exists(
			'display', $config ) && array_key_exists( 'headerLabel', $config['display'] )
		) {
			$this->getOutput()->addHtml( $config['display']['headerLabel'] );
		}

		if ( array_key_exists( 'fallbackToAltUploadForm', $config )
			&& array_key_exists( 'altUploadForm', $config )
			&& $config['altUploadForm'] != ''
			&& $config[ 'fallbackToAltUploadForm' ]
		) {
			$linkHtml = '';
			$altUploadForm = Title::newFromText( $config[ 'altUploadForm' ] );
			if ( $altUploadForm instanceof Title ) {
				$linkHtml = Html::rawElement( 'p', array( 'style' => 'text-align: center;' ),
					Html::rawElement( 'a', array( 'href' => $altUploadForm->getLocalURL() ),
						$config['altUploadForm']
					)
				);
			}

			return
				Html::rawElement(
					'div',
					array(),
					Html::rawElement(
						'p',
						array( 'style' => 'text-align: center' ),
						wfMessage( 'mwe-upwiz-extension-disabled' )->text()
					) . $linkHtml
				);
		}

		$tutorialHtml = '';
		// only load the tutorial HTML if we aren't skipping the first step
		if ( !$this->getUser()->getBoolOption( 'upwiz_skiptutorial' ) &&
			$config['tutorial'] !== null && $config['tutorial'] !== array() &&
			$config['tutorial']['skip'] !== true
		) {
			$tutorialHtml = UploadWizardTutorial::getHtml( $this->campaign );
		}

		// TODO move this into UploadWizard.js or some other javascript resource so the upload wizard
		// can be dynamically included ( for example the add media wizard )
		return
		// @codingStandardsIgnoreStart
			'<div id="upload-wizard" class="upload-section">' .
				'<div id="mwe-upwiz-tutorial-html" style="display:none;">' .
					$tutorialHtml .
				'</div>' .

				// if loading takes > 2 seconds display spinner. Note we are evading Resource Loader here, and linking directly. Because we want an image to appear if RL's package is late.
				// using some &nbsp;'s which is a bit of superstition, to make sure jQuery will hide this (it seems that it doesn't sometimes, when it has no content)
				// the min-width & max-width is copied from the #uploadWizard properties, so in nice browsers the spinner is right where the button will go.
				'<div id="mwe-first-spinner" style="min-width:750px; max-width:900px; height:200px; line-height:200px; text-align:center;">' .
					'&nbsp;<img src="' . $wgExtensionAssetsPath . '/UploadWizard/resources/images/24px-spinner-0645ad.gif" width="24" height="24" />&nbsp;' .
				'</div>' .
			'</div>';
		// @codingStandardsIgnoreEnd
	}

	protected function getGroupName() {
		return 'media';
	}
}


/**
 * This is a hack on UploadForm, to make one that works from UploadWizard when JS is not available.
 */
class UploadWizardSimpleForm extends UploadForm {

	/*
	 * Normally, UploadForm adds its own Javascript.
	 * We wish to prevent this, because we want to control the case where we have Javascript.
	 * So, we make the addUploadJS a no-op.
	 */
	protected function addUploadJS() {

	}
}
