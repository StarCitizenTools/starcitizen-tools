<?php

/**
 * Class to encapsulate all the html generation associated with the UploadWizard tutorial.
 * Might be a start for a subclass of UploadWizard, if we ever free it of its WMF-oriented features
 * So that non-WMF'ers can use it
 */
class UploadWizardTutorial {

	// Id of imagemap used in tutorial.
	const IMAGEMAP_ID = 'tutorialMap';

	/**
	 * Fetches appropriate HTML for the tutorial portion of the wizard.
	 * Looks up an image on the current wiki. This will work as is on Commons, and will also work
	 * on test wikis that enable instantCommons.
	 * @param String|null $campaign Upload Wizard campaign for which the tutorial should be displayed.
	 * @return String html that will display the tutorial.
	 */
	public static function getHtml( $campaign = null ) {
		global $wgLang;

		$error = null;
		$errorHtml = '';
		$tutorialHtml = '';

		$langCode = $wgLang->getCode();

		$tutorial = UploadWizardConfig::getSetting( 'tutorial', $campaign );
		// getFile returns false if it can't find the right file
		$tutorialFile = self::getFile( $langCode, $tutorial );
		if ( $tutorialFile === false ) {
			$error = 'localized-file-missing';
			foreach ( $wgLang->getFallbackLanguages() as $langCode ) {
				$tutorialFile = self::getFile( $langCode, $tutorial );
				if ( $tutorialFile !== false ) {
					// $langCode remains as the code where a file is found.
					break;
				}
			}
		}

		// at this point, we have one of the following situations:
		// $error is null, and tutorialFile is the right one for this language
		// $error notes we couldn't find the tutorialFile for your language,
		// and $tutorialFile is the english one
		// $error notes we couldn't find the tutorialFile for your language,
		// and $tutorialFile is still false (major file failure)

		if ( $tutorialFile ) {
			// XXX TODO if the client can handle SVG, we could also just send it the unscaled thumb,
			// client-scaled into a DIV or something.
			// if ( client can handle SVG ) {
			//   $tutorialThumbnailImage->getUnscaledThumb();
			// }
			// put it into a div of appropriate dimensions.

			// n.b. File::transform() returns false if failed, MediaTransformOutput otherwise
			$thumbnailImage = $tutorialFile->transform( array( 'width' => $tutorial['width'] ) );

			if ( $thumbnailImage ) {
				$tutorialHtml = self::getImageHtml( $thumbnailImage, $tutorial );
			} else {
				$error = 'cannot-transform';
			}
		} else {
			$error = 'file-missing';
		}

		if ( $error !== null ) {
			// Messages:
			// mwe-upwiz-tutorial-error-localized-file-missing, mwe-upwiz-tutorial-error-file-missing,
			// mwe-upwiz-tutorial-error-cannot-transform
			$errorMsg = wfMessage( 'mwe-upwiz-tutorial-error-' . $error );
			if ( $error === 'localized-file-missing' ) {
				$errorMsg->params( Language::fetchLanguageName( $langCode, $wgLang->getCode() ) );
			}
			$errorHtml = Html::element(
				'p', array( 'class' => 'errorbox', 'style' => 'float: none;' ), $errorMsg->text()
			);
		}

		return $errorHtml . $tutorialHtml;

	}

	/**
	 * Get tutorial file for a particular language, or false if not available.
	 *
	 * @param String $langCode: language Code
	 * @param String|null $campaign Upload Wizard campaign for which the tutorial should be displayed.
	 *
	 * @return File|false
	 */
	public static function getFile( $langCode, $tutorial ) {
		$tutorialName = str_replace( '$1', $langCode, $tutorial['template'] );
		return wfFindFile( Title::newFromText( $tutorialName, NS_FILE ) );
	}

	/**
	 * Constructs HTML for the tutorial (laboriously),
	 * including an imagemap for the clickable "Help desk" button.
	 *
	 * @param MediaTransformOutput $thumb
	 * @param String|null $campaign Upload Wizard campaign for which the tutorial should be displayed.
	 *
	 * @return String HTML representing the image, with clickable helpdesk button
	 */
	public static function getImageHtml( MediaTransformOutput $thumb, $tutorial ) {
		$helpDeskUrl = wfMessage( 'mwe-upwiz-help-desk-url' )->text();

		// Per convention, we may be either using an absolute URL or a wiki page title in this UI message
		if ( preg_match( '/^(?:' . wfUrlProtocols() . ')/', $helpDeskUrl ) ) {
			$helpDeskHref = $helpDeskUrl;
		} else {
			$helpDeskTitle = Title::newFromText( $helpDeskUrl );
			if ( !$helpDeskTitle || !$helpDeskTitle->exists() ) {
				// Fall back to the wiki's content language...if that page
				// doesn't exist, we can't help.
				$helpDeskUrl = wfMessage( 'mwe-upwiz-help-desk-url' )->inContentLanguage()->text();
				$helpDeskTitle = Title::newFromText( $helpDeskUrl );
			}

			$helpDeskHref = $helpDeskTitle ? $helpDeskTitle->getLocalURL() : '#';
		}

		$buttonCoords = $tutorial['helpdeskCoords'];
		$useMap = $buttonCoords !== false && trim( $buttonCoords ) != '';

		$imgAttributes = array(
			'src' => $thumb->getUrl(),
			'width' => $thumb->getWidth(),
			'height' => $thumb->getHeight(),
		);

		if ( $useMap ) {
			$imgAttributes['usemap'] = '#' . self::IMAGEMAP_ID;
		}

		// here we use the not-yet-forgotten HTML imagemap to add a clickable area to the tutorial image.
		// we could do more special effects with hovers and images and such, not to mention SVG scripting,
		// but we aren't sure what we want yet...
		$imgHtml = Html::element( 'img', $imgAttributes );

		if ( $useMap ) {
			$areaAltText = wfMessage( 'mwe-upwiz-help-desk' )->text();

			$area = Html::element( 'area', array(
				'shape' => 'rect',
				'coords' => $buttonCoords,
				'href' => $helpDeskHref,
				'alt' => $areaAltText,
				'title' => $areaAltText,
				'id' => 'mwe-upwiz-tutorial-helpdesk',
			) );

			$imgHtml = Html::rawElement(
				'map',
				array( 'id' => self::IMAGEMAP_ID, 'name' => self::IMAGEMAP_ID ),
				$area
			) . $imgHtml;
		}

		return $imgHtml;
	}

}
