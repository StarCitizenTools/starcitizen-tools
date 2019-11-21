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

use Exception;
use File;
use InvalidArgumentException;
use MediaWiki\Extension\WikiSEO\WikiSEO;
use MediaWiki\MediaWikiServices;
use RepoGroup;
use Title;

trait FileMetadataTrait {
	/**
	 * Returns a file object by name, throws InvalidArgument if not found.
	 *
	 * @param string $name Filename
	 *
	 * @return File
	 *
	 * @throws InvalidArgumentException
	 */
	private function getFileObject( string $name ) {
		// This should remove the namespace if present
		$nameSplit = explode( ':', $name );
		$name = array_pop( $nameSplit );

		$title = Title::newFromText( sprintf( 'File:%s', $name ) );

		$file = RepoGroup::singleton()->getLocalRepo()->findFile( $title );

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
	private function getFileInfo( File $file ) {
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
	private function preprocessFileMetadata() {
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
			try {
				$logo = MediaWikiServices::getInstance()->getMainConfig()->get( 'Logo' );
				$logo = wfExpandUrl( $logo );
				$this->metadata['image'] = $logo;
			} catch ( Exception $e ) {
				// We do nothing
			}
		}
	}
}
