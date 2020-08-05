( function ( mw, $ ) {
	/**
	 * Sets the cookie, that the cookiewarning is dismissed. Called,
	 * when the api query to save this information in the user preferences,
	 * failed for any reason, or the user is not logged-in.
	 */
	function setCookie() {
		mw.cookie.set( 'cookiewarning_dismissed', true );
	}

	$( function () {
		if ( mw.cookie.get( 'cookiewarning_dismissed' ) ) {
			$( '.mw-cookiewarning-container' ).detach();
		} else {
			// Click handler for the "Ok" element in the cookiewarning information bar
			$( '.mw-cookiewarning-dismiss' ).on( 'click', function ( ev ) {
				// an anonymous user doesn't have preferences, so don't try to save this in
				// the user preferences.
				if ( !mw.user.isAnon() ) {
					// try to save, that the cookiewarning was disabled, in the user preferences
					new mw.Api().saveOption( 'cookiewarning_dismissed', '1' )
						.fail( function ( code, result ) {
							// if it fails, fall back to the cookie
							mw.log.warn( 'Failed to save dismissed CookieWarning: ' + code + '\n' + result.error + '. Using cookie now.' );
							setCookie();
						} );
				} else {
					// use cookies for anonymous users
					setCookie();
				}
				// always remove the cookiewarning element
				$( '.mw-cookiewarning-container' ).detach();

				ev.preventDefault();
			} );
		}
	} );
}( mediaWiki, jQuery ) );
