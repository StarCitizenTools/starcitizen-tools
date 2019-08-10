<?php

namespace Octfx\WikiSEO;

use Octfx\WikiSEO\Generator\GeneratorInterface;
use Octfx\WikiSEO\Generator\MetaTag;
use OutputPage;
use Parser;
use PPFrame;

class WikiSEO {
	/**
	 * prepend, append or replace the new title to the existing title
	 *
	 * @var string
	 */
	private $titleMode = 'replace';

	/**
	 * the separator to use when using append or prepend modes
	 *
	 * @var string
	 */
	private $titleSeparator = ' - ';

	/**
	 * @var string[] Array with generator names
	 */
	private $generators;

	/**
	 * @var GeneratorInterface[]
	 */
	private $generatorInstances = [];

	/**
	 * @var string[] Possible error messages
	 */
	private $errors = [];

	/**
	 * @var array
	 */
	private $metadataArray;

	/**
	 * WikiSEO constructor.
	 * Loads generator names from LocalSettings
	 */
	public function __construct() {
		global $wgMetadataGenerators;

		$this->generators = $wgMetadataGenerators;
	}

	/**
	 * Set an array with metadata key value pairs
	 *
	 * @param array $metadataArray
	 */
	public function setMetadataArray( array $metadataArray ) {
		$this->metadataArray = $metadataArray;
	}

	/**
	 * Add the metadata array as meta tags to the page
	 *
	 * @param OutputPage $out
	 */
	public function addMetadataToPage( OutputPage $out ) {
		$this->modifyPageTitle( $out );
		$this->instantiateMetadataPlugins();

		foreach ( $this->generatorInstances as $generatorInstance ) {
			$generatorInstance->init( $this->metadataArray, $out );
			$generatorInstance->addMetadata();
		}
	}

	/**
	 * Instantiates the metadata generators from $wgMetadataGenerators
	 */
	private function instantiateMetadataPlugins() {
		$this->generatorInstances[] = new MetaTag();

		foreach ( $this->generators as $generator ) {
			$classPath = "Octfx\\WikiSEO\\Generator\\Plugins\\$generator";

			if ( !class_exists( $classPath ) ) {
				$this->errors[] = wfMessage( 'wiki-seo-invalid-generator', $generator )->parse();
			} else {
				$this->generatorInstances[] = new $classPath();
			}
		}
	}

	/**
	 * @return string Error | Metadata Html content
	 */
	private function makeHtmlOutput() {
		if ( empty( $this->metadataArray ) ) {
			$this->errors[] = wfMessage( 'wiki-seo-empty-attr' );

			return $this->makeErrorHtml();
		}

		return $this->makeMetadataHtml();
	}

	/**
	 * @return string Concatenated error strings
	 */
	private function makeErrorHtml() {
		$text = implode( '<br>', $this->errors );

		return "<div class='errorbox'>{$text}</div>";
	}

	/**
	 * Renders the parameters as HTML comment tags in order to cache them in the Wiki text.
	 *
	 * When MediaWiki caches pages it does not cache the contents of the <head> tag, so
	 * to propagate the information in cached pages, the information is stored
	 * as HTML comments in the Wiki text.
	 *
	 * @return string A HTML string of comments
	 */
	private function makeMetadataHtml() {
		$validator = new Validator();

		$data = '';

		foreach ( $validator->validateParams( $this->metadataArray ) as $k => $v ) {
			if ( !empty( $v ) ) {
				$data .= sprintf( "WikiSEO:%s;%s\n", $k, base64_encode( $v ) );
			}
		}

		return sprintf( "<!--wiki-seo-data-start\n%swiki-seo-data-end-->", $data );
	}

	/**
	 * Modifies the page title based on 'titleMode'
	 *
	 * @param OutputPage $out
	 */
	private function modifyPageTitle( OutputPage $out ) {
		if ( !array_key_exists( 'title', $this->metadataArray ) ) {
			return;
		}

		$metaTitle = $this->metadataArray['title'];

		if ( array_key_exists( 'title_separator', $this->metadataArray ) ) {
			$this->titleSeparator = html_entity_decode( $this->metadataArray['title_separator'] );
		}

		if ( array_key_exists( 'title_mode', $this->metadataArray ) ) {
			$this->titleMode = $this->metadataArray['title_mode'];
		}

		switch ( $this->titleMode ) {
			case 'append':
				$pageTitle = sprintf( '%s%s%s', $out->getPageTitle(), $this->titleSeparator, $metaTitle );
				break;
			case 'prepend':
				$pageTitle = sprintf( '%s%s%s', $metaTitle, $this->titleSeparator, $out->getPageTitle() );
				break;
			case 'replace':
			default:
				$pageTitle = $metaTitle;
		}

		$pageTitle = preg_replace( "/\r|\n/", '', $pageTitle );

		$out->setHTMLTitle( $pageTitle );
	}

	/**
	 * Parse the values input from the <seo> tag extension
	 *
	 * @param string $input The text content of the tag
	 * @param array $args The HTML attributes of the tag
	 * @param Parser $parser The active Parser instance
	 * @param PPFrame $frame
	 *
	 * @return string The HTML comments of cached attributes
	 */
	public static function fromTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		$seo = new WikiSEO();
		$tagParser = new TagParser();

		$parsedInput = $tagParser->parseText( $input );
		$seo->setMetadataArray( $tagParser->expandWikiTextTagArray( $parsedInput, $parser, $frame ) );

		return $seo->makeHtmlOutput();
	}

	/**
	 * Parse the values input from the {{#seo}} parser function
	 *
	 * @param Parser $parser The active Parser instance
	 * @param PPFrame $frame Frame
	 * @param array $args Arguments
	 *
	 * @return array Parser options and the HTML comments of cached attributes
	 */
	public static function fromParserFunction( Parser $parser, PPFrame $frame, array $args ) {
		$expandedArgs = [];

		foreach ( $args as $arg ) {
			$expandedArgs[] = trim( $frame->expand( $arg ) );
		}

		$seo = new WikiSEO();
		$tagParser = new TagParser();

		$seo->setMetadataArray( $tagParser->parseArgs( $expandedArgs ) );

		return [ $seo->makeHtmlOutput(), 'noparse' => true, 'isHTML' => true ];
	}
}
