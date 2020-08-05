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

use ConfigException;
use Exception;
use File;
use InvalidArgumentException;
use MediaWiki\Extension\WikiSEO\WikiSEO;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RepoGroup;
use Title;

abstract class AbstractBaseGenerator {
	/**
	 * @var array
	 */
	protected $metadata;

	/**
	 * @var OutputPage
	 */
	protected $outputPage;

	/**
	 * Loads a config value for a given key from the main config
	 * Returns null on if an ConfigException was thrown
	 *
	 * @param string $key The config key
	 * @return mixed|null
	 */
	protected function getConfigValue( $key ) {
		try {
			$value = MediaWikiServices::getInstance()->getMainConfig()->get( $key );
		} catch ( ConfigException $e ) {
			wfLogWarning(
				sprintf(
					'Could not get config for "$wg%s". %s', $key,
					$e->getMessage()
				)
			);
			$value = null;
		}

		return $value;
	}

	/**
	 * Returns a file object by name, throws InvalidArgument if not found.
	 *
	 * @param string $name Filename
	 *
	 * @return File
	 *
	 * @throws InvalidArgumentException
	 */
	protected function getFileObject( string $name ) {
		// This should remove the namespace if present
		$nameSplit = explode( ':', $name );
		$name = array_pop( $nameSplit );

		$title = Title::newFromText( sprintf( 'File:%s', $name ) );

		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()
				->findFile( $title );
		} else {
			$file = RepoGroup::singleton()->getLocalRepo()->findFile( $title );
		}

		if ( $file === false ) {
			throw new InvalidArgumentException( sprintf( 'File %s not found.', $name ) );
		}

		return $file;
	}

	/**
	 * Generate file metadata from a local file object
	 *
	 * @param File $file
	 *
	 * @return array
	 */
	protected function getFileInfo( File $file ) {
		$cacheHash =
		'?version=' . md5( $file->getTimestamp() . $file->getWidth() . $file->getHeight() );
		$width = $file->getWidth();
		$height = $file->getHeight();
		$image = WikiSEO::protocolizeUrl( $file->getFullUrl(), $this->outputPage->getRequest() );

		return [
		'url' => $image . $cacheHash,
		'width' => $width,
		'height' => $height,
		];
	}

	/**
	 * If the metadata key 'image' is set, try to get file info of the local file
	 */
	protected function preprocessFileMetadata() {
		if ( isset( $this->metadata['image'] ) ) {
			try {
				$file = $this->getFileObject( $this->metadata['image'] );
				$info = $this->getFileInfo( $file );

				$this->metadata['image'] = $info['url'];
				$this->metadata['image_width'] = $info['width'];
				$this->metadata['image_height'] = $info['height'];
			} catch ( InvalidArgumentException $e ) {
				// File does not exist.
				// Maybe the user has set an URL, should we do something?
			}
		} else {
			if ( $this->getConfigValue( 'WikiSeoDisableLogoFallbackImage' ) === true ) {
				return;
			}

			try {
				$logo = MediaWikiServices::getInstance()->getMainConfig()->get( 'Logo' );
				$logo = wfExpandUrl( $logo );
				$this->metadata['image'] = $logo;
			} catch ( Exception $e ) {
				// We do nothing
			}
		}
	}

	/**
	 * Tries to load the current revision timestamp for the page or current timestamp if nothing
	 * could be found.
	 *
	 * @return bool|string
	 */
	protected function getRevisionTimestamp() {
		$timestamp = $this->outputPage->getRevisionTimestamp();

		// No cached timestamp, load it from the database
		if ( $timestamp === null ) {
			$timestamp =
			MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getKnownCurrentRevision(
					$this->outputPage->getTitle(),
					$this->outputPage->getRevisionId()
				);

			if ( $timestamp === false ) {
				   $timestamp = wfTimestampNow();
			} else {
				$timestamp = $timestamp->getTimestamp() ?? wfTimestampNow();
			}
		}

		return wfTimestamp( TS_ISO_8601, $timestamp );
	}
}
