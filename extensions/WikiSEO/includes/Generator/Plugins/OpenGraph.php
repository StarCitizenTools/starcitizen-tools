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

namespace MediaWiki\Extension\WikiSEO\Generator\Plugins;

use Html;
use MediaWiki\Extension\WikiSEO\Generator\AbstractBaseGenerator;
use MediaWiki\Extension\WikiSEO\Generator\GeneratorInterface;
use MediaWiki\Extension\WikiSEO\WikiSEO;
use OutputPage;

/**
 * OpenGraph metadata generator
 *
 * @package MediaWiki\Extension\WikiSEO\Generator\Plugins
 */
class OpenGraph extends AbstractBaseGenerator implements GeneratorInterface {
	protected static $htmlElementPropertyKey = 'property';
	protected static $htmlElementContentKey = 'content';

	/**
	 * Valid Tags for this generator
	 *
	 * @var array
	 */
	protected $tags = [
	'author',
	'description',
	'image',
	'image_width',
	'image_height',
	'image_alt',
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
	protected $conversions = [
	'image'        => 'og:image',
	'image_width'  => 'og:image:width',
	'image_height' => 'og:image:height',
	'image_alt'    => 'og:image:alt',

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

		if ( !isset( $this->metadata['image'] ) ) {
			$defaultImage = $this->getConfigValue( 'WikiSeoDefaultImage' );

			if ( $defaultImage !== null ) {
				$this->metadata['image'] = $defaultImage;
			}
		}

		$this->preprocessFileMetadata();
		$this->metadata['modified_time'] = $this->getRevisionTimestamp();

		if ( !isset( $this->metadata['published_time'] ) ) {
			$this->metadata['published_time'] = $this->metadata['modified_time'];
		}
	}

	/**
	 * Add the metadata to the OutputPage
	 *
	 * @return void
	 */
	public function addMetadata() {
		$this->addTitleMeta();
		$this->addSiteName();

		if ( $this->outputPage->getTitle() !== null ) {
			$url = $this->outputPage->getTitle()->getFullURL();

			$url = WikiSEO::protocolizeUrl( $url, $this->outputPage->getRequest() );

			$this->outputPage->addHeadItem(
				'og:url', Html::element(
					'meta', [
					self::$htmlElementPropertyKey => 'og:url',
					self::$htmlElementContentKey  => $url,
					]
				)
			);
		}

		foreach ( $this->tags as $tag ) {
			if ( array_key_exists( $tag, $this->metadata ) ) {
				$convertedTag = $this->conversions[$tag];

				$this->outputPage->addHeadItem(
					$convertedTag, Html::element(
						'meta', [
						self::$htmlElementPropertyKey => $convertedTag,
						self::$htmlElementContentKey  => $this->metadata[$tag]
						]
					)
				);
			}
		}
	}

	/**
	 * Add a title meta attribute to the output
	 *
	 * @return void
	 */
	protected function addTitleMeta() {
		$this->outputPage->addHeadItem(
			$this->titlePropertyName, Html::element(
				'meta', [
				self::$htmlElementPropertyKey => $this->titlePropertyName,
				self::$htmlElementContentKey => $this->outputPage->getHTMLTitle(),
				]
			)
		);
	}

	/**
	 * Add the sitename as og:site_name
	 *
	 * @return void
	 */
	private function addSiteName() {
		$sitename = $this->getConfigValue( 'Sitename' );

		if ( !isset( $this->metadata['site_name'] ) && $sitename !== null ) {
			$this->outputPage->addHeadItem(
				'og:site_name', Html::element(
					'meta', [
					self::$htmlElementPropertyKey => 'og:site_name',
					self::$htmlElementContentKey => $sitename,
					]
				)
			);
		}
	}
}
