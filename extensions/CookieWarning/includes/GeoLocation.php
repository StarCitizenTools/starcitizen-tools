<?php
/**
 * GeoLocation implementation
 */

/**
 * Implements the GeoLocation class, which allows to locate the user based on the IP address.
 */
class GeoLocation {
	private $ip;
	private $config;
	private $countryCode;

	/**
	 * Set the IP address you want to locate.
	 *
	 * @param string $ip A valid IP address
	 * @return $this
	 */
	public function setIP( $ip ) {
		if ( !IP::isValid( $ip ) ) {
			throw new InvalidArgumentException( "$ip is not a valid IP address." );
		}
		$this->ip = $ip;

		return $this;
	}

	/**
	 * Returns the IP address.
	 *
	 * @return null|string NULL if the address is not set so far, string otherwise.
	 */
	public function getIP() {
		return $this->ip;
	}

	/**
	 * Sets the Config object used by this class.
	 *
	 * @param Config $config
	 * @return $this
	 */
	public function setConfig( Config $config ) {
		$this->config = $config;

		return $this;
	}

	/**
	 * Returns the country code, if the last call to self::locate() returned true. Otherwise, NULL.
	 *
	 * @return null|string
	 */
	public function getCountryCode() {
		return $this->countryCode;
	}

	/**
	 * Tries to locate the IP address set with self::setIP() using the geolocation service
	 * configured with the $wgCookieWarningGeoIPServiceURL configuration variable. If the config
	 * isn't set, this function returns NULL. If the config is set, but the URL is invalid or an
	 * other problem occures which resulted in a failed locating process, this function returns
	 * false, otherwise it returns true.
	 *
	 * @return bool|null NULL if no geolocation service configured, false on error, true otherwise.
	 */
	public function locate() {
		$this->countryCode = null;
		if ( $this->ip === null ) {
			throw new RuntimeException(
				'No IP address set, locating now would return the servers location.' );
		}
		if ( $this->config === null ) {
			throw new RuntimeException(
				'You need to set the Config object first, before you can locate an IP address.' );
		}
		if ( !$this->config->get( 'CookieWarningGeoIPServiceURL' ) ) {
			return null;
		}
		$requestUrl = $this->config->get( 'CookieWarningGeoIPServiceURL' );
		if ( substr( $requestUrl, -1 ) !== '/' ) {
			$requestUrl .= '/';
		}
		$json = Http::get( $requestUrl . $this->getIP(), [
			'timeout' => '2'
		] );
		if ( !$json ) {
			return false;
		}
		$returnObject = json_decode( $json );
		if ( $returnObject === null || !property_exists( $returnObject, 'country_code' ) ) {
			return false;
		}
		$this->countryCode = $returnObject->country_code;
		return true;
	}
}
