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

namespace MediaWiki\Extension\WikiSEO;

use ConfigException;
use MediaWiki\Extension\WikiSEO\Generator\GeneratorInterface;
use MediaWiki\Extension\WikiSEO\Generator\MetaTag;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Parser;
use ParserOutput;
use PPFrame;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use WebRequest;

class WikiSEO {
	private const MODE_TAG = 'tag';
	private const MODE_PARSER = 'parser';

	/**
	 * @var string $mode 'tag' or 'parser' used to determine the error message
	 */
	private $mode;

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
	private $metadata = [];

	/**
	 * WikiSEO constructor.
	 * Loads generator names from LocalSettings
	 *
	 * @param string $mode the parser mode
	 * @throws RuntimeException
	 */
	public function __construct( $mode = self::MODE_PARSER ) {
		if ( !function_exists( 'json_encode' ) ) {
			throw new RuntimeException( "WikiSEO required 'ext-json' to be installed." );
		}

		$this->setMetadataGenerators();

		$this->mode = $mode;
	}

	private function setMetadataGenerators() {
		try {
			$generators =
			MediaWikiServices::getInstance()->getMainConfig()->get( 'MetadataGenerators' );
		} catch ( ConfigException $e ) {
			wfLogWarning(
				sprintf(
					'Could not get config for "$wgMetadataGenerators", using default. %s',
					$e->getMessage()
				)
			);

			$generators = [
			'OpenGraph',
			'Twitter',
			'SchemaOrg',
			];
		}

		$this->generators = $generators;
	}

	/**
	 * Set the metadata by loading the page props from the db or the OutputPage object
	 *
	 * @param OutputPage $outputPage
	 */
	public function setMetadataFromPageProps( OutputPage $outputPage ) {
		if ( $outputPage->getTitle() === null ) {
			$this->errors[] = wfMessage( 'wiki-seo-missing-page-title' );

			return;
		}

		$pageId = $outputPage->getTitle()->getArticleID();

		$result =
		$this->loadPagePropsFromDb( $pageId ) ??
		$this->loadPagePropsFromOutputPage( $outputPage ) ?? [];

		$this->setMetadata( $result );
	}

	/**
	 * Loads all page props with pp_propname in Validator::$validParams
	 *
	 * @param int $pageId
	 * @return null | array Null if empty
	 * @see    Validator::$validParams
	 */
	private function loadPagePropsFromDb( int $pageId ) {
		$dbl = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$db = $dbl->getConnection( DB_REPLICA );

		$propValue = $db->select(
			'page_props', [ 'pp_propname', 'pp_value' ], [
			'pp_page' => $pageId,
			], __METHOD__
		);

		$result = null;

		if ( $propValue !== false ) {
			$result = [];

			foreach ( $propValue as $row ) {
                // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				$value = @unserialize( $row->pp_value, [ 'allowed_classes' => false ] );

				// Value was serialized
				if ( $value !== false ) {
					$result[$row->pp_propname] = $value;
				} else {
					$result[$row->pp_propname] = $row->pp_value;
				}
			}
		}

		return empty( $result ) ? null : $result;
	}

