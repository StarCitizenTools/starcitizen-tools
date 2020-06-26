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

use MediaWiki\MediaWikiServices;
use OutputPage;

/**
 * @property OutputPage $outputPage
 */
trait RevisionMetadataTrait {
	/**
	 * Tries to load the current revision timestamp for the page or current timestamp if nothing
	 * could be found.
	 *
	 * @return bool|string
	 */
	private function getRevisionTimestamp() {
		$timestamp = $this->outputPage->getRevisionTimestamp();

		// No cached timestamp, load it from the database
		if ( $timestamp === null ) {
			$timestamp =
				MediaWikiServices::getInstance()
					->getRevisionLookup()
					->getKnownCurrentRevision( $this->outputPage->getTitle(),
						$this->outputPage->getRevisionId() );

			if ( $timestamp === false ) {
				$timestamp = wfTimestampNow();
			} else {
				$timestamp = $timestamp->getTimestamp() ?? wfTimestampNow();
			}
		}

		return wfTimestamp( TS_ISO_8601, $timestamp );
	}
}
