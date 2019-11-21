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

use ConfigException;
use Exception;
use InvalidArgumentException;
use MediaWiki\Extension\WikiSEO\Generator\GeneratorInterface;
use MediaWiki\Extension\WikiSEO\Generator\Plugins\FileMetadataTrait as FileMetadata;
use MediaWiki\Extension\WikiSEO\Generator\Plugins\RevisionMetadataTrait as RevisionMetadata;
use MediaWiki\Extension\WikiSEO\WikiSEO;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Title;

class SchemaOrg implements GeneratorInterface {
	use FileMetadata;
	use RevisionMetadata;

	/**
	 * Valid Tags for this generator
	 *
	 * @var array
	 */
	protected $tags = [
		'type',
		'description',
		'keywords',
		'modified_time',
		'published_time',
		'section'
	];

	/**
	 * Tag name conversions for this generator
	 *
	 * @var array
	 */
	protected $conversions = [
		'type' => '@type',

		'section' => 'articleSection',

		'published_time' => 'datePublished',
		'modified_time'  => 'dateModified'
	];

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

		$this->metadata['modified_time'] = $this->getRevisionTimestamp();

		if ( !isset( $this->metadata['published_time'] ) ) {
			$this->metadata['published_time'] = $this->metadata['modified_time'];
		}
	}

	/**
	 * @var array
	 */
	protected $metadata;

	/**
	 * @var OutputPage
	 */
	protected $outputPage;

	/**
	 * Add the metadata to the OutputPage
	 *
	 * @return void
	 */
	public function addMetadata() {
		$template = '<script type="application/ld+json">%s</script>';

		$meta = [
			'@context' => 'http://schema.org',
			'name'     => $this->outputPage->getHTMLTitle(),
			'headline' => $this->outputPage->getHTMLTitle(),
		];

		if ( $this->outputPage->getTitle() !== null ) {
			$url = $this->outputPage->getTitle()->getFullURL();

			$url = WikiSEO::protocolizeUrl( $url, $this->outputPage->getRequest() );

			$meta['identifier'] = $url;
			$meta['url'] = $url;
		}

		foreach ( $this->tags as $tag ) {
			if ( array_key_exists( $tag, $this->metadata ) ) {

				$convertedTag = $this->conversions[$tag] ?? $tag;

				$meta[$convertedTag] = $this->metadata[$tag];
			}
		}

		$meta['image'] = $this->getImageMetadata();
		$meta['author'] = $this->getAuthorMetadata();
		$meta['publisher'] = $this->getAuthorMetadata();
		$meta['potentialAction'] = $this->getSearchActionMetadata();

		$this->outputPage->addHeadItem( 'jsonld-metadata', sprintf( $template, json_encode( $meta ) ) );
	}

	/**
	 * Generate jsonld metadata from the wiki logo or supplied file name
	 *
	 * @return array
	 */
	private function getImageMetadata() {
		$data = [
			'@type' => 'ImageObject',
		];

		if ( isset( $this->metadata['image'] ) ) {
			$image = $this->metadata['image'];

			try {
				$file = $this->getFileObject( $image );

				return array_merge( $data, $this->getFileInfo( $file ) );
			} catch ( InvalidArgumentException $e ) {
				// Fallthrough
			}
		}

		try {
			$logo = MediaWikiServices::getInstance()->getMainConfig()->get( 'Logo' );
			$logo = wfExpandUrl( $logo );
			$data['url'] = $logo;
		} catch ( Exception $e ) {
			// Uh oh either there was a ConfigException or there was an error expanding the URL.
			// We'll bail out.
			$data = [];
		}

		return $data;
	}

	/**
	 * Add the sitename as the author
	 *
	 * @return array
	 */
	private function getAuthorMetadata() {
		try {
			$sitename = MediaWikiServices::getInstance()->getMainConfig()->get( 'Sitename' );
		} catch ( ConfigException $e ) {
			// Empty tags will be ignored
			$sitename = '';
		}

		return [
			'@type' => 'Organization',
			'name' => $sitename,
		];
	}

	/**
	 * Add search action metadata
	 * https://gitlab.com/hydrawiki/extensions/seo/blob/master/SEOHooks.php
	 *
	 * @return array
	 */
	private function getSearchActionMetadata() {
		$searchPage = Title::newFromText( 'Special:Search' );

		if ( $searchPage !== null ) {
			$search =
				$searchPage->getFullURL( [ 'search' => 'search_term' ], false,
					$this->outputPage->getRequest()->getProtocol() );
			$search = str_replace( 'search_term', '{search_term}', $search );

			return [
				'@type' => 'SearchAction',
				'target' => $search,
				'query-input' => 'required name=search_term',
			];
		}

		return [];
	}
}
