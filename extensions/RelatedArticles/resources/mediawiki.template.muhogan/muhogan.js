// Register the Hogan compiler with MediaWiki.
( function ( mw ) {
	var compiler;
	/*
	 * Check if muhogan is already registered (by QuickSurveys). If not
	 * register mustache (Desktop) or hogan (Mobile) as muhogan.
	 */
	try {
		mw.template.getCompiler( 'muhogan' );
	} catch ( e ) {
		try {
			compiler = mw.template.getCompiler( 'mustache' );
		} catch ( e2 ) {
			compiler = mw.template.getCompiler( 'hogan' );
		}

		// register hybrid compiler with core
		mw.template.registerCompiler( 'muhogan', compiler );
	}
}( mediaWiki ) );
