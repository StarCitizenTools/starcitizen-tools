( function ( mw, $ ) {
	/**
	 * Attempt to execute a thank operation for a given edit
	 *
	 * @param {string} name The username of the user who made the edit
	 * @param {string} revision The revision the user created
	 * @param {string} recipientGender The gender of the user who made the edit
	 * @return {Promise} The thank operation's status.
	 */
	function thankUser( name, revision, recipientGender ) {
		return ( new mw.Api() ).postWithToken( 'csrf', {
			action: 'thank',
			rev: revision,
			source: 'mobilediff'
		} ).then( function () {
			mw.notify( mw.msg( 'thanks-thanked-notice', name, recipientGender, mw.user ) );
		}, function ( errorCode ) {
			// FIXME: What is "popup" and where is it defined?
			/* eslint-disable no-undef */
			switch ( errorCode ) {
				case 'invalidrevision':
					popup.show( mw.msg( 'thanks-error-invalidrevision' ) );
					break;
				case 'ratelimited':
					popup.show( mw.msg( 'thanks-error-ratelimited', recipientGender ) );
					break;
				default:
					popup.show( mw.msg( 'thanks-error-undefined', errorCode ) );
			}
			/* eslint-enable no-undef */
		} );
	}

	/**
	 * Create a thank button for a given edit
	 *
	 * @param {string} name The username of the user who made the edit
	 * @param {string} rev The revision the user created
	 * @param {string} gender The gender of the user who made the edit
	 * @return {jQuery|null} The HTML of the button.
	 */
	function createThankLink( name, rev, gender ) {
		var $thankImg = $( '<img>' ).attr( {
			width: 25,
			height: 20,
			src: mw.config.get( 'wgExtensionAssetsPath' ) + '/Thanks/WhiteSmiley.png'
		} ).addClass( 'mw-mf-action-button-icon' );

		// Don't make thank button for self
		if ( name === mw.config.get( 'wgUserName' ) ) {
			return null;
		}
		// See if user has already been thanked for this edit
		if ( mw.config.get( 'wgThanksAlreadySent' ) ) {
			return $( '<button>' )
				.addClass( 'mw-mf-action-button mw-ui-button mw-ui-progressive thanked' )
				.prop( 'disabled', true )
				.text( mw.message( 'thanks-button-thanked', mw.user ).text() )
				.prepend( $thankImg );
		}
		return $( '<button>' )
			.addClass( 'mw-mf-action-button mw-ui-button mw-ui-progressive' )
			.text( mw.message( 'thanks-button-thank', mw.user, gender ).text() )
			.prepend( $thankImg )
			.on( 'click', function () {
				var $this = $( this );
				if ( !$this.hasClass( 'thanked' ) ) {
					thankUser( name, rev, gender ).done( function () {
						$this.addClass( 'thanked' ).prop( 'disabled', true )
							.text( mw.message( 'thanks-button-thanked', mw.user, gender ).text() )
							.prepend( $thankImg );
					} );
				}
			} );
	}

	/**
	 * Initialise a thank button in the given container.
	 *
	 * @param {jQuery} $user existing element with data attributes associated describing a user.
	 * @param {jQuery} $container to render button in
	 */
	function init( $user, $container ) {
		var username = $user.data( 'user-name' ),
			rev = $user.data( 'revision-id' ),
			gender = $user.data( 'user-gender' ),
			$thankBtn;

		$thankBtn = createThankLink( username, rev, gender );
		if ( $thankBtn ) {
			$thankBtn.prependTo( $container );
		}

	}

	$( function () {
		init( $( '.mw-mf-user' ), $( '#mw-mf-userinfo' ) );
	} );

	// Expose for testing purposes
	mw.thanks = $.extend( {}, mw.thanks || {}, {
		_mobileDiffInit: init
	} );
}( mediaWiki, jQuery ) );
