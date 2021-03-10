( function ( mw, $ ) {
	/**
	 * Module handling diff page reloading and the RevisionSlider browser history
	 *
	 * @constructor
	 */
	var DiffPage = function () {
		this.lastRequest = null;
	};

	$.extend( DiffPage.prototype, {
		/**
		 * Refreshes the diff view with two given revision IDs
		 *
		 * @param {number} diff
		 * @param {number} oldid
		 * @param {SliderView} sliderView
		 * @param {number} [retryAttempt=0]
		 */
		refresh: function ( diff, oldid, sliderView, retryAttempt ) {
			var self = this,
				retryLimit = 2,
				data = {
					diff: diff,
					oldid: oldid
				},
				params = this.getExtraDiffPageParams();

			retryAttempt = retryAttempt || 0;

			if ( Object.keys( params ).length > 0 ) {
				$.extend( data, params );
			}

			if ( this.lastRequest ) {
				this.lastRequest.abort();
			}

			$( 'table.diff[data-mw="interface"]' ).addClass( 'mw-revslider-diff-loading' );

			this.lastRequest = $.ajax( {
				url: mw.util.wikiScript( 'index' ),
				data: data,
				tryCount: 0
			} );
			// Don't chain, so lastRequest is a jQuery.jqXHR object
			this.lastRequest.then( function ( data ) {
				var $data,
					$container = $( '.mw-revslider-container' ),
					$contentText = $( '#mw-content-text' ),
					$sidePanel = $( '#mw-panel' ),
					$catLinks = $( '#catlinks' ),
					$printFooter = $( '.printfooter' ),
					scrollLeft = $container.find( '.mw-revslider-revisions-container' ).scrollLeft();

				// Add our current rendered slider into the newly loaded container
				$data = $( data );
				$data.find( '.mw-revslider-container' ).replaceWith( $container );

				// Replace the elements on the page with the newly loaded elements
				$catLinks.replaceWith( $data.find( '#catlinks' ) );
				$sidePanel.replaceWith( $data.find( '#mw-panel' ) );
				$printFooter.replaceWith( $data.find( '.printfooter' ) );
				$contentText.replaceWith( $data.find( '#mw-content-text' ) );
				// Update edit link
				$( '#ca-edit a, #ca-ve-edit a' ).each( function () {
					var uri = new mw.Uri( $( this ).attr( 'href' ) );
					uri.query.oldid = diff;
					$( this ).attr( 'href', uri.toString() );
				} );
				// Update wgRevisionId (T161257), and wgDiffOldId/NewId
				mw.config.set( 'wgRevisionId', diff );
				mw.config.set( 'wgDiffOldId', oldid );
				mw.config.set( 'wgDiffNewId', diff );

				$( '.mw-revslider-revisions-container' ).scrollLeft( scrollLeft );

				self.addHandlersToCoreLinks( sliderView );

				mw.hook( 'wikipage.content' ).fire( $contentText );
				mw.hook( 'wikipage.diff' ).fire( $( 'body' ).find( 'table.diff' ) );

			}, function ( xhr ) {
				$( 'table.diff[data-mw="interface"]' ).removeClass( 'mw-revslider-diff-loading' );
				if ( xhr.statusText !== 'abort' ) {
					this.tryCount++;
					mw.track( 'counter.MediaWiki.RevisionSlider.error.refresh' );
					if ( retryAttempt <= retryLimit ) {
						self.refresh( diff, oldid, sliderView, retryAttempt + 1 );
					}
					// TODO notify the user that we failed to update the diff?
					// This could also attempt to reload the page with the correct diff loaded without ajax?
				}
			} );
		},

		/**
		 * Replaces the current state in the history stack
		 *
		 * @param {number} diff
		 * @param {number} oldid
		 * @param {SliderView} sliderView
		 */
		replaceState: function ( diff, oldid, sliderView ) {
			// IE9 does not have history.replaceState()
			if ( typeof history.replaceState === 'function' ) {
				history.replaceState(
					this.getStateObject( diff, oldid, sliderView ),
					$( document ).find( 'title' ).text(),
					this.getStateUrl( diff, oldid )
				);
			}
		},

		/**
		 * Pushes the current state onto the history stack
		 *
		 * @param {number} diff
		 * @param {number} oldid
		 * @param {SliderView} sliderView
		 */
		pushState: function ( diff, oldid, sliderView ) {
			// IE9 does not have history.pushState()
			if ( typeof history.pushState === 'function' ) {
				history.pushState(
					this.getStateObject( diff, oldid, sliderView ),
					$( document ).find( 'title' ).text(),
					this.getStateUrl( diff, oldid )
				);
			}
		},

		/**
		 * Gets a state object to be used with history.replaceState and history.pushState
		 *
		 * @param {number} diff
		 * @param {number} oldid
		 * @param {SliderView} sliderView
		 * @return {Object}
		 */
		getStateObject: function ( diff, oldid, sliderView ) {
			return {
				diff: diff,
				oldid: oldid,
				pointerOlderPos: sliderView.pointerOlder.getPosition(),
				pointerNewerPos: sliderView.pointerNewer.getPosition(),
				sliderPos: sliderView.slider.getOldestVisibleRevisionIndex()
			};
		},

		/**
		 * Gets a URL to be used with history.replaceState and history.pushState
		 *
		 * @param {number} diff
		 * @param {number} oldid
		 * @return {string}
		 */
		getStateUrl: function ( diff, oldid ) {
			var url = mw.util.wikiScript( 'index' ) + '?diff=' + diff + '&oldid=' + oldid,
				params = this.getExtraDiffPageParams();
			if ( Object.keys( params ).length > 0 ) {
				Object.keys( params ).forEach( function ( key ) {
					url += '&' + key + '=' + params[ key ];
				} );
			}
			return url;
		},

		/**
		 * Returns an object containing all possible parameters that should be included in diff URLs
		 * when selected revisions change, e.g. uselang
		 *
		 * @return {Object}
		 */
		getExtraDiffPageParams: function () {
			var params = {},
				paramArray = location.search.substr( 1 ).split( '&' ).filter( function ( elem ) {
					return elem.indexOf( '=' ) > 0 && elem.match( /^(diff|oldid)=/ ) === null;
				} );
			paramArray.forEach( function ( elem ) {
				var pair = elem.split( '=', 2 );
				params[ pair[ 0 ] ] = pair[ 1 ];
			} );
			return params;
		},

		/**
		 * @param {SliderView} sliderView
		 */
		initOnPopState: function ( sliderView ) {
			window.addEventListener( 'popstate', function ( event ) {
				if ( event.state === null ) {
					return;
				}
				mw.track( 'counter.MediaWiki.RevisionSlider.event.historyChange' );
				sliderView.slider.setFirstVisibleRevisionIndex( event.state.sliderPos );
				sliderView.updatePointersAndDiffView(
					event.state.pointerNewerPos,
					event.state.pointerOlderPos,
					false
				);
			} );
		},

		/**
		 * @param {SliderView} sliderView
		 */
		addHandlersToCoreLinks: function ( sliderView ) {
			$( '#differences-nextlink' ).click( function () {
				sliderView.showNextDiff();
				return false;
			} );
			$( '#differences-prevlink' ).click( function () {
				sliderView.showPrevDiff();
				return false;
			} );
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.DiffPage = DiffPage;
}( mediaWiki, jQuery ) );
