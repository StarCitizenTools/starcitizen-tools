<?php

namespace Octfx\WikiSEO\Generator;

use Html;
use OutputPage;

/**
 * Basic metadata tag generator
 * Adds metadata for description, keywords and robots
 *
 * @package Octfx\WikiSEO\Generator
 */
class MetaTag implements GeneratorInterface {
	private static $tags = [ 'description', 'keywords', 'robots' ];

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