( function ( mw, $ ) {
	var HelpDialog = mw.libs.revisionSlider.HelpDialog;

	QUnit.module( 'ext.RevisionSlider.HelpDialog' );

	QUnit.test( 'Initialize HelpDialog', function ( assert ) {
		var helpDialog = new HelpDialog(),
			windowManager = new OO.ui.WindowManager();

		function getSlideTextHtml( slide ) {
			return slide.$element.find( '.mw-revslider-help-dialog-text' ).html();
		}

		function addLinkTargets( parsedMessage ) {
			var $container = $( '<div>' ).html( parsedMessage );
			$container.find( 'a' ).attr( 'target', '_blank' );
			return $container.html();
		}

		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ helpDialog ] );

		assert.equal( helpDialog.slides.length, 4 );
		assert.equal( helpDialog.slidePointer, 0 );
		assert.equal(
			getSlideTextHtml( helpDialog.slides[ 0 ] ),
			addLinkTargets( mw.message( 'revisionslider-help-dialog-slide1' ).parse() )
		);
		assert.equal(
			getSlideTextHtml( helpDialog.slides[ 1 ] ),
			addLinkTargets( mw.message( 'revisionslider-help-dialog-slide2' ).parse() )
		);
		assert.equal(
			getSlideTextHtml( helpDialog.slides[ 2 ] ),
			addLinkTargets( mw.message( 'revisionslider-help-dialog-slide3a' ).parse() )
		);
		assert.equal(
			getSlideTextHtml( helpDialog.slides[ 3 ] ),
			addLinkTargets( mw.message( 'revisionslider-help-dialog-slide4' ).parse() )
		);
	} );

}( mediaWiki, jQuery ) );
