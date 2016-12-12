/*global moment*/
( function ( mw, $, moment ) {
	/**
	 * this is a progress bar for monitoring multiple objects, giving summary view
	 */
	mw.GroupProgressBar = function ( selector, uploads, successStates, errorStates, progressProperty, weightProperty ) {
		this.$selector = $( selector );
		this.$selector.html(
			'<div class="mwe-upwiz-progress-inner">' +
				'<div class="mwe-upwiz-progress-bar-etr-container">' +
					'<div class="mwe-upwiz-progress-bar-etr" style="display: none">' +
						'<div class="mwe-upwiz-etr"></div>' +
					'</div>' +
				'</div>' +
				'<div class="mwe-upwiz-count"></div>' +
			'</div>'
		);

		this.progressBarWidget = new OO.ui.ProgressBarWidget( {
			classes: [ 'mwe-upwiz-progress-bar' ],
			progress: 0
		} );

		this.$selector.find( '.mwe-upwiz-progress-bar-etr' )
			.prepend( this.progressBarWidget.$element );

		this.uploads = uploads;
		this.successStates = successStates;
		this.errorStates = errorStates;
		this.progressProperty = progressProperty;
		this.weightProperty = weightProperty;
		this.beginTime = undefined;
	};

	mw.GroupProgressBar.prototype = {

		/**
		 * Show the progress bar
		 */
		showBar: function () {
			this.$selector.find( '.mwe-upwiz-progress-bar-etr' ).fadeIn( 200 );
		},

		/**
		 * loop around the uploads, summing certain properties for a weighted total fraction
		 */
		start: function () {
			var bar = this,
				shown = false;

			this.setBeginTime();

			function displayer() {
				var totalWeight = 0.0,
					fraction = 0.0,
					successStateCount = 0,
					errorStateCount = 0,
					hasData = false;

				$.each( bar.uploads, function ( i, upload ) {
					if ( upload === undefined ) {
						return;
					}

					totalWeight += upload[ bar.weightProperty ];
				} );

				$.each( bar.uploads, function ( i, upload ) {
					if ( upload === undefined || upload.state === 'aborted' ) {
						return;
					}
					if ( $.inArray( upload.state, bar.successStates ) !== -1 ) {
						successStateCount++;
					}
					if ( $.inArray( upload.state, bar.errorStates ) !== -1 ) {
						errorStateCount++;
					}
					if ( upload[ bar.progressProperty ] !== undefined ) {
						fraction += upload[ bar.progressProperty ] * ( upload[ bar.weightProperty ] / totalWeight );
						if ( upload[ bar.progressProperty ] > 0 ) {
							hasData = true;
						}
					}
				} );

				// sometimes, the first data we have just tells us that it's over. So only show the bar
				// if we have good data AND the fraction is less than 1.
				if ( hasData && fraction < 1.0 ) {
					if ( !shown ) {
						bar.showBar();
						shown = true;
					}
					bar.showProgress( fraction );
				}
				bar.showCount( successStateCount );

				if ( successStateCount + errorStateCount < bar.uploads.length - bar.countEmpties() ) {
					setTimeout( displayer, 200 );
				} else {
					bar.showProgress( 1.0 );
					bar.finished = true;
					setTimeout( function () { bar.hideBar(); }, 500 );
				}
			}
			displayer();
		},

		/**
		 * Hide the progress bar with a slideup motion
		 */
		hideBar: function () {
			this.$selector.find( '.mwe-upwiz-progress-bar-etr' ).fadeOut( 200 );
		},

		/**
		 * sets the beginning time (useful for figuring out estimated time remaining)
		 * if time parameter omitted, will set beginning time to now
		 *
		 * @param {number} [time] The time this bar is presumed to have started (epoch milliseconds)
		 */
		setBeginTime: function ( time ) {
			this.beginTime = time ? time : ( new Date() ).getTime();
		},

		/**
		 * Show overall progress for the entire UploadWizard
		 * The current design doesn't have individual progress bars, just one giant one.
		 * We did some tricky calculations in startUploads to try to weight each individual file's progress against
		 * the overall progress.
		 *
		 * @param {number} fraction The amount of whatever it is that's done whatever it's done
		 */
		showProgress: function ( fraction ) {
			var t, timeString,
				remainingTime = this.getRemainingTime( fraction );

			this.progressBarWidget.setProgress( parseInt( fraction * 100, 10 ) );

			if ( remainingTime !== null ) {
				if ( remainingTime === 0 ) {
					timeString = mw.message( 'mwe-upwiz-finished' ).text();
				} else if ( remainingTime < 1000 ) {
					timeString = mw.message( 'mwe-upwiz-almost-finished' ).text();
				} else {
					t = moment.duration( remainingTime );
					timeString = t.humanize();
				}

				this.$selector.find( '.mwe-upwiz-etr' ).text( timeString );
			}
		},

		/**
		 * Calculate remaining time for all uploads to complete.
		 *
		 * @param {number} fraction Fraction of progress to show
		 * @return {number} Estimated time remaining (in milliseconds)
		 */
		getRemainingTime: function ( fraction ) {
			var elapsedTime, rate;
			if ( this.beginTime ) {
				elapsedTime = ( new Date() ).getTime() - this.beginTime;
				if ( fraction > 0.0 && elapsedTime > 0 ) { // or some other minimums for good data
					rate = fraction / elapsedTime;
					return ( ( 1.0 - fraction ) / rate );
				}
			}
			return null;
		},

		/**
		 * Show the overall count as we upload
		 *
		 * @param {number} count The number of items that have done whatever has been done e.g. in "uploaded 2 of 5", this is the 2
		 */
		showCount: function ( count ) {
			this.$selector
				.find( '.mwe-upwiz-count' )
				.html( mw.message( 'mwe-upwiz-upload-count', count, this.uploads.length - this.countEmpties() ).escaped() );
		},

		countEmpties: function () {
			var count = 0;
			$.each( this.uploads, function ( i, upload ) {
				if ( !upload || upload.state === 'aborted' ) {
					count += 1;
				}
			} );
			return count;
		}
	};
}( mediaWiki, jQuery, moment ) );
