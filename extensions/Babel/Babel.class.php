<?php
/**
 * Contains main code.
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

use MediaWiki\Babel\BabelBox\LanguageBabelBox;
use MediaWiki\Babel\BabelBox\NotBabelBox;
use MediaWiki\Babel\BabelBox\NullBabelBox;

/**
 * Main class for the Babel extension.
 */
class Babel {
	/**
	 * @var Title
	 */
	protected static $title;

	/**
	 * Render the Babel tower.
	 *
	 * @param Parser $parser
	 * @param string [$parameter,...]
	 * @return string Babel tower.
	 */
	public static function Render( Parser $parser ) {
		global $wgBabelUseUserLanguage;
		$parameters = func_get_args();
		array_shift( $parameters );
		self::$title = $parser->getTitle();

		self::mTemplateLinkBatch( $parameters );

		$parser->getOutput()->addModuleStyles( 'ext.babel' );

		$content = self::mGenerateContentTower( $parser, $parameters );

		if ( preg_match( '/^plain\s*=\s*\S/', reset( $parameters ) ) ) {
			return $content;
		}

		if ( $wgBabelUseUserLanguage ) {
			$uiLang = $parser->getOptions()->getUserLangObj();
		} else {
			$uiLang = self::$title->getPageLanguage();
		}

		$top = wfMessage( 'babel', self::$title->getDBkey() )->inLanguage( $uiLang );

		if ( $top->isDisabled() ) {
			$top = '';
		} else {
			$top = $top->text();
			$url = wfMessage( 'babel-url' )->inContentLanguage();
			if ( !$url->isDisabled() ) {
				$top = '[[' . $url->text() . '|' . $top . ']]';
			}
			$top = '! class="mw-babel-header" | ' . $top;
		}
		$footer = wfMessage( 'babel-footer', self::$title->getDBkey() )->inLanguage( $uiLang );

		$url = wfMessage( 'babel-footer-url' )->inContentLanguage();
		$showfooter = '';
		if ( !$footer->isDisabled() && !$url->isDisabled() ) {
			$showfooter = '! class="mw-babel-footer" | [[' .
				$url->text() . '|' . $footer->text() . ']]';
		}
		$spacing = Babel::mCssAttrib( 'border-spacing', 'babel-box-cellspacing', true );
		$padding = Babel::mCssAttrib( 'padding', 'babel-box-cellpadding', true );

		if ( $spacing === '' ) {
			$style = ( $padding === '' ) ? '' : ( 'style="' . $padding . '"' );
		} else {
			$style = ( $padding === '' ) ?
				'style="' . $spacing . '"' :
				'style="' . $padding . ' ' . $spacing . '"';
		}

		$tower = <<<EOT
{|$style class="mw-babel-wrapper"
$top
|-
| $content
|-
$showfooter
|}
EOT;

		return $tower;
	}

	/**
	 * @param Parser $parser
	 * @param string[] $parameters
	 *
	 * @return string Wikitext
	 */
	private static function mGenerateContentTower( Parser $parser, array $parameters ) {
		$content = '';
		$templateParameters = []; // collects name=value parameters to be passed to wiki templates.

		foreach ( $parameters as $name ) {
			if ( strpos( $name, '=' ) !== false ) {
				$templateParameters[] = $name;
				continue;
			}

			$content .= self::mGenerateContent( $parser, $name, $templateParameters );
		}

		return $content;
	}

