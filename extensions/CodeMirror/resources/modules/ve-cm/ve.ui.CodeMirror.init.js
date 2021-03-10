( function ( ve, mw ) {
	if ( mw.config.get( 'wgCodeMirrorEnabled' ) ) {
		mw.libs.ve.targetLoader.addPlugin( function () {
			var i, target, index;
			for ( i in ve.init.mw ) {
				target = ve.init.mw[ i ];
				if ( target === ve.init.mw.DesktopArticleTarget ) {
					index = target.static.actionGroups[ 1 ].include.indexOf( 'changeDirectionality' );
					target.static.actionGroups[ 1 ].include.splice( index, 0, 'codeMirror' );
				}
			}
		} );
	}
}( ve, mediaWiki ) );
