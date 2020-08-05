<?php
/**
 * Contains code for language boxes.
 *
 * @file
 * @author Robert Leverington
 * @author Robin Pepermans
 * @author Niklas Laxström
 * @author Brian Wolff
 * @author Purodha Blissenbach
 * @author Sam Reed
 * @author Siebrand Mazeland
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Babel\BabelBox;

use BabelAutoCreate;
use BabelLanguageCodes;
use Language;
use MWException;
use Title;

/**
 * Class for babel language boxes.
 */
class LanguageBabelBox implements BabelBox {

	/**
	 * @var Title
	 */
	private $title;

	/**
	 * @var string
	 */
	private $code;

	/**
	 * @var string
	 */
	private $level;

	/**
	 * @var bool
	 */
	private $createCategories;

	/**
	 * Construct a babel box for the given language and level.
	 *
	 * @param Title $title
	 * @param string $code Language code to use.
	 *   This is a mediawiki-internal code (not necessarily a valid BCP-47 code)
	 * @param string $level Level of ability to use.
	 * @param bool $createCategories If true, creates non existing categories;
	 *  otherwise, doesn't create them.
	 */
	public function __construct( Title $title, $code, $level, $createCategories = true ) {
		$this->title = $title;
		$this->code = BabelLanguageCodes::getCode( $code ) ?? $code;
		$this->level = $level;
		$this->createCategories = $createCategories;
	}

	/**
	 * Return the babel box code.
	 *
	 * @return string A babel box for the given language and level.
	 */
	public function render() {
		$code = $this->code;
		$catCode = BabelLanguageCodes::getCategoryCode( $code );
		$bcp47 = BabelLanguageCodes::bcp47( $code );

		$portal = wfMessage( 'babel-portal', $catCode )->inContentLanguage()->text();
		if ( $portal !== '' ) {
			$portal = "[[$portal|$catCode]]";
		} else {
			$portal = $catCode;
		}
		$header = "$portal<span class=\"mw-babel-box-level-{$this->level}\">-{$this->level}</span>";

		$name = BabelLanguageCodes::getName( $code );
		$text = self::getText( $this->title, $name, $code, $this->level );

		$dir_current = Language::factory( $code )->getDir();

		$dir_head = $this->title->getPageLanguage()->getDir();

		$box = <<<EOT
<div class="mw-babel-box mw-babel-box-{$this->level}" dir="$dir_head">
{|
! dir="$dir_head" | $header
| dir="$dir_current" lang="$bcp47" | $text
|}
</div>
EOT;

		return $box;
	}

	/**
	 * Get the text to display in the language box for specific language and
	 * level.
	 *
	 * @param Title $title
	 * @param string $name
	 * @param string $code Mediawiki-internal language code of language to use.
	 * @param string $level Level to use.
	 * @return string Text for display, in wikitext format.
	 */
	private static function getText( Title $title, $name, $code, $level ) {
		global $wgBabelMainCategory, $wgBabelCategoryNames;

		if ( $wgBabelCategoryNames[$level] === false ) {
			$categoryLevel = ':' . $title->getFullText();
		} else {
			$categoryLevel = ':Category:' .
				self::getCategoryName( $wgBabelCategoryNames[$level], $code );
		}

		if ( $wgBabelMainCategory === false ) {
			$categoryMain = ':' . $title->getFullText();
		} else {
			$categoryMain = ':Category:' .
				self::getCategoryName( $wgBabelMainCategory, $code );
		}

		// Give grep a chance to find the usages:
		// babel-0-n, babel-1-n, babel-2-n, babel-3-n, babel-4-n, babel-5-n, babel-N-n
		$text = wfMessage( "babel-$level-n",
			$categoryLevel, $categoryMain, '', $title->getDBkey()
		)->inLanguage( $code )->text();

		$fallbackLanguage = Language::getFallbackFor( $code );
		$fallback = wfMessage( "babel-$level-n",
			$categoryLevel, $categoryMain, '', $title->getDBkey()
		)->inLanguage( $fallbackLanguage ?: $code )->text();

		// Give grep a chance to find the usages:
		// babel-0, babel-1, babel-2, babel-3, babel-4, babel-5, babel-N
		if ( $text == $fallback ) {
			$text = wfMessage( "babel-$level",
				$categoryLevel, $categoryMain, $name, $title->getDBkey()
			)->inLanguage( $code )->text();
		}

		return $text;
	}

	/**
	 * Generate categories for the language box.
	 *
	 * @return string[] [ category => sort key ]
	 */
	public function getCategories() {
		global $wgBabelMainCategory, $wgBabelCategoryNames, $wgBabelCategorizeNamespaces;

		$r = [];

		if (
			$wgBabelCategorizeNamespaces !== null &&
			!$this->title->inNamespaces( $wgBabelCategorizeNamespaces )
		) {
			return $r;
		}

		# Add main category
		if ( $wgBabelMainCategory !== false && $this->level !== '0' ) {
			$category = self::getCategoryName( $wgBabelMainCategory, $this->code );
			$r[$category] = $this->level;
			if ( $this->createCategories ) {
				BabelAutoCreate::create( $category, $this->code );
			}
		}

		# Add level category
		if ( $wgBabelCategoryNames[$this->level] !== false ) {
			$category = self::getCategoryName( $wgBabelCategoryNames[$this->level], $this->code );
			// Use default sort key
			$r[$category] = false;
			if ( $this->createCategories ) {
				BabelAutoCreate::create( $category, $this->code, $this->level );
			}
		}

		return $r;
	}

	/**
	 * Replace the placeholder variables from the category names configurtion
	 * array with actual values.
	 *
	 * @throws MWException if the category name is not a valid title
	 * @param string $category Category name (containing variables).
	 * @param string $code Mediawiki-internal language code of category.
	 * @return string Category name with variables replaced.
	 */
	private static function getCategoryName( $category, $code ) {
		global $wgLanguageCode;
		$category = strtr( $category, [
			'%code%' => BabelLanguageCodes::getCategoryCode( $code ),
			'%wikiname%' => BabelLanguageCodes::getName( $code, $wgLanguageCode ),
			'%nativename%' => BabelLanguageCodes::getName( $code )
		] );

		// Normalize using Title
		$title = Title::makeTitleSafe( NS_CATEGORY, $category );
		if ( !$title ) {
			throw new MWException( "Invalid babel category name '$category'" );
		}
		return $title->getDBkey();
	}

}
