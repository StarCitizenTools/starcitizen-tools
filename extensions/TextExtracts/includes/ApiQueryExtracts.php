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
use Config;
use ConfigFactory;
use FauxRequest;
use MWTidy;
use ParserCache;
use ParserOptions;
use Title;
use UsageException;
use User;
use WikiPage;

class ApiQueryExtracts extends ApiQueryBase {
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
	private $supportedContentModels  = array( 'wikitext' );

	public function __construct( $query, $moduleName, Config $conf ) {
		parent::__construct( $query, $moduleName, 'ex' );
		$this->config = $conf;
	}

	public function execute() {
		$titles = $this->getPageSet()->getGoodTitles();
		if ( count( $titles ) == 0 ) {
			return;
		}
		$isXml = $this->getMain()->isInternalMode() || $this->getMain()->getPrinter()->getFormat() == 'XML';
		$result = $this->getResult();
		$params = $this->params = $this->extractRequestParams();
		$this->requireMaxOneParameter( $params, 'chars', 'sentences' );
		$continue = 0;
		$limit = intval( $params['limit'] );
		if ( $limit > 1 && !$params['intro'] ) {
			$limit = 1;
			$this->setWarning( "exlimit was too large for a whole article extracts request, lowered to $limit" );
		}
		if ( isset( $params['continue'] ) ) {
			$continue = intval( $params['continue'] );
			if ( $continue < 0 || $continue > count( $titles ) ) {
				$this->dieUsageMsg( '_badcontinue' );
			}
			$titles = array_slice( $titles, $continue, null, true );
		}
		$count = 0;
		/** @var Title $t */
		foreach ( $titles as $id => $t ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $continue + $count - 1 );
				break;
			}
			$text = $this->getExtract( $t );
			$text = $this->truncate( $text );
			if ( $this->params['plaintext'] ) {
				$text = $this->doSections( $text );
			}

