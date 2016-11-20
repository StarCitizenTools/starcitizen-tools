<?php

class RSSParser {
	protected $maxheads = 32;
	protected $date = "Y-m-d H:i:s";
	protected $ItemMaxLength = 200;
	protected $reversed = false;
	protected $highlight = array();
	protected $filter = array();
	protected $filterOut = array();
	protected $itemTemplate;
	protected $url;
	protected $etag;
	protected $lastModified;
	protected $xml;
	protected $error;
	protected $displayFields = array( 'author', 'title', 'encodedContent', 'description' );
	protected $stripItems;
	protected $markerString;


	/**
	 * @var RSSData
	 */
	public $rss;

	/**
	 * @var CurlHttpRequest|PhpHttpRequest
	 */
	public $client;

	/**
	 * Convenience function that takes a space-separated string and returns an array of words
	 * @param $str String: list of words
	 * @return Array words found
	 */
	private static function explodeOnSpaces( $str ) {
		$found = preg_split( '# +#', $str );
		return is_array( $found ) ? $found : array();
	}

	/**
	 * Take a bit of WikiText that looks like
	 *   <rss max=5>http://example.com/</rss>
	 * and return an object that can produce rendered output.
	 */
	function __construct( $url, $args ) {
		global $wgRSSDateDefaultFormat,$wgRSSItemMaxLength;

		$this->url = $url;

		$this->markerString = wfRandomString( 32 );
		$this->stripItems = array();

		# Get max number of headlines from argument-array
		if ( isset( $args['max'] ) ) {
			$this->maxheads = $args['max'];
		}

		# Get reverse flag from argument array
		if ( isset( $args['reverse'] ) ) {
			$this->reversed = true;
		}

		# Get date format from argument array
		# or use a default value
		# @todo FIXME: not used yet
		if ( isset( $args['date'] ) ) {
			$this->date = $args['date'];
		} elseif ( isset( $wgRSSDateDefaultFormat ) ) {
			$this->date = $wgRSSDateDefaultFormat;
		}

		# Get highlight terms from argument array
		if ( isset( $args['highlight'] ) ) {
			# mapping to lowercase here so the regex can be case insensitive below.
			$this->highlight = self::explodeOnSpaces( $args['highlight'] );
		}

		# Get filter terms from argument array
		if ( isset( $args['filter'] ) ) {
			$this->filter = self::explodeOnSpaces( $args['filter'] );
		}

		# Get a maximal length for item texts
		if ( isset( $args['item-max-length'] ) ) {
			$this->ItemMaxLength = $args['item-max-length'];
		} elseif ( is_numeric( $wgRSSItemMaxLength ) ) {
			$this->ItemMaxLength = $wgRSSItemMaxLength;
		}

		if ( isset( $args['filterout'] ) ) {
			$this->filterOut = self::explodeOnSpaces( $args['filterout'] );
		}

		// 'template' is the pagename of a user's itemTemplate including
		// a further pagename for the feedTemplate
		// In that way everything is handled via these two pages
		// and no default pages or templates are used.

		// 'templatename' is an optional pagename of a user's feedTemplate
		// In that way it substitutes $1 (default: RSSPost) in MediaWiki:Rss-item

		if ( isset( $args['template'] ) ) {
			$itemTemplateTitleObject = Title::newFromText( $args['template'], NS_TEMPLATE );
			$itemTemplateArticleObject = new Article( $itemTemplateTitleObject, 0 );
			$this->itemTemplate = $itemTemplateArticleObject->fetchContent();
		} else {
			if ( isset( $args['templatename'] ) ) {
				$feedTemplatePagename = $args['templatename'];
			} else {

				// compatibility patch for rss extension

				$feedTemplatePagename = 'RSSPost';
				$feedTemplateTitleObject = Title::newFromText( $feedTemplatePagename, NS_TEMPLATE );

				if ( !$feedTemplateTitleObject->exists() ) {
					$feedTemplatePagename = Title::makeTitleSafe( NS_MEDIAWIKI, 'Rss-feed' );
				}
			}

			// MediaWiki:Rss-item = {{ feedTemplatePagename | title = {{{title}}} | ... }}

			// if the attribute parameter templatename= is not present
			// then it defaults to
			// {{ Template:RSSPost | title = {{{title}}} | ... }} - if Template:RSSPost exists from pre-1.9 versions
			// {{ MediaWiki:Rss-feed | title = {{{title}}} | ... }} - otherwise

			$this->itemTemplate = wfMessage( 'rss-item', $feedTemplatePagename )->plain();

		}
	}