	/**
	 * Tries to load the page props from OutputPage with keys from Validator::$validParams
	 *
	 * @param OutputPage $page
	 * @return array|null
	 * @see    Validator::$validParams
	 */
	private function loadPagePropsFromOutputPage( OutputPage $page ) {
		$result = [];

		foreach ( Validator::$validParams as $param ) {
			$prop = $page->getProperty( $param );
			if ( $prop !== null ) {
                // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				$value = @unserialize( $prop, [ 'allowed_classes' => false ] );

				// Value was serialized
				if ( $value !== false ) {
					$prop = $value;
				}

				$result[$param] = $prop;
			}
		}

		return empty( $result ) ? null : $result;
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
			$generatorInstance->init( $this->metadata, $out );
			$generatorInstance->addMetadata();
		}
	}

	/**
	 * Set an array with metadata key value pairs
	 * Gets validated by Validator
	 *
	 * @param array $metadataArray
	 * @see   Validator
	 */
	private function setMetadata( array $metadataArray ) {
		$validator = new Validator();
		$validMetadata = [];

		foreach ( $validator->validateParams( $metadataArray ) as $k => $v ) {
			if ( !empty( $v ) ) {
				$validMetadata[$k] = $v;
			}
		}

		$this->metadata = $validMetadata;
	}

	/**
	 * Instantiates the metadata generators from $wgMetadataGenerators
	 */
	private function instantiateMetadataPlugins() {
		$this->generatorInstances[] = new MetaTag();

		foreach ( $this->generators as $generator ) {
			$classPath = "MediaWiki\\Extension\\WikiSEO\\Generator\\Plugins\\$generator";

			try {
				$class = new ReflectionClass( $classPath );
				$this->generatorInstances[] = $class->newInstance();
			} catch ( ReflectionException $e ) {
				$this->errors[] = wfMessage( 'wiki-seo-invalid-generator', $generator )->parse();
			}
		}
	}

	/**
	 * Finalize everything.
	 * Check for errors and save to props if everything is ok.
	 *
	 * @param ParserOutput $output
	 *
	 * @return string String with errors that happened or empty
	 */
	private function finalize( ParserOutput $output ) {
		if ( empty( $this->metadata ) ) {
			$message = sprintf( 'wiki-seo-empty-attr-%s', $this->mode );
			$this->errors[] = wfMessage( $message );

			return $this->makeErrorHtml();
		}

		$this->saveMetadataToProps( $output );

		return '';
	}

	/**
	 * @return string Concatenated error strings
	 */
	private function makeErrorHtml() {
		$text = implode( '<br>', $this->errors );

		return sprintf( '<div class="errorbox">%s</div>', $text );
	}

	/**
	 * Modifies the page title based on 'titleMode'
	 *
	 * @param OutputPage $out
	 */
	private function modifyPageTitle( OutputPage $out ) {
		if ( !array_key_exists( 'title', $this->metadata ) ) {
			return;
		}

		$metaTitle = $this->metadata['title'];

		if ( array_key_exists( 'title_separator', $this->metadata ) ) {
			$this->titleSeparator = $this->metadata['title_separator'];
		}

		if ( array_key_exists( 'title_mode', $this->metadata ) ) {
			$this->titleMode = $this->metadata['title_mode'];
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

		$pageTitle = preg_replace( "/[\r\n]/", '', $pageTitle );
		$pageTitle = html_entity_decode( $pageTitle, ENT_QUOTES );

		$out->setHTMLTitle( $pageTitle );
	}

	/**
	 * Save the metadata array json encoded to the page props table
	 *
	 * @param ParserOutput $outputPage
	 */
	private function saveMetadataToProps( ParserOutput $outputPage ) {
		foreach ( $this->metadata as $key => $value ) {
			if ( $outputPage->getProperty( $key ) === false ) {
				$outputPage->setProperty( $key, $value );
			}
		}
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
		$seo = new WikiSEO( self::MODE_TAG );
		$tagParser = new TagParser();

		$parsedInput = $tagParser->parseText( $input );
		$parsedInput = array_merge( $parsedInput, $args );
		$tags = $tagParser->expandWikiTextTagArray( $parsedInput, $parser, $frame );

		$seo->setMetadata( $tags );

		return $seo->finalize( $parser->getOutput() );
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

		$seo = new WikiSEO( self::MODE_PARSER );
		$tagParser = new TagParser();

		$seo->setMetadata( $tagParser->parseArgs( $expandedArgs ) );

		$fin = $seo->finalize( $parser->getOutput() );
		if ( !empty( $fin ) ) {
			return [
			$fin,
			'noparse' => true,
			'isHTML' => true,
			];
		}

		return [ '' ];
	}

	/**
	 * Add the server protocol to the URL if it is missing
	 *
	 * @param string $url URL from getFullURL()
	 * @param WebRequest $request
	 *
	 * @return string
	 */
	public static function protocolizeUrl( $url, WebRequest $request ) {
		if ( parse_url( $url, PHP_URL_SCHEME ) === null ) {
			$url = sprintf( '%s:%s', $request->getProtocol(), $url );
		}

		return $url;
	}
}
