<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WikiSEO\Generator;

use Html;
use MediaWiki\Extension\WikiSEO\Validator;
use MediaWiki\Extension\WikiSEO\WikiSEO;
use OutputPage;

/**
 * Basic metadata tag generator
 * Adds metadata for description, keywords and robots
 *
 * @package MediaWiki\Extension\WikiSEO\Generator
 */
class MetaTag extends AbstractBaseGenerator implements GeneratorInterface {
	private static $tags = [ 'description', 'keywords', 'robots', 'google_bot' ];

	/**
	 * Initialize the generator with all metadata and the page to output the metadata onto
	 *
	 * @param array $metadata All metadata
	 * @param OutputPage $out The page to add the metadata to
	 *
	 * @return void
	 */
	public function init( array $metadata, OutputPage $out ) {
		$this->metadata = $metadata;
		$this->outputPage = $out;
	}

	/**
	 * Add the metadata to the OutputPage
	 *
	 * @return void
	 */
	public function addMetadata() {
		$this->addGoogleSiteVerification();
		$this->addBingSiteVerification();
		$this->addYandexSiteVerification();
		$this->addAlexaSiteVerification();
		$this->addPinterestSiteVerification();
		$this->addNortonSiteVerification();
		$this->addFacebookAppId();
		$this->addFacebookAdmins();
		$this->addHrefLangs();

		// Meta tags already set in the page
		$outputMeta = [];
		foreach ( $this->outputPage->getMetaTags() as $metaTag ) {
			$outputMeta[$metaTag[0]] = $metaTag[1];
		}

		foreach ( self::$tags as $tag ) {
			// Only add tag if it doesn't already exist in the output page
			if ( array_key_exists( $tag, $this->metadata ) && !array_key_exists( $tag, $outputMeta ) ) {
				$this->outputPage->addMeta( $tag, $this->metadata[$tag] );
			}
		}
	}

	/**
	 * Add $wgGoogleSiteVerificationKey from LocalSettings
	 */
	private function addGoogleSiteVerification() {
		$googleSiteVerificationKey = $this->getConfigValue( 'GoogleSiteVerificationKey' );

		if ( $googleSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'google-site-verification', $googleSiteVerificationKey );
		}
	}

	/**
	 * Add $wgBingSiteVerificationKey from LocalSettings
	 */
	private function addBingSiteVerification() {
		$bingSiteVerificationKey = $this->getConfigValue( 'BingSiteVerificationKey' );

		if ( $bingSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'msvalidate.01', $bingSiteVerificationKey );
		}
	}

	/**
	 * Add $wgYandexSiteVerificationKey from LocalSettings
	 */
	private function addYandexSiteVerification() {
		$yandexSiteVerificationKey = $this->getConfigValue( 'YandexSiteVerificationKey' );

		if ( $yandexSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'yandex-verification', $yandexSiteVerificationKey );
		}
	}

	/**
	 * Add $wgAlexaSiteVerificationKey from LocalSettings
	 */
	private function addAlexaSiteVerification() {
		$alexaSiteVerificationKey = $this->getConfigValue( 'AlexaSiteVerificationKey' );

		if ( $alexaSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'alexaVerifyID', $alexaSiteVerificationKey );
		}
	}

	/**
	 * Add $wgPinterestSiteVerificationKey from LocalSettings
	 */
	private function addPinterestSiteVerification() {
		$pinterestSiteVerificationKey = $this->getConfigValue( 'PinterestSiteVerificationKey' );

		if ( $pinterestSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'p:domain_verify', $pinterestSiteVerificationKey );
		}
	}

	/**
	 * Add $wgNortonSiteVerificationKey from LocalSettings
	 */
	private function addNortonSiteVerification() {
		$nortonSiteVerificationKey = $this->getConfigValue( 'NortonSiteVerificationKey' );

		if ( $nortonSiteVerificationKey !== null ) {
			$this->outputPage->addMeta(
				'norton-safeweb-site-verification',
				$nortonSiteVerificationKey
			);
		}
	}

	/**
	 * Add $wgFacebookAppId from LocalSettings
	 */
	private function addFacebookAppId() {
		$facebookAppId = $this->getConfigValue( 'FacebookAppId' );

		if ( $facebookAppId !== null ) {
			$this->outputPage->addHeadItem(
				'fb:app_id', Html::element(
					'meta', [
					'property' => 'fb:app_id',
					'content' => $facebookAppId,
					]
				)
			);
		}
	}

	/**
	 * Add $wgFacebookAdmins from LocalSettings
	 */
	private function addFacebookAdmins() {
		$facebookAdmins = $this->getConfigValue( 'FacebookAdmins' );

		if ( $facebookAdmins !== null ) {
			$this->outputPage->addHeadItem(
				'fb:admins', Html::element(
					'meta', [
					'property' => 'fb:admins',
					'content' => $facebookAdmins,
					]
				)
			);
		}
	}

	/**
	 * Sets <link rel="alternate" href="url" hreflang="language-area"> elements
	 * Will add a link element for the current page if $wgWikiSeoDefaultLanguage is set
	 */
	private function addHrefLangs() {
		$language = $this->getConfigValue( 'WikiSeoDefaultLanguage' );

		if ( $language !== null && in_array( $language, Validator::$isoLanguageCodes, true ) ) {
			$this->outputPage->addHeadItem(
				$language, Html::element(
					'link', [
					'rel' => 'alternate',
					'href' => WikiSEO::protocolizeUrl(
						$this->outputPage->getTitle()->getFullURL(),
						$this->outputPage->getRequest()
					),
					'hreflang' => $language,
					]
				)
			);
		}

		foreach ( $this->metadata as $metaKey => $url ) {
			if ( strpos( $metaKey, 'hreflang' ) === false ) {
				continue;
			}

			$this->outputPage->addHeadItem(
				$metaKey, Html::element(
					'link', [
					'rel' => 'alternate',
					'href' => $url,
					'hreflang' => substr( $metaKey, 9 ),
					]
				)
			);
		}
	}
}