	function insertStripItem( $item ) {
		$this->stripItems[] = $item;
		$itemIndex = count( $this->stripItems ) - 1;
		return "{$this->markerString}-{$itemIndex}-{$this->markerString}";
	}

	/**
	 * Return RSS object for the given URL, maintaining caching.
	 *
	 * NOTES ON RETRIEVING REMOTE FILES:
	 * No attempt will be made to fetch remote files if there is something in cache.
	 *
	 * NOTES ON FAILED REQUESTS:
	 * If there is an HTTP error while fetching an RSS object, the cached version
	 * will be returned, if it exists.
	 *
	 * @return Status object
	 */
	function fetch() {
		if ( !isset( $this->url ) ) {
			return Status::newFatal( 'rss-fetch-nourl' );
		}

		// Flow
		// 1. check cache
		// 2. if there is a hit, make sure its fresh
		// 3. if cached obj fails freshness check, fetch remote
		// 4. if remote fails, return stale object, or error
		$key = wfMemcKey( 'rss', $this->url );
		$cachedFeed = $this->loadFromCache( $key );
		if ( $cachedFeed !== false ) {
			wfDebugLog( 'RSS', 'Outputting cached feed for ' . $this->url );
			return Status::newGood();
		}
		wfDebugLog( 'RSS', 'Cache Failed, fetching ' . $this->url . ' from remote.' );

		$status = $this->fetchRemote( $key );
		return $status;
	}

	/**
	 * Retrieve the URL from the cache
	 * @param $key String: lookup key to associate with this item
	 * @return boolean
	 */
	protected function loadFromCache( $key ) {
		global $wgMemc, $wgRSSCacheCompare;

		$data = $wgMemc->get( $key );
		if ( !is_array( $data ) ) {
			return false;
		}

		list( $etag, $lastModified, $rss ) =
			$data;

		if ( !isset( $rss->items ) ) {
			return false;
		}

		wfDebugLog( 'RSS', "Got '$key' from cache" );

		# Now that we've verified that we got useful data, keep it around.
		$this->rss = $rss;
		$this->etag = $etag;
		$this->lastModified = $lastModified;

		// We only care if $wgRSSCacheCompare is > 0
		if ( $wgRSSCacheCompare && time() - $wgRSSCacheCompare > $lastModified ) {
			wfDebugLog( 'RSS', 'Content is old enough that we need to check cached content' );
			return false;
		}

		return true;
	}

	/**
	 * Store these objects (i.e. etag, lastModified, and RSS) in the cache.
	 * @param $key String: lookup key to associate with this item
	 * @return boolean
	 */
	protected function storeInCache( $key ) {
		global $wgMemc, $wgRSSCacheAge;

		if ( !isset( $this->rss ) ) {
			return false;
		}
		$r = $wgMemc->set( $key,
			array( $this->etag, $this->lastModified, $this->rss ),
			$wgRSSCacheAge );

		wfDebugLog( 'RSS', "Stored '$key' as in cache? $r");
		return true;
	}

	/**
	 * Retrieve a feed.
	 * @param $key String:
	 * @param $headers Array: headers to send along with the request
	 * @return Status object
	 */
	protected function fetchRemote( $key, array $headers = array()) {
		global $wgRSSFetchTimeout, $wgRSSUserAgent, $wgRSSProxy,
			$wgRSSUrlNumberOfAllowedRedirects;

		if ( $this->etag ) {
			wfDebugLog( 'RSS', 'Used etag: ' . $this->etag );
			$headers['If-None-Match'] = $this->etag;
		}
		if ( $this->lastModified ) {
			$lm = gmdate( 'r', $this->lastModified );
			wfDebugLog( 'RSS', "Used last modified: $lm" );
			$headers['If-Modified-Since'] = $lm;
		}

		/**
		 * 'noProxy' can conditionally be set as shown in the commented
		 * example below; in HttpRequest 'noProxy' takes precedence over
		 * any value of 'proxy' and disables the use of a proxy.
		 *
		 * This is useful if you run the wiki in an intranet and need to
		 * access external feed urls through a proxy but internal feed
		 * urls must be accessed without a proxy.
		 *
		 * The general handling of such cases will be subject of a
		 * forthcoming version.
		 */

 		$url = $this->url;
		$noProxy = !isset( $wgRSSProxy );

		// Example for disabling proxy use for certain urls
		// $noProxy = preg_match( '!\.internal\.example\.com$!i', parse_url( $url, PHP_URL_HOST ) );

		if ( isset( $wgRSSUrlNumberOfAllowedRedirects )
			&& is_numeric( $wgRSSUrlNumberOfAllowedRedirects ) ) {
			$maxRedirects = $wgRSSUrlNumberOfAllowedRedirects;
		} else {
			$maxRedirects = 0;
		}

		// we set followRedirects intentionally to true to see error messages
		// in cases where the maximum number of redirects is reached
		$client = MWHttpRequest::factory( $url,
			array(
				'timeout'         => $wgRSSFetchTimeout,
				'followRedirects' => true,
				'maxRedirects'    => $maxRedirects,
				'proxy'           => $wgRSSProxy,
				'noProxy'         => $noProxy,
				'userAgent'       => $wgRSSUserAgent,
			)
		);

		foreach ( $headers as $header => $value ) {
			$client->setHeader( $header, $value );
		}

		$fetch = $client->execute();
		$this->client = $client;

		if ( !$fetch->isGood() ) {
			wfDebug( 'RSS', 'Request Failed: ' . $fetch->getWikiText() );
			return $fetch;
		}

		$ret = $this->responseToXML( $key );
		return $ret;
	}

