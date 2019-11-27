<?php

/**
 * @file
 * @license GPL-2.0-or-later
 */

use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Sanitizer\FontFeatureValuesAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\KeyframesAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\MediaAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\NamespaceAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\PageAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\Sanitizer;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;
use Wikimedia\CSS\Sanitizer\StyleRuleSanitizer;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;
use Wikimedia\CSS\Sanitizer\SupportsAtRuleSanitizer;

/**
 * TemplateStyles extension hooks
 */
class TemplateStylesHooks {

	/** @var Config|null */
	private static $config = null;

	/** @var Sanitizer[] */
	private static $sanitizers = [];

	/**
	 * Get our Config
	 * @return Config
	 * @codeCoverageIgnore
	 */
	public static function getConfig() {
		if ( !self::$config ) {
			self::$config = \MediaWiki\MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'templatestyles' );
		}
		return self::$config;
	}

	/**
	 * Get our Sanitizer
	 * @param string $class Class to limit selectors to
	 * @return Sanitizer
	 */
	public static function getSanitizer( $class ) {
		if ( !isset( self::$sanitizers[$class] ) ) {
			$config = self::getConfig();
			$matcherFactory = new TemplateStylesMatcherFactory(
				$config->get( 'TemplateStylesAllowedUrls' )
			);

			$propertySanitizer = new StylePropertySanitizer( $matcherFactory );
			$propertySanitizer->setKnownProperties( array_diff_key(
				$propertySanitizer->getKnownProperties(),
				array_flip( $config->get( 'TemplateStylesPropertyBlacklist' ) )
			) );
			Hooks::run( 'TemplateStylesPropertySanitizer', [ &$propertySanitizer, $matcherFactory ] );

			$atRuleBlacklist = array_flip( $config->get( 'TemplateStylesAtRuleBlacklist' ) );
			$ruleSanitizers = [
				'styles' => new StyleRuleSanitizer(
					$matcherFactory->cssSelectorList(),
					$propertySanitizer,
					[
						'prependSelectors' => [
							new Token( Token::T_DELIM, '.' ),
							new Token( Token::T_IDENT, $class ),
							new Token( Token::T_WHITESPACE ),
						],
					]
				),
				'@font-face' => new TemplateStylesFontFaceAtRuleSanitizer( $matcherFactory ),
				'@font-feature-values' => new FontFeatureValuesAtRuleSanitizer( $matcherFactory ),
				'@keyframes' => new KeyframesAtRuleSanitizer( $matcherFactory, $propertySanitizer ),
				'@page' => new PageAtRuleSanitizer( $matcherFactory, $propertySanitizer ),
				'@media' => new MediaAtRuleSanitizer( $matcherFactory->cssMediaQueryList() ),
				'@supports' => new SupportsAtRuleSanitizer( $matcherFactory, [
					'declarationSanitizer' => $propertySanitizer,
				] ),
			];
			$ruleSanitizers = array_diff_key( $ruleSanitizers, $atRuleBlacklist );
			if ( isset( $ruleSanitizers['@media'] ) ) { // In case @media was blacklisted
				$ruleSanitizers['@media']->setRuleSanitizers( $ruleSanitizers );
			}
			if ( isset( $ruleSanitizers['@supports'] ) ) { // In case @supports was blacklisted
				$ruleSanitizers['@supports']->setRuleSanitizers( $ruleSanitizers );
			}

			$allRuleSanitizers = $ruleSanitizers + [
				// Omit @import, it's not secure. Maybe someday we'll make an "@-mw-import" or something.
				'@namespace' => new NamespaceAtRuleSanitizer( $matcherFactory ),
			];
			$allRuleSanitizers = array_diff_key( $allRuleSanitizers, $atRuleBlacklist );
			$sanitizer = new StylesheetSanitizer( $allRuleSanitizers );
			Hooks::run( 'TemplateStylesStylesheetSanitizer',
				[ &$sanitizer, $propertySanitizer, $matcherFactory ]
			);
			self::$sanitizers[$class] = $sanitizer;
		}
		return self::$sanitizers[$class];
	}

	/**
	 * Update $wgTextModelsToParse
	 */
	public static function onRegistration() {
		// This gets called before ConfigFactory is set up, so I guess we need
		// to use globals.
		global $wgTextModelsToParse, $wgTemplateStylesAutoParseContent;

		if ( in_array( CONTENT_MODEL_CSS, $wgTextModelsToParse, true ) &&
			$wgTemplateStylesAutoParseContent
		) {
			$wgTextModelsToParse[] = 'sanitized-css';
		}
	}

	/**
	 * Add `<templatestyles>` to the parser.
	 * @param Parser &$parser Parser object being cleared
	 * @return bool
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setHook( 'templatestyles', 'TemplateStylesHooks::handleTag' );
		$parser->extTemplateStylesCache = new MapCacheLRU( 100 ); // 100 is arbitrary
		return true;
	}

	/**
	 * Fix Tidy screw-ups
	 *
	 * It seems some versions of Tidy try to wrap the contents of a `<style>`
	 * tag in bare `<![CDATA[` ... `]]>`, which makes it invalid CSS. It should
	 * be wrapping those additions with CSS comments.
	 *
	 * @todo When we kill Tidy in favor of RemexHTML or the like, kill this too.
	 * @param Parser &$parser Parser object being used
	 * @param string &$text text that will be returned
	 */
	public static function onParserAfterTidy( &$parser, &$text ) {
		$text = preg_replace( '/(<(?i:style)[^>]*>\s*)(<!\[CDATA\[)/', '$1/*$2*/', $text );
		$text = preg_replace( '/(\]\]>)(\s*<\/style>)/i', '/*$1*/$2', $text );
	}

	/**
	 * Set the default content model to 'sanitized-css' when appropriate.
	 * @param Title $title the Title in question
	 * @param string &$model The model name
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( $title, &$model ) {
		$enabledNamespaces = self::getConfig()->get( 'TemplateStylesNamespaces' );
		if ( !empty( $enabledNamespaces[$title->getNamespace()] ) &&
			$title->isSubpage() && substr( $title->getText(), -4 ) === '.css'
		) {
			$model = 'sanitized-css';
			return false;
		}
		return true;
	}

	/**
	 * Edit our CSS content model like core's CSS
	 * @param Title $title Title being edited
	 * @param string &$lang CodeEditor language to use
	 * @param string $model Content model
	 * @param string $format Content format
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( $title, &$lang, $model, $format ) {
		if ( $model === 'sanitized-css' && self::getConfig()->get( 'TemplateStylesUseCodeEditor' ) ) {
			$lang = 'css';
			return false;
		}
		return true;
	}

	/**
	 * Clear our cache when the parser is reset
	 * @param Parser $parser
	 */
	public static function onParserClearState( Parser $parser ) {
		$parser->extTemplateStylesCache->clear();
	}

	/**
	 * Parser hook for `<templatestyles>`
	 * @param string $text Contents of the tag (ignored).
	 * @param array $params Tag attributes
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML
	 */
	public static function handleTag( $text, $params, $parser, $frame ) {
		global $wgContLang;

		if ( self::getConfig()->get( 'TemplateStylesDisable' ) ) {
			return '';
		}

		if ( !isset( $params['src'] ) || trim( $params['src'] ) === '' ) {
			return '<strong class="error">' .
				wfMessage( 'templatestyles-missing-src' )->inContentLanguage()->parse() .
				'</strong>';
		}

		// Default to the Template namespace because that's the most likely
		// situation. We can't allow for subpage syntax like src="/styles.css"
		// or the like, though, because stuff like substing and Parsoid would
		// wind up wanting to make that relative to the wrong page.
		$title = Title::newFromText( $params['src'], NS_TEMPLATE );
		if ( !$title ) {
			return '<strong class="error">' .
				wfMessage( 'templatestyles-invalid-src' )->inContentLanguage()->parse() .
				'</strong>';
		}

		$rev = $parser->fetchCurrentRevisionOfTitle( $title );

		// It's not really a "template", but it has the same implications
		// for needing reparse when the stylesheet is edited.
		$parser->getOutput()->addTemplate( $title, $title->getArticleId(), $rev ? $rev->getId() : null );

		$content = $rev ? $rev->getContent() : null;
		if ( !$content ) {
			$title = $title->getPrefixedText();
			return '<strong class="error">' .
				wfMessage(
					'templatestyles-bad-src-missing',
					$title,
					wfEscapeWikiText( $title )
				)->inContentLanguage()->parse() .
				'</strong>';
		}
		if ( !$content instanceof TemplateStylesContent ) {
			$title = $title->getPrefixedText();
			return '<strong class="error">' .
				wfMessage(
					'templatestyles-bad-src',
					$title,
					wfEscapeWikiText( $title ),
					ContentHandler::getLocalizedName( $content->getModel() )
				)->inContentLanguage()->parse() .
				'</strong>';
		}

		// If the revision actually has an ID, cache based on that.
		// Otherwise, cache by hash.
		if ( $rev->getId() ) {
			$cacheKey = 'r' . $rev->getId();
		} else {
			$cacheKey = sha1( $content->getNativeData() );
		}

		// Include any non-default wrapper class in the cache key too
		$wrapClass = $parser->getOptions()->getWrapOutputClass();
		if ( $wrapClass === false ) { // deprecated
			$wrapClass = 'mw-parser-output';
		}
		if ( $wrapClass !== 'mw-parser-output' ) {
			$cacheKey .= '/' . $wrapClass;
		}

		// Already cached?
		if ( $parser->extTemplateStylesCache->has( $cacheKey ) ) {
			return $parser->extTemplateStylesCache->get( $cacheKey );
		}

		$status = $content->sanitize( [
			'flip' => $parser->getTargetLanguage()->getDir() !== $wgContLang->getDir(),
			'minify' => !ResourceLoader::inDebugMode(),
			'class' => $wrapClass,
		] );
		$style = $status->isOk() ? $status->getValue() : '/* Fatal error, no CSS will be output */';

		// Prepend errors. This should normally never happen, but might if an
		// update or configuration change causes something that was formerly
		// valid to become invalid or something like that.
		if ( !$status->isGood() ) {
			$comment = wfMessage(
				'templatestyles-errorcomment',
				$title->getPrefixedText(),
				$rev->getId(),
				$status->getWikiText( null, 'rawmessage' )
			)->text();
			$comment = trim( strtr( $comment, [
				// Use some lookalike unicode characters to avoid things that might
				// otherwise confuse browsers.
				'*' => '•', '-' => '‐', '<' => '⧼', '>' => '⧽',
			] ) );
			$style = "/*\n$comment\n*/\n$style";
		}

		// Hide the CSS from Parser::doBlockLevels
		$marker = Parser::MARKER_PREFIX . '-templatestyles-' .
			sprintf( '%08X', $parser->mMarkerIndex++ ) . Parser::MARKER_SUFFIX;
		$parser->mStripState->addNoWiki( $marker, $style );

		// Return the inline <style>, which the Parser will wrap in a 'general'
		// strip marker.
		$ret = Html::inlineStyle( $marker, 'all', [
			'data-mw-deduplicate' => "TemplateStyles:$cacheKey",
		] );
		$parser->extTemplateStylesCache->set( $cacheKey, $ret );
		return $ret;
	}

}
