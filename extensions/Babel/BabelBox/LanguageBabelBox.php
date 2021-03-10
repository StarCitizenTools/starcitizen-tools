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
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

namespace MediaWiki\Babel\BabelBox;

use Babel;
use BabelAutoCreate;
use BabelLanguageCodes;
use Language;
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
	 * @param string|int $level Level of ability to use.
	 * @param bool $createCategories If true, creates non existing categories;
	 *  otherwise, doesn't create them.
	 */
	public function __construct( Title $title, $code, $level, $createCategories = true ) {
		$this->title = $title;
		$this->code = wfBCP47( $code );
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

		$portal = wfMessage( 'babel-portal', $code )->inContentLanguage()->plain();
		if ( $portal !== '' ) {
			$portal = "[[$portal|$code]]";
		} else {
			$portal = $code;
		}
		$header = "$portal<span class=\"mw-babel-box-level-{$this->level}\">-{$this->level}</span>";

		$name = BabelLanguageCodes::getName( $code );
		$code = BabelLanguageCodes::getCode( $code );
		$text = self::getText( $this->title, $name, $code, $this->level );

		$dir_current = Language::factory( $code )->getDir();

		$spacing = Babel::mCssAttrib( 'border-spacing', 'babel-cellspacing', true );
		$padding = Babel::mCssAttrib( 'padding', 'babel-cellpadding', true );

		if ( $spacing === '' ) {
			$style = ( $padding === '' ) ? '' : ( 'style="' . $padding . '"' );
		} else {
			$style = ( $padding === '' ) ?
				'style="' . $spacing . '"' :
				'style="' . $padding . ' ' . $spacing . '"';
		}

		$dir_head = $this->title->getPageLanguage()->getDir();

		$box = <<<EOT
<div class="mw-babel-box mw-babel-box-{$this->level}" dir="$dir_head">
{|$style
! dir="$dir_head" | $header
| dir="$dir_current" lang="$code" | $text
|}
</div>
EOT;

		$box .= $this->generateCategories();

		return $box;
	}

	/**
	 * Get the text to display in the language box for specific language and
	 * level.
	 *
	 * @param Title $title
	 * @param string $name
	 * @param string $language Language code of language to use.
	 * @param string $level Level to use.
	 * @return string Text for display, in wikitext format.
	 */
	private static function getText( Title $title, $name, $language, $level ) {
		global $wgBabelMainCategory, $wgBabelCategoryNames;

		if ( $wgBabelCategoryNames[$level] === false ) {
			$categoryLevel = $title->getFullText();
		} else {
			$categoryLevel = ':Category:' .
				self::replaceCategoryVariables( $wgBabelCategoryNames[$level], $language );
		}

		if ( $wgBabelMainCategory === false ) {
			$categoryMain = $title->getFullText();
		} else {
			$categoryMain = ':Category:' .
				self::replaceCategoryVariables( $wgBabelMainCategory, $language );
		}

		// Give grep a chance to find the usages:
		// babel-0-n, babel-1-n, babel-2-n, babel-3-n, babel-4-n, babel-5-n, babel-N-n
		$text = wfMessage( "babel-$level-n",
			$categoryLevel, $categoryMain, '', $title->getDBkey()
		)->inLanguage( $language )->text();

		$fallbackLanguage = Language::getFallbackfor( $language );
		$fallback = wfMessage( "babel-$level-n",
			$categoryLevel, $categoryMain, '', $title->getDBkey()
		)->inLanguage( $fallbackLanguage ? $fallbackLanguage : $language )->text();

		// Give grep a chance to find the usages:
		// babel-0, babel-1, babel-2, babel-3, babel-4, babel-5, babel-N
		if ( $text == $fallback ) {
			$text = wfMessage( "babel-$level",
				$categoryLevel, $categoryMain, $name, $title->getDBkey()
			)->inLanguage( $language )->text();
		}

		return $text;
	}

	/**
	 * Generate categories for the language box.
	 *
	 * @return string Wikitext to add categories.
	 */
	private function generateCategories() {
		global $wgBabelMainCategory, $wgBabelCategoryNames;

		$r = '';

		# Add main category
		if ( $wgBabelMainCategory !== false ) {
			$category = self::replaceCategoryVariables( $wgBabelMainCategory, $this->code );
			$r .= "[[Category:$category|{$this->level}]]";
			if ( $this->createCategories ) {
				BabelAutoCreate::create( $category, $this->code );
			}
		}

		# Add level category
		if ( $wgBabelCategoryNames[$this->level] !== false ) {
			$category = self::replaceCategoryVariables( $wgBabelCategoryNames[$this->level], $this->code );
			$r .= "[[Category:$category]]";
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
	 * @param string $category Category name (containing variables).
	 * @param string $code Language code of category.
	 * @return string Category name with variables replaced.
	 */
	private static function replaceCategoryVariables( $category, $code ) {
		global $wgLanguageCode;
		$category = strtr( $category, [
			'%code%' => $code,
			'%wikiname%' => BabelLanguageCodes::getName( $code, $wgLanguageCode ),
			'%nativename%' => BabelLanguageCodes::getName( $code )
		] );

		return $category;
	}

}