			if ( $isXml ) {
				$fit = $result->addValue( array( 'query', 'pages', $id ), 'extract', array( '*' => $text ) );
			} else {
				$fit = $result->addValue( array( 'query', 'pages', $id ), 'extract', $text );
			}
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $continue + $count - 1 );
				break;
			}
		}
	}

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
			$this->setWarning( "{$title->getPrefixedDBkey()} has content model '$contentModel', which is not supported; returning an empty extract." );
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
			$text = $this->convertText( $text, $title, $this->params['plaintext'] );
			$this->setCache( $page, $text );
		}
		return $text;
	}

	private function cacheKey( WikiPage $page, $introOnly ) {
		return wfMemcKey( 'textextracts', $page->getId(), $page->getTouched(),
			$page->getTitle()->getPageLanguage()->getPreferredVariant(),
			$this->params['plaintext'], $introOnly
		);
	}

	private function getFromCache( WikiPage $page, $introOnly ) {
		global $wgMemc;

		$key = $this->cacheKey( $page, $introOnly );
		return $wgMemc->get( $key );
	}

	private function setCache( WikiPage $page, $text ) {
		global $wgMemc;

		$key = $this->cacheKey( $page, $this->params['intro'] );
		$wgMemc->set( $key, $text );
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
	 * @return string
	 */
	private function parse( WikiPage $page ) {
		if ( !$this->parserOptions ) {
			$this->parserOptions = new ParserOptions( new User( '127.0.0.1' ) );
		}
		// first try finding full page in parser cache
		if ( method_exists( $page, 'isParserCachedUsed' ) ) {
			$useCache = $page->isParserCacheUsed( $this->parserOptions, 0 );
		} else {
			$useCache = $page->shouldCheckParserCache( $this->parserOptions, 0 );
		}
		if ( $useCache ) {
			$pout = ParserCache::singleton()->get( $page, $this->parserOptions );
			if ( $pout ) {
				$pout->setTOCEnabled( false );
				$text = $pout->getText();
				if ( $this->params['intro'] ) {
					$text = $this->getFirstSection( $text, false );
				}
				return $text;
			}
		}
		$request = array(
			'action' => 'parse',
			'page' => $page->getTitle()->getPrefixedText(),
			'prop' => 'text'
		);
		if ( $this->params['intro'] ) {
			$request['section'] = 0;
		}
		// in case of cache miss, render just the needed section
		$api = new ApiMain( new FauxRequest( $request )	);
		try {
			$api->execute();
			if ( defined( 'ApiResult::META_CONTENT' ) ) {
				$data = $api->getResult()->getResultData( null, array(
					'BC' => array(),
					'Types' => array(),
				) );
			} else {
				$data = $api->getResultData();
			}
		} catch ( UsageException $e ) {
			if ( $e->getCodeString() === 'nosuchsection' ) {
				// Looks like we tried to get the intro to a page without
				// sections!  Lets just grab what we can get.
				unset( $request['section'] );
				$api = new ApiMain( new FauxRequest( $request )	);
				$api->execute();
				if ( defined( 'ApiResult::META_CONTENT' ) ) {
					$data = $api->getResult()->getResultData( null, array(
						'BC' => array(),
						'Types' => array(),
					) );
				} else {
					$data = $api->getResultData();
				}
			} else {
				// Some other unexpected error - lets just report it to the user
				// on the off chance that is the right thing.
				throw $e;
			}
		}
		return $data['parse']['text']['*'];
	}

	/**
	 * @param \ApiQuery $query
	 * @param string $action
	 * @return ApiQueryExtracts
	 */
	public static function factory( $query, $action ) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'textextracts' );
		return new self( $query, $action, $config );
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
		if ( $this->getConfig()->get( 'UseTidy' ) && !$this->params['plaintext'] ) {
			$text = trim ( MWTidy::tidy( $text ) );
		}
		return $text;
	}

	private function doSections( $text ) {
		$text = preg_replace_callback(
			"/" . ExtractFormatter::SECTION_MARKER_START . '(\d)'. ExtractFormatter::SECTION_MARKER_END . "(.*?)$/m",
			array( $this, 'sectionCallback' ),
			$text
		);
		return $text;
	}

	private function sectionCallback( $matches ) {
		if ( $this->params['sectionformat'] == 'raw' ) {
			return $matches[0];
		}
		$func = __CLASS__ . "::doSection_{$this->params['sectionformat']}";
		return call_user_func( $func, $matches[1], trim( $matches[2] ) );
	}

	private static function doSection_wiki( $level, $text ) {
		$bars = str_repeat( '=', $level );
		return "\n$bars $text $bars";
	}

	private static function doSection_plain( $level, $text ) {
		return "\n$text";
	}

	public function getAllowedParams() {
		return array(
			'chars' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
			),
			'sentences' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 10,
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 1,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 20,
				ApiBase::PARAM_MAX2 => 20,
			),
			'intro' => false,
			'plaintext' => false,
			'sectionformat' => array(
				ApiBase::PARAM_TYPE => array( 'plain', 'wiki', 'raw' ),
				ApiBase::PARAM_DFLT => 'wiki',
			),
			'continue' => array(
				ApiBase::PARAM_TYPE => 'integer',
				/** @todo Once support for MediaWiki < 1.25 is dropped, just use ApiBase::PARAM_HELP_MSG directly */
				defined( 'ApiBase::PARAM_HELP_MSG' ) ? ApiBase::PARAM_HELP_MSG : '' => 'api-help-param-continue',
			),
			// Used implicitly by LanguageConverter
			'variant' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => false,
			),
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array(
			'chars' => 'How many characters to return, actual text returned might be slightly longer.',
			'sentences' => 'How many sentences to return',
			'limit' => 'How many extracts to return',
			'intro' => 'Return only content before the first section',
			'plaintext' => 'Return extracts as plaintext instead of limited HTML',
			'sectionformat' => array(
				'How to format sections in plaintext mode:',
				' plain - No formatting',
				' wiki - Wikitext-style formatting == like this ==',
				" raw - This module's internal representation (section titles prefixed with <ASCII 1><ASCII 2><section level><ASCII 2><ASCII 1>",
			),
			'continue' => 'When more results are available, use this to continue',
			'variant' => 'Convert content into this language variant`',
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Returns plain-text or limited HTML extracts of the given page(s)';
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array(
			'api.php?action=query&prop=extracts&exchars=175&titles=Therion' => 'Get a 175-character extract',
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=query&prop=extracts&exchars=175&titles=Therion'
				=> 'apihelp-query+extracts-example-1',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:TextExtracts#API';
	}
}
