<?php

/**
 * Class that represents a single upload campaign.
 * An upload campaign is stored as a row in the uw_campaigns table,
 * and its configuration is stored in the Campaign: namespace
 *
 * This class is 'readonly' - to modify the campaigns, please
 * edit the appropriate Campaign: namespace page
 *
 * @file
 * @ingroup Upload
 *
 * @since 1.2
 *
 * @licence GNU GPL v2+
 * @author Yuvi Panda <yuvipanda@gmail.com>
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class UploadWizardCampaign {

	/**
	 * The campaign configuration.
	 *
	 * @since 1.2
	 * @var array
	 */
	protected $config = array();

	/**
	 * The campaign configuration, after wikitext properties have been parsed.
	 *
	 * @since 1.2
	 * @var array
	 */
	protected $parsedConfig = null;

	/**
	 * Array of templates used in this campaign.
	 * Each item is an array with ( namespace, template_title )
	 * Stored without deduplication
	 *
	 * @since 1.2
	 * @var array
	 */
	protected $templates = array();

	/**
	 * The Title representing the current campaign
	 *
	 * @since 1.4
	 * @var Title
	 */
	protected $title = null;

	/**
	 * The RequestContext to use for operations performed from this object
	 *
	 * @since 1.4
	 * @var RequestContext
	 */
	protected $context = null;

	public static function newFromName( $name ) {
		$campaignTitle = Title::makeTitleSafe( NS_CAMPAIGN, $name );
		if ( $campaignTitle === null || !$campaignTitle->exists() ) {
			return false;
		}

		return new UploadWizardCampaign( $campaignTitle );
	}

	function __construct( $title, $config = null, $context = null ) {
		$this->title = $title;
		if ( $config === null ) {
			$content = WikiPage::factory( $title )->getContent();
			if ( $content->getModel() !== 'Campaign' ) {
				throw new MWException( 'Wrong content model' );
			}
			$this->config = $content->getJsonData();
		} else {
			$this->config = $config;
		}
		if ( $context === null ) {
			$this->context = RequestContext::getMain();
		} else {
			$this->context = $context;
		}
	}

	/**
	 * Returns true if current campaign is enabled
	 *
	 * @since 1.4
	 *
	 * @return boolean
	 */
	public function getIsEnabled() {
		return $this->config['enabled'];
	}

	/**
	 * Returns name of current campaign
	 *
	 * @since 1.4
	 *
	 * @return string
	 */
	public function getName() {
		return $this->title->getDBkey();
	}

	public function getTitle() {
		return $this->title;
	}

	public function getTrackingCategory() {
		$trackingCats = UploadWizardConfig::getSetting( 'trackingCategory' );
		return Title::makeTitleSafe(
			NS_CATEGORY, str_replace( '$1', $this->getName(), $trackingCats['campaign'] )
		);
	}

	public function getUploadedMediaCount() {
		return Category::newFromTitle( $this->getTrackingCategory() )->getFileCount();
	}

	public function getTotalContributorsCount() {
		global $wgMemc;

		$key = wfMemcKey( 'uploadwizard', 'campaign', $this->getName(), 'contributors-count' );
		$data = $wgMemc->get( $key );
		if ( $data === false ) {
			wfDebug( __METHOD__ . ' cache miss for key ' . $key );
			$dbr = wfGetDB( DB_SLAVE );
			$result = $dbr->select(
				array( 'categorylinks', 'page', 'image' ),
				array( 'count' => 'COUNT(DISTINCT img_user)' ),
				array( 'cl_to' => $this->getTrackingCategory()->getDBKey(), 'cl_type' => 'file' ),
				__METHOD__,
				array(
					'USE INDEX' => array( 'categorylinks' => 'cl_timestamp' )
				),
				array(
					'page' => array( 'INNER JOIN', 'cl_from=page_id' ),
					'image' => array( 'INNER JOIN', 'page_title=img_name' )
				)
			);

			$data = $result->current()->count;

			$wgMemc->set( $key, $data, UploadWizardConfig::getSetting( 'campaignStatsMaxAge' ) );
		}

		return $data;
	}

	public function getUploadedMedia( $limit = 24 ) {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select(
			array( 'categorylinks', 'page' ),
			array( 'cl_from', 'page_namespace', 'page_title' ),
			array( 'cl_to' => $this->getTrackingCategory()->getDBKey(), 'cl_type' => 'file' ),
			__METHOD__,
			array(
				'ORDER BY' => 'cl_timestamp DESC',
				'LIMIT' => $limit,
				'USE INDEX' => array( 'categorylinks' => 'cl_timestamp' )
			),
			array( 'page' => array( 'INNER JOIN', 'cl_from=page_id' ) )
		);

		$images = array();
		foreach ( $result as $row ) {
			$images[] = Title::makeTitle( $row->page_namespace, $row->page_title );
		}

		return $images;
	}

	/**
	 * Returns all set config properties.
	 * Property name => property value
	 *
	 * @since 1.2
	 *
	 * @return array
	 */
	public function getRawConfig() {
		return $this->config;
	}

	/**
	 * Update internal list of templates used in parsing this campaign
	 *
	 * @param ParserOutput $parserOutput
	 */
	private function updateTemplates( ParserOutput $parserOutput ) {
		$templateIds = $parserOutput->getTemplateIds();
		foreach ( $parserOutput->getTemplates() as $ns => $templates ) {
			foreach ( $templates as $dbk => $id ) {
				$this->templates[$ns][$dbk] = array( $id, $templateIds[$ns][$dbk] );
			}
		}
	}

	/**
	 * Wrapper around OutputPage::parseInline
	 *
	 * @param $value String Wikitext to parse
	 * @param $lang Language
	 *
	 * @since 1.3
	 *
	 * @return String parsed wikitext
	 */
	private function parseValue( $value, Language $lang ) {
		global $wgParser;

		$parserOptions = ParserOptions::newFromContext( $this->context );
		$parserOptions->setEditSection( false );
		$parserOptions->setInterfaceMessage( true );
		$parserOptions->setUserLang( $lang );
		$parserOptions->setTargetLanguage( $lang );
		$parserOptions->setTidy( true );

		$output = $wgParser->parse( $value, $this->getTitle(),
									$parserOptions );
		$parsed = $output->getText();

		// Strip out the surrounding <p> tags
		$m = array();
		if ( preg_match( '/^<p>(.*)\n?<\/p>\n?/sU', $parsed, $m ) ) {
			$parsed = $m[1];
		}

		$this->updateTemplates( $output );

		return $parsed;
	}

	/**
	 * Parses the values in an assoc array as wikitext
	 *
	 * @param $array Array
	 * @param $forKeys Array: Array of keys whose values should be parsed
	 *
	 * @since 1.3
	 *
	 * @return array
	 */
	private function parseArrayValues( $array, $lang, $forKeys = null ) {
		$parsed = array();
		foreach ( $array as $key => $value ) {
			if ( $forKeys !== null ) {
				if ( in_array( $key, $forKeys ) ) {
					if ( is_array( $value ) ) {
						$parsed[$key] = $this->parseArrayValues( $value, $lang );
					} else {
						$parsed[$key] = $this->parseValue( $value, $lang );
					}
				} else {
					$parsed[$key] = $value;
				}
			} else {
				$parsed[$key] = $this->parseValue( $value, $lang );
			}
		}
		return $parsed;
	}

	/**
	 * Returns all config parameters, after parsing the wikitext based ones
	 *
	 * @since 1.3
	 *
	 * @return array
	 */
	public function getParsedConfig( $lang = null ) {
		if ( $lang === null ) {
			$lang = $this->context->getLanguage();
		}

		// We check if the parsed config for this campaign is cached. If it is available in cache,
		// we then check to make sure that it is the latest version - by verifying that its
		// timestamp is greater than or equal to the timestamp of the last time an invalidate was
		// issued.
		$cache = ObjectCache::getMainWANInstance();
		$memKey = wfMemcKey(
			'uploadwizard', 'campaign', $this->getName(), 'parsed-config', $lang->getCode()
		);
		$depKeys = array( $this->makeInvalidateTimestampKey() );

		$curTTL = null;
		$memValue = $cache->get( $memKey, $curTTL, $depKeys );
		if ( is_array( $memValue ) && $curTTL > 0 ) {
			$this->parsedConfig = $memValue['config'];
		}

		if ( $this->parsedConfig === null ) {
			$parsedConfig = array();
			foreach ( $this->config as $key => $value ) {
				switch ( $key ) {
				case "title":
				case "description":
					$parsedConfig[$key] = $this->parseValue( $value, $lang );
					break;
				case "display":
					foreach ( $value as $option => $optionValue ) {
						if ( is_array( $optionValue ) ) {
							$parsedConfig['display'][$option] = $this->parseArrayValues(
								$optionValue,
								$lang,
								array( 'label' )
							);
						} else {
							$parsedConfig['display'][$option] = $this->parseValue( $optionValue, $lang );
						}
					}
					break;
				case "fields":
					$parsedConfig['fields'] = array();
					foreach ( $value as $field ) {
						$parsedConfig['fields'][] = $this->parseArrayValues(
							$field,
							$lang,
							array( 'label', 'options' )
						);
					}
					break;
				case "whileActive":
				case "afterActive":
				case "beforeActive":
					if ( array_key_exists( 'display', $value ) ) {
						$value['display'] = $this->parseArrayValues( $value['display'], $lang );
					}
					$parsedConfig[$key] = $value;
					break;
				default:
					$parsedConfig[$key] = $value;
					break;
				}
			}

			$this->parsedConfig = $parsedConfig;

			$cache->set( $memKey, array( 'timestamp' => time(), 'config' => $parsedConfig ) );
		}

		$uwDefaults = UploadWizardConfig::getSetting( 'defaults' );
		if ( array_key_exists( 'objref', $uwDefaults ) ) {
			$this->applyObjectReferenceToButtons( $uwDefaults['objref'] );
		}
		$this->modifyIfNecessary();

		return $this->parsedConfig;
	}

	/**
	 * Modifies the parsed config if there are time-based modifiers that are active.
	 */
	protected function modifyIfNecessary() {
		foreach ( $this->parsedConfig as $cnf => $modifiers ) {
			if ( $cnf === 'whileActive' && $this->isActive() ) {
				$activeModifiers = $modifiers;
			} elseif ( $cnf === 'afterActive' && $this->wasActive() ) {
				$activeModifiers = $modifiers;
			} elseif ( $cnf === 'beforeActive' ) {
				$activeModifiers = $modifiers;
			}
		}

		if ( isset( $activeModifiers ) ) {
			foreach ( $activeModifiers as $cnf => $modifier ) {
				switch ( $cnf ) {
					case "autoAdd":
					case "display":
						if ( !array_key_exists( $cnf, $this->parsedConfig ) ) {
							$this->parsedConfig[$cnf] = array();
						}

						$this->parsedConfig[$cnf] = array_merge( $this->parsedConfig[$cnf], $modifier );
						break;
				}
			}
		}
	}

	/**
	 * Returns the templates used in this Campaign's config
	 *
	 * @return array [ns => [ dbk => [page_id, rev_id ] ] ]
	 */
	public function getTemplates() {
		if ( $this->parsedConfig === null ) {
			$this->getParsedConfig();
		}
		return $this->templates;
	}


	/**
	 * Invalidate the cache for this campaign, in all languages
	 *
	 * Does so by simply writing a new invalidate timestamp to memcached.
	 * Since this invalidate timestamp is checked on every read, the cached entries
	 * for the campaign will be regenerated the next time there is a read.
	 */
	public function invalidateCache() {
		$cache = ObjectCache::getMainWANInstance();
		$cache->touchCheckKey( $this->makeInvalidateTimestampKey() );
	}

	/**
	 * Returns key used to store the last time the cache for a particular campaign was invalidated
	 *
	 * @return String
	 */
	private function makeInvalidateTimestampKey() {
		return wfMemcKey(
			'uploadwizard', 'campaign', $this->getName(), 'parsed-config', 'invalidate-timestamp'
		);
	}

	/**
	 * Checks the current date against the configured start and end dates to determine
	 * whether the campaign is currently active.
	 */
	private function isActive() {
		$today = strtotime( date( "Y-m-d" ) );
		$start = array_key_exists(
			'start', $this->parsedConfig
		) ? strtotime( $this->parsedConfig['start'] ) : null;
		$end = array_key_exists(
			'end', $this->parsedConfig
		) ? strtotime( $this->parsedConfig['end'] ) : null;

		return ( $start === null || $start <= $today ) && ( $end === null || $end > $today );
	}

	/**
	 * Checks the current date against the configured start and end dates to determine
	 * whether the campaign is currently active.
	 */
	private function wasActive() {
		$today = strtotime( date( "Y-m-d" ) );
		$start = array_key_exists(
			'start', $this->parsedConfig
		) ? strtotime( $this->parsedConfig['start'] ) : null;

		return $start === null || $start <= $today;
	}

	/**
	 * Generate the URL out of the object reference
	 *
	 * @param string $objRef
	 * @return bool|string
	 */
	private function getButtonHrefByObjectReference( $objRef ) {
		$arrObjRef = explode( '|', $objRef );
		if ( count( $arrObjRef ) > 1 ) {
			list( $wiki, $title ) = $arrObjRef;
			if ( Interwiki::isValidInterwiki( $wiki ) ) {
				return str_replace( '$1', $title, Interwiki::fetch( $wiki )->getURL() );
			}
		}
		return false;
	}

	/**
	 * Apply given object reference to buttons configured to use it as href
	 *
	 * @param string $objRef
	 */
	private function applyObjectReferenceToButtons( $objRef ) {
		$customizableButtons = array( 'homeButton', 'beginButton' );

		foreach ( $customizableButtons as $button ) {
			if ( isset( $this->parsedConfig['display'][$button]['target'] ) &&
				$this->parsedConfig['display'][$button]['target'] === 'useObjref'
			) {
				$validUrl = $this->getButtonHrefByObjectReference( $objRef );
				if ( $validUrl ) {
					$this->parsedConfig['display'][$button]['target'] = $validUrl;
				} else {
					unset( $this->parsedConfig['display'][$button] );
				}
			}
		}
	}
}
