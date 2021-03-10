( function ( mw, $ ) {
	var settings = new mw.libs.revisionSlider.Settings(),
		autoExpand = settings.shouldAutoExpand();

	if ( autoExpand ) {
		mw.loader.load( 'ext.RevisionSlider.init' );
	} else {
		$( '.mw-revslider-toggle-button' ).click(
			function () {
				mw.loader.load( 'ext.RevisionSlider.init' );
			}
		);
	}

}( mediaWiki, jQuery ) );