	/**
	 * @see https://bugzilla.wikimedia.org/show_bug.cgi?id=34763
	 * @param string $wikiText
	 * @param Parser $origParser
	 * @return string
	 */
	protected function sandboxParse( $wikiText, $origParser ) {
		$myParser = new Parser();
		$result = $myParser->parse(
			$wikiText,
			$origParser->getTitle(),
			$origParser->getOptions()
		);

		$stripItems = $this->stripItems;
		$text = preg_replace_callback(
			"/{$this->markerString}-(\d+)-{$this->markerString}/",
			function ( array $matches ) use ( $stripItems ) {
				$markerIndex = (int) $matches[1];
				return $stripItems[$markerIndex];
			},
			$result->getText()
		);
		return $text;
	}

	/**
	 * Render the entire feed so that each item is passed to the
	 * template which the MediaWiki then displays.
	 *
	 * @param Parser $parser
	 * @param string $frame The frame param to pass to recursiveTagParse()
	 * @return string
	 */
	function renderFeed( $parser, $frame ) {

		$renderedFeed = '';

		if ( isset( $this->itemTemplate ) && isset( $parser ) && isset( $frame ) ) {

			$headcnt = 0;
			if ( $this->reversed ) {
				$this->rss->items = array_reverse( $this->rss->items );
			}

			foreach ( $this->rss->items as $item ) {
				if ( $this->maxheads > 0 && $headcnt >= $this->maxheads ) {
					continue;
				}

				if ( $this->canDisplay( $item ) ) {
					$renderedFeed .= $this->renderItem( $item, $parser ) . "\n";
					$headcnt++;
				}
			}

			$renderedFeed = $this->sandboxParse( $renderedFeed, $parser );

		}

		$parser->addTrackingCategory( 'rss-tracking-category' );

		return $renderedFeed;
	}

	/**
	 * Render each item, filtering it out if necessary, applying any highlighting.
	 *
	 * @param $item Array: an array produced by RSSData where keys are the names of the RSS elements
	 * @return mixed
	 */
	protected function renderItem( $item, $parser ) {

		$renderedItem = $this->itemTemplate;

		// $info will only be an XML element name, so we're safe using it.
		// $item[$info] is handled by the XML parser --
		// and that means bad RSS with stuff like
		// <description><script>alert("hi")</script></description> will find its
		// rogue <script> tags neutered.
		// use the overloaded multi byte wrapper functions in GlobalFunctions.php

		foreach ( array_keys( $item ) as $info ) {
			if ( $item[$info] != "" ) {
				switch ( $info ) {
				// ATOM <id> elements and RSS <link> elements are item link urls
				case 'id':
					$txt = $this->sanitizeUrl( $item['id'] );
					$renderedItem = str_replace( '{{{link}}}', $txt, $renderedItem );
					break;
				case 'link':
					$txt = $this->sanitizeUrl( $item['link'] );
					$renderedItem = str_replace( '{{{link}}}', $txt, $renderedItem );
					break;
				case 'date':
					$tempTimezone = date_default_timezone_get();
					date_default_timezone_set( 'UTC' );
					$txt = date( $this->date, strtotime( $this->escapeTemplateParameter( $item['date'] ) ) );
					date_default_timezone_set( $tempTimezone );
					$renderedItem = str_replace( '{{{date}}}', $txt, $renderedItem );
					break;
				default:
					$str = $this->escapeTemplateParameter( $item[$info] );
					$str = $parser->getFunctionLang()->truncate( $str, $this->ItemMaxLength );
					$str = $this->highlightTerms( $str );
					$renderedItem = str_replace( '{{{' . $info . '}}}', $this->insertStripItem( $str ), $renderedItem );
				}
			}
		}

		// nullify all remaining info items in the template
		// without a corresponding info in the current feed item

		$renderedItem = preg_replace( "!{{{[^}]+}}}!U", "", $renderedItem );

		return $renderedItem;
	}