	/**
	 * @param Parser $parser
	 * @param string $name
	 * @param string[] $templateParameters
	 *
	 * @return string Wikitext
	 */
	private static function mGenerateContent( Parser $parser, $name, array $templateParameters ) {
		$createCategories = !$parser->getOptions()->getIsPreview();
		$components = self::mParseParameter( $name );
		$template = wfMessage( 'babel-template', $name )->inContentLanguage()->text();

		if ( $name === '' ) {
			$box = new NullBabelBox();
		} elseif ( $components !== false ) {
			// Valid parameter syntax (with lowercase language code), babel box
			$box = new LanguageBabelBox(
				self::$title,
				$components['code'],
				$components['level'],
				$createCategories
			);
		} elseif ( self::mPageExists( $template ) ) {
			// Check for an existing template
			$templateParameters[0] = $template;
			$template = implode( '|', $templateParameters );
			$box = new NotBabelBox(
				self::$title->getPageLanguage()->getDir(),
				$parser->replaceVariables( "{{{$template}}}" )
			);
		} elseif ( self::mValidTitle( $template ) ) {
			// Non-existing page, so try again as a babel box,
			// with converting the code to lowercase
			$components2 = self::mParseParameter( $name, /* code to lowercase */
				true );
			if ( $components2 !== false ) {
				$box = new LanguageBabelBox(
					self::$title,
					$components2['code'],
					$components2['level'],
					$createCategories
				);
			} else {
				// Non-existent page and invalid parameter syntax, red link.
				$box = new NotBabelBox(
					self::$title->getPageLanguage()->getDir(),
					'[[' . $template . ']]'
				);
			}
		} else {
			// Invalid title, output raw.
			$box = new NotBabelBox(
				self::$title->getPageLanguage()->getDir(),
				$template
			);
		}

		return $box->render();
	}

	/**
	 * Performs a link batch on a series of templates.
	 *
	 * @param string[] $parameters Templates to perform the link batch on.
	 */
	protected static function mTemplateLinkBatch( array $parameters ) {
		$titles = [];
		foreach ( $parameters as $name ) {
			$title = Title::newFromText( wfMessage( 'babel-template', $name )->inContentLanguage()->text() );
			if ( is_object( $title ) ) {
				$titles[] = $title;
			}
		}

		$batch = new LinkBatch( $titles );
		$batch->setCaller( __METHOD__ );
		$batch->execute();
	}

	/**
	 * Identify whether or not a page exists.
	 *
	 * @param string $name Name of the page to check.
	 * @return bool Indication of whether the page exists.
	 */
	protected static function mPageExists( $name ) {
		$titleObj = Title::newFromText( $name );

		return ( is_object( $titleObj ) && $titleObj->exists() );
	}

	/**
	 * Identify whether or not the passed string would make a valid page name.
	 *
	 * @param string $name Name of page to check.
	 * @return bool Indication of whether or not the title is valid.
	 */
	protected static function mValidTitle( $name ) {
		$titleObj = Title::newFromText( $name );

		return is_object( $titleObj );
	}

	/**
	 * Parse a parameter, getting a language code and level.
	 *
	 * @param string $parameter Parameter.
	 * @param bool $strtolower Whether to convert the language code to lowercase
	 * @return array [ 'code' => xx, 'level' => xx ]
	 */
	protected static function mParseParameter( $parameter, $strtolower = false ) {
		global $wgBabelDefaultLevel, $wgBabelCategoryNames;
		$return = [];

		$babelcode = $strtolower ? strtolower( $parameter ) : $parameter;
		// Try treating the paramter as a language code (for default level).
		$code = BabelLanguageCodes::getCode( $babelcode );
		if ( $code !== false ) {
			$return['code'] = $code;
			$return['level'] = $wgBabelDefaultLevel;
			return $return;
		}
		// Try splitting the paramter in to language and level, split on last hyphen.
		$lastSplit = strrpos( $parameter, '-' );
		if ( $lastSplit === false ) {
			return false;
		}
		$code = substr( $parameter, 0, $lastSplit );
		$level = substr( $parameter, $lastSplit + 1 );

		$babelcode = $strtolower ? strtolower( $code ) : $code;
		// Validate code.
		$return['code'] = BabelLanguageCodes::getCode( $babelcode );
		if ( $return['code'] === false ) {
			return false;
		}
		// Validate level.
		$level = strtoupper( $level );
		if ( !isset( $wgBabelCategoryNames[$level] ) ) {
			return false;
		}
		$return['level'] = $level;

		return $return;
	}

