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
use OutputPage;

/**
 * Basic metadata tag generator
 * Adds metadata for description, keywords and robots
 *
 * @package MediaWiki\Extension\WikiSEO\Generator
 */
class MetaTag implements GeneratorInterface {
	private static $tags = [ 'description', 'keywords', 'robots', 'google_bot' ];

	/**
	 * @var array
	 */
	private $metadata;

	/**
	 * @var OutputPage
	 */
	private $outputPage;

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

		foreach ( self::$tags as $tag ) {
			if ( array_key_exists( $tag, $this->metadata ) ) {
				$this->outputPage->addMeta( $tag, $this->metadata[$tag] );
			}
		}
	}

	/**
	 * Add $wgGoogleSiteVerificationKey from LocalSettings
	 */
	private function addGoogleSiteVerification() {
		global $wgGoogleSiteVerificationKey;

		if ( $wgGoogleSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'google-site-verification', $wgGoogleSiteVerificationKey );
		}
	}

	/**
	 * Add $wgBingSiteVerificationKey from LocalSettings
	 */
	private function addBingSiteVerification() {
		global $wgBingSiteVerificationKey;

		if ( $wgBingSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'msvalidate.01', $wgBingSiteVerificationKey );
		}
	}

	/**
	 * Add $wgYandexSiteVerificationKey from LocalSettings
	 */
	private function addYandexSiteVerification() {
		global $wgYandexSiteVerificationKey;

		if ( $wgYandexSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'yandex-verification', $wgYandexSiteVerificationKey );
		}
	}

	/**
	 * Add $wgAlexaSiteVerificationKey from LocalSettings
	 */
	private function addAlexaSiteVerification() {
		global $wgAlexaSiteVerificationKey;

		if ( $wgAlexaSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'alexaVerifyID', $wgAlexaSiteVerificationKey );
		}
	}

	/**
	 * Add $wgPinterestSiteVerificationKey from LocalSettings
	 */
	private function addPinterestSiteVerification() {
		global $wgPinterestSiteVerificationKey;

		if ( $wgPinterestSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'p:domain_verify', $wgPinterestSiteVerificationKey );
		}
	}

	/**
	 * Add $wgNortonSiteVerificationKey from LocalSettings
	 */
	private function addNortonSiteVerification() {
		global $wgNortonSiteVerificationKey;

		if ( $wgNortonSiteVerificationKey !== null ) {
			$this->outputPage->addMeta( 'norton-safeweb-site-verification', $wgNortonSiteVerificationKey );
		}
	}

	/**
	 * Add $wgFacebookAppId from LocalSettings
	 */
	private function addFacebookAppId() {
		global $wgFacebookAppId;

		if ( $wgFacebookAppId !== null ) {
			$this->outputPage->addHeadItem( 'fb:app_id', Html::element( 'meta', [
				'property' => 'fb:app_id',
				'content'  => $wgFacebookAppId
			] ) );
		}
	}
}
