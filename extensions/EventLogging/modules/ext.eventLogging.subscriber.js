/*!
 * Because subscribers to mw#track receive the full backlog of events
 * matching the subscription, event processing can be safely deferred
 * until the window's load event has fired. This keeps the impact of
 * analytic instrumentation on page load times to a minimum.
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function ( mw, $ ) {
	var pageViewToken = mw.user.generateRandomSessionId();

	/**
	 * Convert the first letter of a string to uppercase.
	 *
	 * @ignore
	 * @private
	 * @param {string} word
	 * @return {string}
	 */
	function titleCase( word ) {
		return word[ 0 ].toUpperCase() + word.slice( 1 );
	}

	/**
	 * mw.track handler for EventLogging events.
	 *
	 * @ignore
	 * @private
	 * @param {string} topic Topic name ('event.*').
	 * @param {Object} event
	 */
	function handleTrackedEvent( topic, event ) {
		var schema = titleCase( topic.slice( topic.indexOf( '.' ) + 1 ) ),
			dependencies = [ 'ext.eventLogging', 'schema.' + schema ];

		mw.loader.using( dependencies, function () {
			mw.eventLog.logEvent( schema, event );
		} );
	}

	/**
	 * This a light-weight interface intended to have no dependencies and be
	 * loaded by initialisation code from consumers without loading the rest
	 * of EventLogging that deals with validation and logging to the server.
	 *
	 * This module handles the 'event'-namespaced topics in `mw.track`.
	 *
	 * Extensions can use this topic without depending on EventLogging
	 * as it degrades gracefully when EventLogging is not installed.
	 *
	 * The handler lazy-loads the appropriate schema module and core EventLogging
	 * code and logs the event.
	 *
	 * @class mw.eventLog
	 * @singleton
	 */
	mw.eventLog = {

		/**
		 * Randomise inclusion based on population size and 64-bit random token.
		 *
		 * Use #inSample instead.
		 *
		 * @private
		 * @param {number} populationSize One in how should return true.
		 * @param {string} [token] 64-bit integer in HEX format
		 * @return {boolean}
		 */
		randomTokenMatch: function ( populationSize, token ) {
			token = token || mw.user.generateRandomSessionId();
			var rand = parseInt( token.slice( 0, 8 ), 16 );
			return rand % populationSize === 0;
		},

		/**
		 * Determine whether the current page view falls in a random sampling.
		 *
		 * @param {number} populationSize One in how should be included.
		 *  0 means nobody, 1 is 100%, 2 is 50%, etc.
		 * @return {boolean}
		 */
		inSample: function ( populationSize ) {
			// Use the same unique random identifier within the same page load
			// to allow correlation between multiple events.
			return this.randomTokenMatch( populationSize, pageViewToken );
		}

	};

	$( window ).on( 'load', function () {
		mw.trackSubscribe( 'event.', handleTrackedEvent );
	} );

}( mediaWiki, jQuery ) );
