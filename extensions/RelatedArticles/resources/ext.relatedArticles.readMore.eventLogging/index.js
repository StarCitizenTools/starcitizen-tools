// See https://meta.wikimedia.org/wiki/Schema:RelatedArticles
( function ( $, mw ) {
	var $readMore,
		schemaRelatedPages,
		skin = mw.config.get( 'skin' ),
		$window = $( window );

	/**
	 * Gets whether the UA supports [the Beacon API][0].
	 *
	 * [0]: https://www.w3.org/TR/beacon/
	 *
	 * @return {boolean}
	 */
	function supportsBeacon() {
		return $.isFunction( window.navigator.sendBeacon );
	}

	/**
	 * Gets whether the instrumentation is enabled for the user.
	 *
	 * We sample at the feature level, not by pageview. If the instrumentation is
	 * enabled for the user, then it's enabled for the duration of their session.
	 *
	 * @return {boolean}
	 */
	function isEnabledForCurrentUser() {
		var bucketSize = mw.config.get( 'wgRelatedArticlesLoggingBucketSize', 0 );

		if ( !supportsBeacon() ) {
			return false;
		}

		return mw.experiments.getBucket( {
			name: 'ext.relatedArticles.instrumentation',
			enabled: true,
			buckets: {
				control: 1 - bucketSize,
				A: bucketSize
			}
		}, mw.user.sessionId() ) === 'A';
	}

	if ( !isEnabledForCurrentUser() ) {
		return;
	}

	// ---
	// BEGIN INSTRUMENTATION
	// ---

	schemaRelatedPages = new mw.eventLog.Schema(
		'RelatedArticles',

		// The instrumentation is enabled for the user's session. DON'T SAMPLE AT
		// THE EVENT LEVEL.
		1,

		{
			pageId: mw.config.get( 'wgArticleId' ),
			skin: ( skin === 'minerva' ) ? skin + '-' + mw.config.get( 'wgMFMode' ) : skin,
			userSessionToken: mw.user.sessionId()
		}
	);

	/**
	 * Log when ReadMore is seen by the user
	 */
	function logReadMoreSeen() {
		if ( mw.viewport.isElementInViewport( $readMore.get( 0 ) ) ) {
			$window.off( 'scroll', logReadMoreSeen );
			schemaRelatedPages.log( { eventName: 'seen' } );
		}
	}

	mw.trackSubscribe( 'ext.relatedArticles.logEnabled', function ( _, data ) {
		schemaRelatedPages.log( {
			eventName: data.isEnabled ? 'feature-enabled' : 'feature-disabled'
		} );
	} );

	mw.trackSubscribe( 'ext.relatedArticles.logReady', function ( _, data ) {
		$readMore = data.$readMore;

		// log ready
		schemaRelatedPages.log( { eventName: 'ready' } );

		// log when ReadMore is seen by the user
		$window.on(
			'scroll',
			$.debounce( 250, logReadMoreSeen )
		);
		logReadMoreSeen();

		// track the clicked event
		// TODO: This should be moved into the PageList component or, preferably, the CardList/Card views.
		$readMore.on( 'click', 'a', function () {
			var index = $( this ).parents( 'li' ).index();

			schemaRelatedPages.log( {
				eventName: 'clicked',
				clickIndex: index + 1
			} );
		} );
	} );

}( jQuery, mediaWiki ) );
