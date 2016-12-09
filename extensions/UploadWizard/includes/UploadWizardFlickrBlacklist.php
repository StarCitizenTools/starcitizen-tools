<?php

/**
 * Checks Flickr images against a blacklist of users
 */
class UploadWizardFlickrBlacklist {
	/**
	 * Regexp to extract photo id (as match group 1) from a static image URL.
	 */
	const IMAGE_URL_REGEXP = '!static\.?flickr\.com/[^/]+/([0-9]+)_!';

	/**
	 * Regexp to extract photo id (as match group 1) from a photo page URL.
	 */
	const PHOTO_URL_REGEXP = '!flickr\.com/(?:x/t/[^/]+/)?photos/[^/]+/([0-9]+)!';

	/**
	 * An array of the blacklisted Flickr NSIDs and path_aliases.
	 * Used as an in-memory cache to speed successive lookups; null means not yet initialized.
	 * @var array|null
	 */
	protected static $blacklist;

	/**
	 * @var string
	 */
	protected $flickrApiKey;

	/**
	 * @var string
	 */
	protected $flickrApiUrl;

	/**
	 * Name of the wiki page which contains the NSID blacklist.
	 *
	 * The page should contain usernames (either the path_alias - the human-readable username
	 * in the URL - or the NSID) separated by whitespace. It is not required to contain both
	 * path_alias and NSID for the same user.
	 *
	 * Lines starting with # are ignored.
	 * @var string
	 */
	protected $flickrBlacklistPage;

	/**
	 * @var IContextSource
	 */
	protected $context;

	/**
	 * Sets options based on a config array such as UploadWizardConfig::getConfig().
	 * @param array $options an array with 'flickrApiKey', 'flickrApiUrl' and
	 *     'flickrBlacklistPage' keys
	 * @param IContextSource $context
	 */
	public function __construct( array $options, IContextSource $context ) {
		$this->flickrApiKey = $options['flickrApiKey'];
		$this->flickrApiUrl = $options['flickrApiUrl'];
		$this->flickrBlacklistPage = $options['flickrBlacklistPage'];
		$this->context = $context;
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	public function isBlacklisted( $url ) {
		$blacklist = $this->getBlacklist();

		$flickrPhotoId = $this->getPhotoIdFromUrl( $url );
		if ( $flickrPhotoId ) {
			$userIds = $this->getUserIdsFromPhotoId( $flickrPhotoId );
			return (bool)array_intersect( $userIds, $blacklist );
		}
		// FIXME should we tell the user we did not recognize the URL?
		return false;
	}

	/**
	 * Returns the blacklist, which is a non-associative array of user NSIDs and path_aliases
	 * (the name name which can be seen in the pretty URL). For a given user, usually only one
	 * of the NSID and the path_alias will be present; it is the responsibility of the consumers
	 * of the blacklist to check it against both.
	 * @return array
	 */
	public function getBlacklist() {
		if ( !isset( self::$blacklist ) ) {
			self::$blacklist = array();
			if ( $this->flickrBlacklistPage ) {
				$title = Title::newFromText( $this->flickrBlacklistPage );
				$page = WikiPage::factory( $title );
				$text = ContentHandler::getContentText( $page->getContent() );
				$text = preg_replace( '/^\s*#.*$/m', '', $text );
				preg_match_all( '/\S+/', $text, $match );
				self::$blacklist = $match[0];
			}
		}
		return self::$blacklist;
	}

	/**
	 * Takes a Flickr photo page URL or a direct image URL, returns photo id (or false on failure).
	 * @param string $url
	 * @return string|bool
	 */
	protected function getPhotoIdFromUrl( $url ) {
		if ( preg_match( self::IMAGE_URL_REGEXP, $url, $matches ) ) {
			return $matches[1];
		} elseif ( preg_match( self::PHOTO_URL_REGEXP, $url, $matches ) ) {
			return $matches[1];
		} else {
			return false;
		}
	}

	/**
	 * Takes a photo ID, returns owner's NSID and path_alias
	 * (the username which appears in the URL), if available.
	 * @param string $flickrPhotoId
	 * @return array an array containing the NSID first and the path_alias second. The path_alias
	 *     is not guaranteed to exist, in which case the array will have a single item;
	 *     if there is no such photo (or some other error happened), the array will be empty.
	 */
	protected function getUserIdsFromPhotoId( $flickrPhotoId ) {
		$userIds = array();
		$params = array(
			'postData' => array(
				'method' => 'flickr.photos.getInfo',
				'api_key' => $this->flickrApiKey,
				'photo_id' => $flickrPhotoId,
				'format' => 'json',
				'nojsoncallback' => 1,
			),
		);
		$response = HTTP::post( $this->flickrApiUrl, $params );
		if ( $response !== false ) {
			$response = json_decode( $response, true );
		}
		if ( isset( $response['photo']['owner']['nsid'] ) ) {
			$userIds[] = $response['photo']['owner']['nsid'];
		}
		// what Flickr calls 'username' can change at any time and so is worthless for blacklisting
		// path_alias is the username in the pretty URL; once set, it cannot be changed.
		if ( isset( $response['photo']['owner']['path_alias'] ) ) {
			$userIds[] = $response['photo']['owner']['path_alias'];
		}
		return $userIds;
	}
}