	/**
	 * Determine a CSS attribute, such as "border-spacing", from a localizeable message.
	 *
	 * @param string $name Name of CSS attribute.
	 * @param string $key Message key of attribute value.
	 * @param bool $assumeNumbersArePixels If true, treat numbers values as pixels;
	 *  otherwise, keep values as is (default: false).
	 * @todo Move this function to a more appropriate place, likely outside the class.
	 * @return Message|string
	 */
	public static function mCssAttrib( $name, $key, $assumeNumbersArePixels = false ) {
		$value = wfMessage( $key )->inContentLanguage();
		if ( $value->isDisabled() ) {
			$value = '';
		} else {
			$value = htmlentities( $value->text(), ENT_COMPAT, 'UTF-8' );
			if ( $assumeNumbersArePixels && is_numeric( $value ) && $value !== "0" ) {
				// Compatibility: previous babel-box-cellpadding and
				// babel-box-cellspacing entries were in HTML, not CSS
				// and so used numbers without unity as pixels.
				$value .= 'px';
			}
			$value = ' ' . $name . ': ' . $value . ';';
		}

		return $value;
	}

	/**
	 * Determine an HTML attribute, such as "cellspacing" or "title", from a localizeable message.
	 *
	 * @param string $name Name of HTML attribute.
	 * @param string $key Message key of attribute value.
	 * TODO: move this function to a more appropriate place, likely outside the class.
	 *       or consider to deprecate it as it's not used anymore.
	 * @return Message|string
	 */
	protected static function mHtmlAttrib( $name, $key ) {
		$value = wfMessage( $key )->inContentLanguage();
		if ( $value->isDisabled() ) {
			$value = '';
		} else {
			$value = ' ' . $name . '="' . htmlentities( $value->text(), ENT_COMPAT, 'UTF-8' ) .
				'"'; // must get rid of > and " inside value
		}

		return $value;
	}

	/**
	 * Gets the list of languages a user has set up with Babel
	 *
	 * TODO Can be done much smarter, e.g. by saving the languages in the DB and getting them there
	 * TODO There could be an API module that returns the result of this function
	 *
	 * @param User $user
	 * @param string $level Minimal level as given in $wgBabelCategoryNames
	 * @return string[] List of language codes
	 *
	 * @since Version 1.9.0
	 */
	public static function getUserLanguages( User $user, $level = null ) {
		// Right now the function only returns something if the user is categorized appropriately
		// (as defined by the $wgBabelMainCategory setting). If categorization is off, this function
		// will return an empty array.
		// If Babel would save the languages of the user in a Database table, this workaround using
		// the categories would not be needed.
		global $wgBabelMainCategory;
		// If Babel is not configured as required, return nothing.
		// Note also that "Set to false to disable main category".
		if ( $wgBabelMainCategory === false ) {
			return [];
		}

		// The string we construct here will be a pony, it will not be a valid category
		$babelCategoryTitle = Title::makeTitle( NS_CATEGORY, $wgBabelMainCategory );
		// Quote everything to avoid unexpected matches due to parenthesis form
		// It is not necessary to quote any additional chars except the special chars for the regex
		// and perhaps the limiting char, but that should not be respected as anything other than
		// edge delimiter.
		$babelCategoryString = preg_quote( $babelCategoryTitle->getPrefixedDBkey(), '/' );
		// Look for the %code% inside the string and put a group match in the same place
		// This will only work if the previous works so the string isn't misinterpreted as a regular
		// expression itself
		$codeRegex = '/^' . preg_replace( '/%code%/', '(.+?)(-([0-5N]))?', $babelCategoryString ) . '$/';

		$categories = array_keys( $user->getUserPage()->getParentCategories() );

		// We sort on proficiency level
		$result = [];
		foreach ( $categories as $category ) {
			// Only process categories that matches, $match will be created if necessary
			$res = preg_match( $codeRegex, $category, $match );
			if ( $res ) {
				// lowercase the first char, but stay away from the others in case of region codes
				$code = BabelLanguageCodes::getCode( lcfirst( $match[1] ) );
				if ( $code !== false ) {
					$result[$code] = isset( $match[3] ) ? $match[3] : 'N';
				}
			}
		}

		if ( isset( $level ) ) {
			$level = (string)$level;
			// filter down the set, note that this uses a text sort!
			$result = array_filter(
				$result,
				function ( $value ) use ( $level ) {
					return ( strcmp( $value, $level ) >= 0 );
				}
			);
			// sort and retain keys
			uasort(
				$result,
				function ( $a, $b ) {
					return -strcmp( $a, $b );
				}
			);
		}

		return array_keys( $result );
	}
}
