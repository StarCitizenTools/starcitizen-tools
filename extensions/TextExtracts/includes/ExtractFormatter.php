<?php

namespace TextExtracts;

use Config;
use HtmlFormatter\HtmlFormatter;
use Exception;

/**
 * Provides text-only or limited-HTML extracts of page HTML
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class ExtractFormatter extends HtmlFormatter {
	const SECTION_MARKER_START = "\1\2";
	const SECTION_MARKER_END = "\2\1";

	private $plainText;

	/**
	 * @param string $text: Text to convert
	 * @param bool $plainText: Whether extract should be plaintext
	 * @param Config $config
	 */
	public function __construct( $text, $plainText, Config $config ) {
		parent::__construct( HtmlFormatter::wrapHTML( $text ) );
		$this->plainText = $plainText;

		$this->setRemoveMedia( true );
		$this->remove( $config->get( 'ExtractsRemoveClasses' ) );

		if ( $plainText ) {
			$this->flattenAllTags();
		} else {
			$this->flatten( array( 'a' ) );
		}
	}

	public function getText( $dummy = null ) {
		$this->filterContent();
		$text = parent::getText();
		if ( $this->plainText ) {
			$text = html_entity_decode( $text );
			$text = str_replace( "\xC2\xA0", ' ', $text ); // replace nbsp with space
			$text = str_replace( "\r", "\n", $text ); // for Windows
			$text = preg_replace( "/\n{3,}/", "\n\n", $text ); // normalise newlines
		}
		return $text;
	}

	public function onHtmlReady( $html ) {
		if ( $this->plainText ) {
			$html = preg_replace( '/\s*(<h([1-6])\b)/i',
				"\n\n" . self::SECTION_MARKER_START . '$2' . self::SECTION_MARKER_END . '$1' ,
				$html
			);
		}
		return $html;
	}

	/**
	 * Returns no more than the given number of sentences
	 *
	 * @param string $text
	 * @param int $requestedSentenceCount
	 * @return string
	 */
	public static function getFirstSentences( $text, $requestedSentenceCount ) {
		// Based on code from OpenSearchXml by Brion Vibber
		$endchars = array(
			'[^\p{Lu}]\.(?:[ \n]|$)', '[\!\?](?:[ \n]|$)', // regular ASCII
			'。', // full-width ideographic full-stop
			'．', '！', '？', // double-width roman forms
			'｡', // half-width ideographic full stop
			);

		$endgroup = implode( '|', $endchars );
		$end = "(?:$endgroup)";
		$sentence = ".+?$end+";
		$requestedSentenceCount = intval( $requestedSentenceCount );
		$regexp = "/^($sentence){1,{$requestedSentenceCount}}/u";
		$matches = array();
		$res = preg_match( $regexp, $text, $matches );
		if( $res ) {
			$text = trim( $matches[0] );
		} else {
			if ( $res === false ) {
				throw new Exception( __METHOD__ . "() error compiling regular expression $regexp" );
			}
			// Just return the first line
			$lines = explode( "\n", $text );
			$text = trim( $lines[0] );
		}
		return $text;
	}

	/**
	 * Returns no more than a requested number of characters, preserving words
	 *
	 * @param string $text
	 * @param int $requestedLength
	 * @return string
	 */
	public static function getFirstChars( $text, $requestedLength ) {
		$length = mb_strlen( $text );
		if ( $length <= $requestedLength ) {
			return $text;
		}
		$pattern = "#^.{{$requestedLength}}[\\w/]*>?#su";
		preg_match( $pattern, $text, $m );
		return $m[0];
	}

	/**
	 * Removes content we've chosen to remove then removes class and style
	 * attributes from the remaining span elements.
	 *
	 * @return array Array of removed DOMElements
	 */
	public function filterContent() {
		$removed = parent::filterContent();

		$doc = $this->getDoc();
		$spans = $doc->getElementsByTagName( 'span' );

		foreach ( $spans as $span ) {
			$span->removeAttribute( 'class' );
			$span->removeAttribute( 'style' );
		}

		return $removed;
	}
}
