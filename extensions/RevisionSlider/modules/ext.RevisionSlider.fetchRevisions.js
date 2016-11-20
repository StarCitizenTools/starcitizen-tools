( function ( mw, $ ) {
	mw.libs.revisionSlider = mw.libs.revisionSlider || {};

	/**
	 * Fetches up to 500 revisions at a time
	 *
	 * @param {{}} options - Options containing success callback, pageName and startId
	 */
	mw.libs.revisionSlider.fetchRevisions = function ( options ) {
		$.ajax( {
			url: mw.util.wikiScript( 'api' ),
			data: {
				action: 'query',
				prop: 'revisions',
				format: 'json',
				rvprop: 'ids|timestamp|user|comment|parsedcomment|size|flags',
				titles: options.pageName,
				formatversion: 2,
				rvstartid: options.startId,
				'continue': '',
				rvlimit: 500
			},
			success: options.success
		} );
	};
}( mediaWiki, jQuery ) );