	/**
	 * Sanitize a URL for inclusion in wikitext. Escapes characters that have
	 * a special meaning in wikitext, replacing them with URL escape codes, so
	 * that arbitrary input can be included as a free or bracketed external
	 * link and both work and be safe.
	 */
	protected function sanitizeUrl( $url ) {
		# Remove control characters
		$url = preg_replace( '/[\000-\037\177]/', '', trim( $url ) );
		# Escape other problematic characters
		$out = '';
		for ( $i = 0; $i < strlen( $url ); $i++ ) {
			$boringLength = strcspn( $url, '<>"[|]\ {', $i );
			if ( $boringLength ) {
				$out .= substr( $url, $i, $boringLength );
				$i += $boringLength;
			}
			if ( $i < strlen( $url ) ) {
				$out .= rawurlencode( $url[$i] );
			}
		}
		return $out;
	}

	/**
	 * Sanitize user input for inclusion as a template parameter.
	 *
	 * Unlike in wfEscapeWikiText() as of r77127, this escapes }} in addition
	 * to the other kinds of markup, to avoid user input ending a template
	 * invocation.
	 *
	 * If you want to allow clickable link Urls (HTML <a> tag) in RSS feeds:
	 * $wgRSSAllowLinkTag = true;
	 *
	 * If you want to allow images (HTML <img> tag) in RSS feeds:
	 * $wgRSSAllowImageTag = true;
	 *
	 */
	protected function escapeTemplateParameter( $text ) {
		global $wgRSSAllowLinkTag, $wgRSSAllowImageTag;

		$extraInclude = array();
		$extraExclude = array( "iframe" );

		if ( isset( $wgRSSAllowLinkTag ) && $wgRSSAllowLinkTag ) {
			$extraInclude[] = "a";
		} else {
			$extraExclude[] = "a";
		}

		if ( isset( $wgRSSAllowImageTag ) && $wgRSSAllowImageTag ) {
			$extraInclude[] = "img";
		} else {
			$extraExclude[] = "img";
		}

		if ( ( isset( $wgRSSAllowLinkTag ) && $wgRSSAllowLinkTag )
			|| ( isset( $wgRSSAllowImageTag ) && $wgRSSAllowImageTag ) ) {

			$ret = Sanitizer::removeHTMLtags( $text, null, array(), $extraInclude, $extraExclude );

		} else { // use the old escape method for a while

			$text = str_replace(
				array( '[',     '|',      ']',     '\'',    'ISBN ',
					'RFC ',     '://',     "\n=",     '{{',           '}}',
				),
				array( '&#91;', '&#124;', '&#93;', '&#39;', 'ISBN&#32;',
					'RFC&#32;', '&#58;//', "\n&#61;", '&#123;&#123;', '&#125;&#125;',
				),
				htmlspecialchars( str_replace( "\n", "", $text ) )
			);

			// keep some basic layout tags
			$ret = str_replace(
				array( '&lt;p&gt;', '&lt;/p&gt;',
					'&lt;br/&gt;', '&lt;br&gt;', '&lt;/br&gt;',
					'&lt;b&gt;', '&lt;/b&gt;',
					'&lt;i&gt;', '&lt;/i&gt;',
					'&lt;u&gt;', '&lt;/u&gt;',
					'&lt;s&gt;', '&lt;/s&gt;',
				),
				array( "", "<br/>",
					"<br/>", "<br/>", "<br/>",
					"'''", "'''",
					"''", "''",
					"<u>", "</u>",
					"<s>", "</s>",
				),
				$text
			);
		}

		return $ret;
	}

