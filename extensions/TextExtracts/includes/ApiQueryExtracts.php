<?php
/**
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

namespace TextExtracts;

use ApiBase;
use ApiMain;
use ApiQueryBase;
use BagOStuff;
use Config;
use FauxRequest;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWTidy;

use ParserOptions;
use Title;
use ApiUsageException;
use UsageException;
use User;
use WikiPage;

class ApiQueryExtracts extends ApiQueryBase {

	/**
	 * Bump when memcache needs clearing
	 */
	const CACHE_VERSION = 2;

	/**
	 * @var string
	 */
	const PREFIX = 'ex';

	/**
	 * @var ParserOptions
	 */
	private $parserOptions;
	private $params;
	/**
	 * @var Config
	 */
	private $config;

	// TODO: Allow extensions to hook into this to opt-in.
	// This is partly for security reasons; see T107170.
	/**
	 * @var array
	 */
	private $supportedContentModels = [ 'wikitext' ];

	/**
	 * @param \ApiQuery $query API query module object
	 * @param string $moduleName Name of this query module
	 * @param Config $conf MediaWiki configuration
	 */
	public function __construct( $query, $moduleName, Config $conf ) {
		parent::__construct( $query, $moduleName, self::PREFIX );
		$this->config = $conf;
	}

	/**
	 * Evaluates the parameters, performs the requested extraction of text,
	 * and sets up the result
	 * @return null
	 */
	public function execute() {
		$titles = $this->getPageSet()->getGoodTitles();
		if ( count( $titles ) == 0 ) {
			return;
		}
		$isXml = $this->getMain()->isInternalMode()
			|| $this->getMain()->getPrinter()->getFormat() == 'XML';
		$result = $this->getResult();
		$params = $this->params = $this->extractRequestParams();
		$this->requireMaxOneParameter( $params, 'chars', 'sentences' );
		$continue = 0;
		$limit = intval( $params['limit'] );
		if ( $limit > 1 && !$params['intro'] ) {
			$limit = 1;
			$this->addWarning( [ 'apiwarn-textextracts-limit', $limit ] );
		}
		if ( isset( $params['continue'] ) ) {
			$continue = intval( $params['continue'] );
			$this->dieContinueUsageIf( $continue < 0 || $continue > count( $titles ) );
			$titles = array_slice( $titles, $continue, null, true );
		}
		$count = 0;
		$titleInFileNamespace = false;
		/** @var Title $t */
		foreach ( $titles as $id => $t ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $continue + $count - 1 );
				break;
			}

			if ( $t->inNamespace( NS_FILE ) ) {
				$text = '';
				$titleInFileNamespace = true;
			} else {
				$params = $this->params;
				$text = $this->getExtract( $t );
				$text = $this->truncate( $text );
				if ( $params['plaintext'] ) {
					$text = $this->doSections( $text );
				} else {
					if ( $params['sentences'] ) {
						$this->addWarning( $this->msg( 'apiwarn-textextracts-sentences-and-html', self::PREFIX ) );
					}
					$this->addWarning( 'apiwarn-textextracts-malformed-html' );
				}
			}

			if ( $isXml ) {
				$fit = $result->addValue( [ 'query', 'pages', $id ], 'extract', [ '*' => $text ] );
			} else {
				$fit = $result->addValue( [ 'query', 'pages', $id ], 'extract', $text );
			}
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $continue + $count - 1 );
				break;
			}
		}
		if ( $titleInFileNamespace ) {
			$this->addWarning( 'apiwarn-textextracts-title-in-file-namespace' );
		}
	}

	/**
	 * @param array $params Ignored parameters
	 * @return string
	 */
	public function getCacheMode( $params ) {
		return 'public';
	}

	/**
	 * Returns a processed, but not trimmed extract
	 * @param Title $title
	 * @return string
	 */
	private function getExtract( Title $title ) {
		$contentModel = $title->getContentModel();
		if ( !in_array( $contentModel, $this->supportedContentModels, true ) ) {
			$this->addWarning( [
				'apiwarn-textextracts-unsupportedmodel',
				wfEscapeWikiText( $title->getPrefixedText() ),
				$contentModel
			] );
			return '';
		}

		$page = WikiPage::factory( $title );

		$introOnly = $this->params['intro'];
		$text = $this->getFromCache( $page, $introOnly );
		// if we need just first section, try retrieving full page and getting first section out of it
		if ( $text === false && $introOnly ) {
			$text = $this->getFromCache( $page, false );
			if ( $text !== false ) {
				$text = $this->getFirstSection( $text, $this->params['plaintext'] );
			}
		}
		if ( $text === false ) {
			$text = $this->parse( $page );
			$text = $this->convertText( $text );
			$this->setCache( $page, $text );
		}
		return $text;
	}

	private function cacheKey( BagOStuff $cache, WikiPage $page, $introOnly ) {
		return $cache->makeKey( 'textextracts', self::CACHE_VERSION,
			$page->getId(), $page->getTouched(),
			$page->getTitle()->getPageLanguage()->getPreferredVariant(),
			$this->params['plaintext'], $introOnly
		);
	}

	private function getFromCache( WikiPage $page, $introOnly ) {
		global $wgMemc;

		$key = $this->cacheKey( $wgMemc, $page, $introOnly );
		return $wgMemc->get( $key );
	}

	private function setCache( WikiPage $page, $text ) {
		global $wgMemc;

		$key = $this->cacheKey( $wgMemc, $page, $this->params['intro'] );
		$wgMemc->set( $key, $text, $this->getConfig()->get( 'ParserCacheExpireTime' ) );
	}

	private function getFirstSection( $text, $plainText ) {
		if ( $plainText ) {
			$regexp = '/^(.*?)(?=' . ExtractFormatter::SECTION_MARKER_START . ')/s';
		} else {
			$regexp = '/^(.*?)(?=<h[1-6]\b)/s';
		}
		if ( preg_match( $regexp, $text, $matches ) ) {
			$text = $matches[0];
		}
		return $text;
	}

	/**
	 * Returns page HTML
	 * @param WikiPage $page
	 * @return string|null
	 * @throws ApiUsageException
	 * @throws UsageException
	 */
	private function parse( WikiPage $page ) {
		$apiException = null;
		if ( !$this->parserOptions ) {
			$this->parserOptions = new ParserOptions( new User( '127.0.0.1' ) );
			if ( is_callable( [ $this->parserOptions, 'setWrapOutputClass' ] ) &&
				!defined( 'ParserOutput::SUPPORTS_UNWRAP_TRANSFORM' )
			) {
				$this->parserOptions->setWrapOutputClass( false );
			}
		}
		// first try finding full page in parser cache
		if ( $page->shouldCheckParserCache( $this->parserOptions, 0 ) ) {
			$pout = MediaWikiServices::getInstance()->getParserCache()->get( $page, $this->parserOptions );
			if ( $pout ) {
				$text = $pout->getText( [ 'unwrap' => true ] );
				if ( $this->params['intro'] ) {
					$text = $this->getFirstSection( $text, false );
				}
				return $text;
			}
		}
		$request = [
			'action' => 'parse',
			'page' => $page->getTitle()->getPrefixedText(),
			'prop' => 'text',
			// Invokes special handling when using partial wikitext (T168743)
			'sectionpreview' => 1,
			'wrapoutputclass' => '',
		];
		if ( $this->params['intro'] ) {
			$request['section'] = 0;
		}
		// in case of cache miss, render just the needed section
		$api = new ApiMain( new FauxRequest( $request ) );
		try {
			$api->execute();
			$data = $api->getResult()->getResultData( null, [
				'BC' => [],
				'Types' => [],
			] );
		} catch ( ApiUsageException $e ) {
			$apiException = $e->__toString();
			if ( $e->getStatusValue()->hasMessage( 'apierror-nosuchsection' ) ) {
				// Looks like we tried to get the intro to a page without
				// sections!  Lets just grab what we can get.
				unset( $request['section'] );
				$api = new ApiMain( new FauxRequest( $request ) );
				$api->execute();
				$data = $api->getResult()->getResultData( null, [
					'BC' => [],
					'Types' => [],
				] );
			} else {
				// Some other unexpected error - lets just report it to the user
				// on the off chance that is the right thing.
				throw $e;
			}
		} catch ( UsageException $e ) {
			$apiException = $e->__toString();
			if ( $e->getCodeString() === 'nosuchsection' ) {
				// Looks like we tried to get the intro to a page without
				// sections!  Lets just grab what we can get.
				unset( $request['section'] );
				$api = new ApiMain( new FauxRequest( $request ) );
				$api->execute();
				$data = $api->getResult()->getResultData( null, [
					'BC' => [],
					'Types' => [],
				] );
			} else {
				// Some other unexpected error - lets just report it to the user
				// on the off chance that is the right thing.
				throw $e;
			}
		}
		if ( !array_key_exists( 'parse', $data ) ) {
			LoggerFactory::getInstance( 'textextracts' )->warning(
				'API Parse request failed while generating text extract', [
					'title' => $page->getTitle()->getFullText(),
					'url' => $this->getRequest()->getFullRequestURL(),
					'exception' => $apiException,
					'request' => $request
			] );
			return null;
		}

		return $data['parse']['text']['*'];
	}

	/**
	 * @param \ApiQuery $query API query module
	 * @param string $name Name of this query module
	 * @return ApiQueryExtracts
	 */
	public static function factory( $query, $name ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'textextracts' );
		return new self( $query, $name, $config );
	}

	/**
	 * Converts page HTML into an extract
	 * @param string $text
	 * @return string
	 */
	private function convertText( $text ) {
		$fmt = new ExtractFormatter(
			$text,
			$this->params['plaintext'],
			$this->config
		);
		$text = $fmt->getText();

		return trim( $text );
	}

	/**
	 * Truncate the given text to a certain number of characters or sentences
	 * @param string $text The text to truncate
	 * @return string
	 */
	private function truncate( $text ) {
		if ( $this->params['chars'] ) {
			return $this->getFirstChars( $text, $this->params['chars'] );
		} elseif ( $this->params['sentences'] ) {
			return $this->getFirstSentences( $text, $this->params['sentences'] );
		}
		return $text;
	}

	/**
	 * Returns no more than a requested number of characters
	 * @param string $text
	 * @param int $requestedLength
	 * @return string
	 */
	private function getFirstChars( $text, $requestedLength ) {
		$text = ExtractFormatter::getFirstChars( $text, $requestedLength );
		// Fix possibly unclosed tags
		$text = $this->tidy( $text );
		$text .= wfMessage( 'ellipsis' )->inContentLanguage()->text();
		return $text;
	}

	/**
	 * @param string $text
	 * @param int $requestedSentenceCount
	 * @return string
	 */
	private function getFirstSentences( $text, $requestedSentenceCount ) {
		$text = ExtractFormatter::getFirstSentences( $text, $requestedSentenceCount );
		$text = $this->tidy( $text );
		return $text;
	}

	/**
	 * A simple wrapper around tidy
	 * @param string $text
	 * @return string
	 */
	private function tidy( $text ) {
		$tidyConfig = $this->getConfig()->get( 'TidyConfig' );

		if ( $tidyConfig !== null && !$this->params['plaintext'] ) {
			$text = trim( MWTidy::tidy( $text ) );
		}
		return $text;
	}

	private function doSections( $text ) {
		$text = preg_replace_callback(
			"/" . ExtractFormatter::SECTION_MARKER_START . '(\d)' .
				ExtractFormatter::SECTION_MARKER_END . "(.*?)$/m",
			[ $this, 'sectionCallback' ],
			$text
		);
		return $text;
	}

	private function sectionCallback( $matches ) {
		if ( $this->params['sectionformat'] == 'raw' ) {
			return $matches[0];
		}
		$sectionformat = ucfirst( $this->params['sectionformat'] );
		$func = __CLASS__ . "::doSection{$sectionformat}";
		return call_user_func( $func, $matches[1], trim( $matches[2] ) );
	}

	private static function doSectionWiki( $level, $text ) {
		$bars = str_repeat( '=', $level );
		return "\n$bars $text $bars";
	}

	private static function doSectionPlain( $level, $text ) {
		return "\n$text";
	}

	/**
	 * Return an array describing all possible parameters to this module
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'chars' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 1200,
			],
			'sentences' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 10,
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 20,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 20,
				ApiBase::PARAM_MAX2 => 20,
			],
			'intro' => false,
			'plaintext' => false,
			'sectionformat' => [
				ApiBase::PARAM_TYPE => [ 'plain', 'wiki', 'raw' ],
				ApiBase::PARAM_DFLT => 'wiki',
			],
			'continue' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&prop=extracts&exchars=175&titles=Therion'
				=> 'apihelp-query+extracts-example-1',
		];
	}

	/**
	 * @see ApiBase::getHelpUrls()
	 * @return string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:TextExtracts#API';
	}
}
