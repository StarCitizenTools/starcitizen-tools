( function ( mw, $ ) {
	'use strict';
	var geoLocation;

	geoLocation = {
		/**
		 * @return {string} Two-letter country code
		 */
		getCountryCode: function () {
			/**
			 * safe fallback -- if geolocation fails, display the notice anyway
			 */
			var countryCode = 'EU';

			if ( !$.cookie( 'euCookieWarningCountryCode' ) ) {
				// @see http://www.dwuser.com/education/content/web-services-made-practical-where-are-your-visitors-from/
				$.get( mw.config.get( 'wgCookieWarningGeoIPServiceURL' ), function ( data ) {
					// Get the country code
					countryCode = data.country_code;
					// Store the result in a cookie (ah, the sweet, sweet irony) to
					// avoid hitting the geolocation service unnecessarily
					$.cookie( 'euCookieWarningCountryCode', countryCode, {
						domain: window.mw.config.get( 'wgCookieDomain' ),
						path: '/',
						expires: 30
					} );
				}, 'jsonp' );
			} else if ( $.cookie( 'euCookieWarningCountryCode' ) !== null ) {
				countryCode = $.cookie( 'euCookieWarningCountryCode' );
			}

			return countryCode;
		},

		/**
		 * Check if the supplied country code is that of a configured region.
		 *
		 * @return {boolean}
		 */
		isInRegion: function () {
			return mw.config.get( 'wgCookieWarningForCountryCodes' ).hasOwnProperty( this.getCountryCode() );
		}
	};

	$( function () {
		if ( geoLocation.isInRegion() ) {
			$( '.mw-cookiewarning-container' ).show();
		} else {
			$( '.mw-cookiewarning-container' ).detach();
		}
	} );
}( mediaWiki, jQuery ) );