	/**
	 * Parse an HTTP response object into an array of relevant RSS data
	 *
	 * @param $key String: the key to use to store the parsed response in the cache
	 * @return string|bool parsed RSS object (see RSSParse) or false
	 */
	protected function responseToXML( $key ) {
		wfDebugLog( 'RSS', "Got '" . $this->client->getStatus() . "', updating cache for $key" );
		if ( $this->client->getStatus() === 304 ) {
			# Not modified, update cache
			wfDebugLog( 'RSS', "Got 304, updating cache for $key" );
			$this->storeInCache( $key );
		} else {
			$this->xml = new DOMDocument;
			$raw_xml = $this->client->getContent();

			if( $raw_xml == '' ) {
				return Status::newFatal( 'rss-parse-error', 'No XML content' );
			}

			wfSuppressWarnings();
			// Prevent loading external entities when parsing the XML (bug 46932)
			$oldDisable = libxml_disable_entity_loader( true );
			$this->xml->loadXML( $raw_xml );
			libxml_disable_entity_loader( $oldDisable );
			wfRestoreWarnings();

			$this->rss = new RSSData( $this->xml );

			// if RSS parsed successfully
			if ( $this->rss && !$this->rss->error ) {
				$this->etag = $this->client->getResponseHeader( 'Etag' );
				$this->lastModified =
					strtotime( $this->client->getResponseHeader( 'Last-Modified' ) );

				wfDebugLog( 'RSS', 'Stored etag (' . $this->etag . ') and Last-Modified (' .
					$this->client->getResponseHeader( 'Last-Modified' ) . ') and items (' .
					count( $this->rss->items ) . ')!' );
				$this->storeInCache( $key );
			} else {
				return Status::newFatal( 'rss-parse-error', $this->rss->error );
			}
		}
		return Status::newGood();
	}

	/**
	 * Determine if a given item should or should not be displayed
	 *
	 * @param $item Array: associative array that RSSData produced for an <item>
	 * @return boolean
	 */
	protected function canDisplay( array $item ) {
		$check = '';

		/* We're only going to check the displayable fields */
		foreach ( $this->displayFields as $field ) {
			if ( isset( $item[$field] ) ) {
				$check .= $item[$field];
			}
		}

		if ( $this->filter( $check, 'filterOut' ) ) {
			return false;
		}
		if ( $this->filter( $check, 'filter' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Filters items in or out if the match a string we're looking for.
	 *
	 * @param $text String: the text to examine
	 * @param $filterType String: "filterOut" to check for matches in the filterOut member list.
	 *	Otherwise, uses the filter member list.
	 * @return Boolean: decision to filter or not.
	 */
	protected function filter( $text, $filterType ) {
		if ( $filterType === 'filterOut' ) {
			$filter = $this->filterOut;
		} else {
			$filter = $this->filter;
		}

		if ( count( $filter ) == 0 ) {
			return $filterType !== 'filterOut';
		}

		/* Using : for delimiter here since it'll be quoted automatically. */
		$match = preg_match( ':(' . implode( '|', array_map( 'preg_quote', $filter ) ) . '):i', $text ) ;
		if ( $match ) {
			return true;
		}
		return false;
	}

	/**
	 * Highlight the words we're supposed to be looking for
	 *
	 * @param $text String: the text to look in.
	 * @return String with matched text highlighted in a <span> element
	 */
	protected function highlightTerms( $text ) {
		if ( count( $this->highlight ) === 0 ) {
			return $text;
		}

		$terms = array_flip( array_map( 'strtolower', $this->highlight ) );
		$highlight = ':'. implode( '|', array_map( 'preg_quote', array_values( $this->highlight ) ) ) . ':i';
		return preg_replace_callback( $highlight, function ( $match ) use ( $terms ) {
			$styleStart = "<span style='font-weight: bold; background: none repeat scroll 0%% 0%% rgb(%s); color: %s;'>";
			$styleEnd   = '</span>';

			# bg colors cribbed from Google's highlighting of search terms
			$bgcolor = array( '255, 255, 102', '160, 255, 255', '153, 255, 153',
				'255, 153, 153', '255, 102, 255', '136, 0, 0', '0, 170, 0', '136, 104, 0',
				'0, 70, 153', '153, 0, 153' );
			# Spelling out the fg colors instead of using processing time to create this list
			$color = array( 'black', 'black', 'black', 'black', 'black',
				'white', 'white', 'white', 'white', 'white' );

			$index = $terms[strtolower( $match[0] )] % count( $bgcolor );

			return sprintf( $styleStart, $bgcolor[$index], $color[$index] ) . $match[0] . $styleEnd;
		}, $text );
	}
}

class RSSUtils {

	/**
	* Output an error message, all wraped up nicely.
	* @param String $errorMessageName The system message that this error is
	* @param String|Array $param Error parameter (or parameters)
	* @return String Html that is the error.
	*/
	public static function RSSError( $errorMessageName, $param = false ) {

		// Anything from a parser tag should use Content lang for message,
		// since the cache doesn't vary by user language: use ->inContentLanguage()
		// The ->parse() part makes everything safe from an escaping standpoint.

		return Html::rawElement( 'span', array( 'class' => 'error' ),
			"Extension:RSS -- Error: " . wfMessage( $errorMessageName )->inContentLanguage()->params( $param )->parse()
		);

	}

}
