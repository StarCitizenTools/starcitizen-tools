/**
 * Adds a link to Special:MultiLock on a central wiki if $wgCheckUserCAMultiLock
 * is configured on the Special:CheckUser's block form
 */
( function ( mw, $ ) {
	var centralURL = mw.config.get( 'wgCUCAMultiLockCentral' ),
		$userCheckboxes = $( '#checkuserresults li :checkbox' );

	// Initialize the link
	$( '#checkuserblock fieldset' ).append(
		$( '<a>', {
			id: 'cacu-multilock-link',
			text: mw.msg( 'checkuser-centralauth-multilock' ),
			href: centralURL
		} )
	);

	// Change the URL of the link when a checkbox's state is changed
	$userCheckboxes.on( 'change', function () {
		var names = [];
		$.each( $userCheckboxes.serializeArray(), function ( i, obj ) {
			if ( obj.name && obj.name === 'users[]' ) {
				// Only registered accounts (not IPs) can be locked
				if ( !mw.util.isIPAddress( obj.value ) ) {
					names.push( obj.value );
				}
			}
		} );

		var mlHref = centralURL + '?wpTarget=' + encodeURIComponent( names.join( '\n' ) );
		// Update the href of the link with the latest change
		$( '#cacu-multilock-link' ).prop( 'href', mlHref );
	} );

}( mediaWiki, jQuery ) );
