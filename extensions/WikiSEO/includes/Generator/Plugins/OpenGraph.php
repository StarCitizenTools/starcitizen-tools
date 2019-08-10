<?php

namespace Octfx\WikiSEO\Generator\Plugins;

use Html;
use Octfx\WikiSEO\Generator\GeneratorInterface;
use OutputPage;

/**
 * OpenGraph metadata generator
 *
 * @package Octfx\WikiSEO\Generator\Plugins
 */
class OpenGraph implements GeneratorInterface {
	protected static $htmlElementPropertyKey = 'property';
	protected static $htmlElementContentKey = 'content';

	/**
	 * Valid Tags for this generator
	 *
	 * @var array
	 */
	protected static $tags = [
		'author',
		'description',
		'image',
		'image_width',
		'image_height',
		'keywords',
		'locale',
		'modified_time',
		'published_time',
		'section',
		'site_name',
		'type',
	];

	/**
	 * Tag name conversions for this generator
	 *
	 * @var array
	 */
	protected static $conversions = [
		'image'        => 'og:image',
		'image_width'  => 'og:image:width',
		'image_height' => 'og:image:height',

		'locale'      => 'og:locale',
		'type'        => 'og:type',
		'site_name'   => 'og:site_name',
		'description' => 'og:description',

		'author'         => 'article:author',
		'modified_time'  => 'article:modified_time',
		'published_time' => 'article:published_time',
		'section'        => 'article:section',
		'keywords'       => 'article:tag',
	];

	/**
	 * Page title property name
	 *
	 * @var string
	 */
	protected $titlePropertyName = 'og:title';

	/**
	 * @var array
	 */
	protected $metadata;

	/**
	 * @var OutputPage
	 */
	protected $outputPage;

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
		$this->addTitleMeta();

		if ( $this->outputPage->getTitle() !== null ) {
			$this->outputPage->addHeadItem( 'og:url', Html::element( 'meta', [
				self::$htmlElementPropertyKey => 'og:url',
				self::$htmlElementContentKey  => $this->outputPage->getTitle()->getFullURL()
			] ) );
		}

		foreach ( static::$tags as $tag ) {
			if ( array_key_exists( $tag, $this->metadata ) ) {
				$convertedTag = static::$conversions[$tag];

				$this->outputPage->addHeadItem( $convertedTag, Html::element( 'meta', [
					self::$htmlElementPropertyKey => $convertedTag,
					self::$htmlElementContentKey  => $this->metadata[$tag]
				] ) );
			}
		}
	}

	/**
	 * Add a title meta attribute to the output
	 *
	 * @return void
	 */
	protected function addTitleMeta() {
		$this->outputPage->addHeadItem( $this->titlePropertyName, Html::element( 'meta', [
			self::$htmlElementPropertyKey => $this->titlePropertyName,
			self::$htmlElementContentKey  => $this->outputPage->getHTMLTitle()
		] ) );
	}
}