<?php
/**
 * Represents a schema revision on a remote wiki.
 * Handles retrieval (via HTTP) and local caching.
 */
class RemoteSchema implements JsonSerializable {

	const LOCK_TIMEOUT = 20;

	public $title;
	public $revision;
	public $cache;
	public $http;
	public $key;
	public $content = false;

	/**
	 * Constructor.
	 * @param string $title
	 * @param int $revision
	 * @param BagOStuff $cache (optional) cache client.
	 * @param Http $http (optional) HTTP client.
	 */
	public function __construct( $title, $revision, $cache = null, $http = null ) {
		global $wgEventLoggingSchemaApiUri;

		$this->title = $title;
		$this->revision = $revision;
		$this->cache = $cache ?: wfGetCache( CACHE_ANYTHING );
		$this->http = $http ?: new Http();
		$this->key = $this->cache->makeGlobalKey(
			'eventlogging-schema',
			$wgEventLoggingSchemaApiUri,
			$revision
		);
	}

	/**
	 * Retrieves schema content.
	 * @return array|bool Schema or false if irretrievable.
	 */
	public function get() {
		if ( $this->content ) {
			return $this->content;
		}

		$this->content = $this->memcGet();
		if ( $this->content ) {
			return $this->content;
		}

		$this->content = $this->httpGet();
		if ( $this->content ) {
			$this->memcSet();
		}

		return $this->content;
	}

	/**
	 * Returns an object containing serializable properties.
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'schema'   => $this->get() ?: new stdClass(),
			'revision' => $this->revision
		];
	}

	/**
	 * Retrieves content from memcached.
	 * @return array|bool Schema or false if not in cache.
	 */
	protected function memcGet() {
		return $this->cache->get( $this->key );
	}

	/**
	 * Store content in memcached.
	 * @return bool
	 */
	protected function memcSet() {
		return $this->cache->set( $this->key, $this->content );
	}

	/**
	 * Retrieves the schema using HTTP.
	 * Uses a memcached lock to avoid cache stampedes.
	 * @return array|bool Schema or false if unable to fetch.
	 */
	protected function httpGet() {
		if ( !$this->lock() ) {
			return false;
		}
		$uri = $this->getUri();
		$raw = $this->http->get( $uri, [
			'timeout' => self::LOCK_TIMEOUT * 0.8
		] );
		$content = FormatJson::decode( $raw, true );
		if ( !$content ) {
			wfDebugLog( 'EventLogging', "Request to $uri failed." );
		}
		return $content ?: false;
	}

	/**
	 * Acquire a mutex lock for HTTP retrieval.
	 * @return bool Whether lock was successfully acquired.
	 */
	protected function lock() {
		return $this->cache->add( $this->key . ':lock', 1, self::LOCK_TIMEOUT );
	}

	/**
	 * Constructs URI for retrieving schema from remote wiki.
	 * @return string URI.
	 */
	protected function getUri() {
		global $wgEventLoggingSchemaApiUri;

		return wfAppendQuery( $wgEventLoggingSchemaApiUri, [
			'action' => 'jsonschema',
			'revid'  => $this->revision,
			'formatversion' => 2,
		] );
	}
}
