( function ( $ ) {
	QUnit.revisionSlider = {};

	$.extend( QUnit.revisionSlider, {
		// Helper function to add conditions to QUnit skip methods.
		testOrSkip: function ( name, testCallback, skipCondition ) {
			if ( skipCondition ) {
				QUnit.skip( name, testCallback );
			} else {
				QUnit.test( name, testCallback );
			}
		}
	} );
}( jQuery ) );
