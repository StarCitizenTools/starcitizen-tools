( function ( mw, $ ) {
	'use stict';

	if ( Number( mw.user.options.get( 'eventlogging-display-web' ) ) !== 1 ) {
		return;
	}

	function initDialogPromise() {
		return mw.loader.using( 'oojs-ui-windows' )
			.then( function () {
				/*global OO */
				var wm = new OO.ui.WindowManager(),
					dialog = new OO.ui.MessageDialog();

				wm.addWindows( [ dialog ] );
				dialog.setSize( 'large' );
				$( 'body' ).append( wm.$element );

				return function ( args ) {
					wm.openWindow( dialog, $.extend( {
						verbose: true,
						actions: [
							{
								action: 'accept',
								label: mw.msg( 'ooui-dialog-message-accept' ),
								flags: 'primary'
							}
						]
					}, args ) );
				};
			} );
	}

	var dialogPromise,
		logEvent = mw.eventLog.logEvent;

	mw.eventLog.logEvent = function ( schemaName, eventData ) {
		return logEvent( schemaName, eventData ).then( function ( event ) {
			mw.loader.using( [ 'json', 'mediawiki.notification' ] ).then( function () {
				var baseUrl = mw.config.get( 'wgEventLoggingSchemaApiUri' ).replace( 'api.php', 'index.php' ),
					json = JSON.stringify( event, null, 2 ),
					formatted = mw.format(
						mw.html.escape( 'Log event ($1): $2' ),
						mw.html.element( 'a',
							{ href: baseUrl + '?oldid=' + event.revision },
							'Schema: ' + event.schema
						),
						mw.html.element( 'tt', {},
							JSON.stringify( event.event, null, 1 ).slice( 0, 100 ) + '...'
						)
					),
					content = $( '<p>' ).html( formatted );

				content.on( 'click', function () {
					dialogPromise = dialogPromise || initDialogPromise();
					dialogPromise.then( function ( openDialog ) {
						openDialog( {
							title: 'Schema: ' + event.schema,
							message: $( '<pre>' ).text( json )
						} );
					} );
				} );

				mw.log( json );
				mw.notification.notify( content, { autoHide: true } );
			} );

			return event;
		} );
	};
}( mediaWiki, jQuery ) );
