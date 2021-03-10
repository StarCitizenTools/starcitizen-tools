( function ( mw, $ ) {
	/**
	 * @param {RevisionList} revisionList
	 * @param {string} [dir]
	 * @constructor
	 */
	var RevisionListView = function ( revisionList, dir ) {
		this.revisionList = revisionList;
		this.dir = dir;
	};

	$.extend( RevisionListView.prototype, {
		/**
		 * @type {RevisionList}
		 */
		revisionList: null,

		/**
		 * @type {number}
		 */
		revisionWidth: 16,

		/**
		 * @type {number}
		 */
		minRevisionHeight: 5,

		/**
		 * @type {number}
		 */
		maxRevisionHeight: 66,

		/**
		 * @type {number}
		 */
		tooltipTimeout: -1,

		/**
		 * @type {boolean}
		 */
		allowHover: true,

		/**
		 * @type {string}
		 */
		dir: null,

		/**
		 * @type {jQuery}
		 */
		html: null,

		/**
		 * @param {number} revisionTickWidth
		 * @param {number} positionOffset
		 * @return {jQuery}
		 */
		render: function ( revisionTickWidth, positionOffset ) {
			var revs = this.revisionList.getRevisions(),
				maxChangeSizeLogged = Math.log( this.revisionList.getBiggestChangeSize() ),
				self = this,
				i, diffSize, relativeChangeSize,
				setHovered = function ( event ) {
					if ( self.allowHover ) {
						self.setRevisionHovered( $( this ), event );
					}
				},
				unsetHovered = function () {
					self.unsetRevisionHovered( $( this ) );
				};

			positionOffset = positionOffset || 0;
			this.revisionWidth = revisionTickWidth;

			this.$html = $( '<div>' ).addClass( 'mw-revslider-revisions' );

			for ( i = 0; i < revs.length; i++ ) {
				diffSize = revs[ i ].getRelativeSize();
				relativeChangeSize = this.calcRelativeChangeSize( diffSize, maxChangeSizeLogged );

				this.$html
					.append( $( '<div>' )
						.addClass( 'mw-revslider-revision-wrapper' )
						.width( this.revisionWidth )
						.append( $( '<div>' )
							.addClass( 'mw-revslider-revision' )
							.attr( 'data-revid', revs[ i ].getId() )
							.attr( 'data-pos', positionOffset + i + 1 )
							.css( {
								height: relativeChangeSize + 'px',
								width: this.revisionWidth + 'px',
								top: diffSize > 0 ? '-' + relativeChangeSize + 'px' : 0
							} )
							.addClass( diffSize > 0 ? 'mw-revslider-revision-up' : 'mw-revslider-revision-down' )
							.append( $( '<div>' ).addClass( 'mw-revslider-revision-border-box' ) )
						)
						.append( $( '<div>' )
							.addClass( 'mw-revslider-revision-wrapper-up' )
							.width( this.revisionWidth )
							.append(
								$( '<div>' )
									.addClass( 'mw-revslider-pointer mw-revslider-pointer-ghost' )
							)
						)
						.append( $( '<div>' )
							.addClass( 'mw-revslider-revision-wrapper-down' )
							.width( this.revisionWidth )
							.append(
								$( '<div>' )
									.addClass( 'mw-revslider-pointer mw-revslider-pointer-ghost' )
							)
						)
						.mouseenter( setHovered )
						.mouseleave( unsetHovered )
					);
			}

			this.keepTooltipsOnHover();
			this.closeTooltipsOnClick();

			return this.$html;
		},

		enableHover: function () {
			this.allowHover = true;
		},

		disableHover: function () {
			this.allowHover = false;
			this.unsetAllHovered();
		},

		/**
		 * @param {jQuery} $revisionWrapper
		 * @param {Event} event
		 */
		setRevisionHovered: function ( $revisionWrapper, event ) {
			var hasMovedTop = event.pageY - $revisionWrapper.offset().top < $revisionWrapper.height() / 2,
				isOlderTop = $revisionWrapper.hasClass( 'mw-revslider-revision-older' ) && hasMovedTop,
				isNewerBottom = $revisionWrapper.hasClass( 'mw-revslider-revision-newer' ) && !hasMovedTop,
				$neighborRevisionWrapper = $revisionWrapper;

			this.showTooltip( $revisionWrapper );

			if ( isOlderTop ) {
				$neighborRevisionWrapper = $revisionWrapper.prev();
			} else if ( isNewerBottom ) {
				$neighborRevisionWrapper = $revisionWrapper.next();
			}

			if ( $neighborRevisionWrapper.length === 0 ) {
				return;
			}

			if ( hasMovedTop ) {
				this.setRevisionGhost( $revisionWrapper.find( '.mw-revslider-revision-wrapper-up' ) );
				if ( isOlderTop ) {
					this.setRevisionGhost( $neighborRevisionWrapper.find( '.mw-revslider-revision-wrapper-down' ) );
				}
			} else {
				this.setRevisionGhost( $revisionWrapper.find( '.mw-revslider-revision-wrapper-down' ) );
				if ( isNewerBottom ) {
					this.setRevisionGhost( $neighborRevisionWrapper.find( '.mw-revslider-revision-wrapper-up' ) );
				}
			}
		},

		/**
		 * @param {jQuery} $revisionWrapper
		 * @return {number}
		 */
		getRevisionWrapperPos: function ( $revisionWrapper ) {
			return +$revisionWrapper.find( '.mw-revslider-revision' ).attr( 'data-pos' );
		},

		/**
		 * @param {jQuery} $revisionWrapper
		 */
		setRevisionGhost: function ( $revisionWrapper ) {
			$revisionWrapper.addClass( 'mw-revslider-revision-hovered' );
		},

		/**
		 * @param {jQuery} $revisionWrapper
		 */
		unsetRevisionHovered: function ( $revisionWrapper ) {
			this.unsetRevisionGhosts( $revisionWrapper );
			this.hideTooltip( $revisionWrapper );
		},

		unsetAllHovered: function () {
			$( '.mw-revslider-revision-wrapper-up, .mw-revslider-revision-wrapper-down' )
				.removeClass( 'mw-revslider-revision-hovered' );
		},

		/**
		 * @param {jQuery} $revisionWrapper
		 */
		unsetRevisionGhosts: function ( $revisionWrapper ) {
			$revisionWrapper.children().removeClass( 'mw-revslider-revision-hovered' );
			$revisionWrapper.prev().children().removeClass( 'mw-revslider-revision-hovered' );
			$revisionWrapper.next().children().removeClass( 'mw-revslider-revision-hovered' );
		},

		/**
		 * @param {jQuery} $renderedList
		 */
		adjustRevisionSizes: function ( $renderedList ) {
			var revs = this.revisionList.getRevisions(),
				maxChangeSizeLogged = Math.log( this.revisionList.getBiggestChangeSize() ),
				i, diffSize, relativeChangeSize;

			for ( i = 0; i < revs.length; i++ ) {
				diffSize = revs[ i ].getRelativeSize();
				relativeChangeSize = this.calcRelativeChangeSize( diffSize, maxChangeSizeLogged );

				$renderedList.find( '.mw-revslider-revision[data-pos="' + ( i + 1 ) + '"]' ).css( {
					height: relativeChangeSize + 'px',
					top: diffSize > 0 ? '-' + relativeChangeSize + 'px' : 0
				} );
			}
		},

		calcRelativeChangeSize: function ( diffSize, maxChangeSizeLogged ) {
			if ( diffSize === 0 ) {
				return 0;
			}
			return Math.ceil(
				( this.maxRevisionHeight - this.minRevisionHeight ) *
					Math.log( Math.abs( diffSize ) ) / maxChangeSizeLogged ) +
				this.minRevisionHeight;
		},

		/**
		 * Hides the current tooltip immediately
		 */
		hideCurrentTooltip: function () {
			var $highlightedRevisionWrapper = $( '.mw-revslider-revision-wrapper-hovered' ),
				$currentTooltip = $( '.mw-revslider-revision-tooltip' );
			if ( this.tooltipTimeout !== -1 ) {
				window.clearTimeout( this.tooltipTimeout );
			}
			if ( $highlightedRevisionWrapper.length !== 0 ) {
				$highlightedRevisionWrapper.removeClass( 'mw-revslider-revision-wrapper-hovered' );
			}
			if ( $currentTooltip.length !== 0 ) {
				$currentTooltip.remove();
			}
		},

		/**
		 * Hides the tooltip after 500ms
		 *
		 * @param {jQuery} $revisionWrapper
		 */
		hideTooltip: function ( $revisionWrapper ) {
			var $currentTooltip = $( '.mw-revslider-revision-tooltip' );
			this.tooltipTimeout = window.setTimeout( function () {
				if ( $revisionWrapper.length !== 0 ) {
					$revisionWrapper.removeClass( 'mw-revslider-revision-wrapper-hovered' );
				}
				if ( $currentTooltip.length !== 0 ) {
					$currentTooltip.remove();
				}
			}, 500 );
		},

		/**
		 * Hides the previous tooltip and shows the new one
		 *
		 * @param {jQuery} $revisionWrapper
		 */
		showTooltip: function ( $revisionWrapper ) {
			var pos = +$revisionWrapper.find( '.mw-revslider-revision' ).attr( 'data-pos' ),
				revId = +$revisionWrapper.find( '.mw-revslider-revision' ).attr( 'data-revid' ),
				revision = this.getRevisionWithId( revId ),
				tooltip;
			if ( revision === null ) {
				return;
			}

			this.hideCurrentTooltip();

			tooltip = this.makeTooltip( revision, $revisionWrapper );
			tooltip.$element.addClass( 'mw-revslider-revision-tooltip-' + pos );

			$( 'body' ).append( tooltip.$element );
			tooltip.toggle( true );

			$revisionWrapper.addClass( 'mw-revslider-revision-wrapper-hovered' );
		},

		/**
		 * @param {number} revId
		 * @return {Revision|null}
		 */
		getRevisionWithId: function ( revId ) {
			var matchedRevision = null;
			this.revisionList.revisions.forEach( function ( revision ) {
				if ( revision.getId() === revId ) {
					matchedRevision = revision;
				}
			} );
			return matchedRevision;
		},

		/**
		 * Sets event handlers on tooltips so they do not disappear when hovering over them
		 */
		keepTooltipsOnHover: function () {
			var self = this;

			$( document )
				.on( 'mouseenter', '.mw-revslider-revision-tooltip', function () {
					window.clearTimeout( self.tooltipTimeout );
				} )
				.on( 'mouseleave', '.mw-revslider-revision-tooltip', function () {
					self.hideTooltip( $( '.mw-revslider-revision-wrapper-hovered' ) );
				} );
		},

		/**
		 * Sets an event handler to close tooltips when clicking somewhere outside
		 */
		closeTooltipsOnClick: function () {
			var self = this;

			$( document )
				.on( 'click', function ( event ) {
					if ( $( event.target ).closest( '.mw-revslider-revision-tooltip' ).length === 0 &&
						$( event.target ).closest( '.mw-revslider-revisions-container' ).length === 0 ) {
						self.hideCurrentTooltip();
					}
				} );
		},

		/**
		 * Generates the HTML for a tooltip that appears on hover above each revision on the slider
		 *
		 * @param {Revision} revision
		 * @param {jQuery} $revisionWrapper
		 * @return {OO.ui.PopupWidget}
		 */
		makeTooltip: function ( revision, $revisionWrapper ) {
			var $tooltip = $( '<div>' )
				.append(
					$( '<p>' ).append(
						$( '<strong>' ).text( mw.msg( 'revisionslider-label-date' ) + mw.msg( 'colon-separator' ) ),
						$( '<a>' ).attr( 'href', mw.util.getUrl( null, { oldid: revision.id } ) )
							.text( revision.getFormattedDate() )
					),
					this.makeUserLine( revision.getUser(), revision.getUserGender() ),
					this.makeCommentLine( revision ),
					this.makePageSizeLine( revision.getSize() ),
					this.makeChangeSizeLine( revision.getRelativeSize() ),
					revision.isMinor() ? $( '<p>' ).text( mw.message( 'revisionslider-minoredit' ).text() ) : ''
				);
			return new OO.ui.PopupWidget( {
				$content: $tooltip,
				$floatableContainer: $revisionWrapper,
				padded: true,
				classes: [ 'mw-revslider-tooltip', 'mw-revslider-revision-tooltip' ]
			} );
		},

		/**
		 * Generates a link to user page or to contributions page for IP addresses
		 *
		 * @param {string} user
		 * @return {string}
		 */
		getUserPage: function ( user ) {
			return ( mw.util.isIPAddress( user, false ) ? 'Special:Contributions/' : 'User:' ) + this.stripInvalidCharacters( user );
		},

		/**
		 * Generates the HTML for the user label
		 *
		 * @param {string} userString
		 * @param {string} userGender
		 * @return {string|jQuery}
		 */
		makeUserLine: function ( userString, userGender ) {
			if ( !userString ) {
				return '';
			}

			if ( !userGender ) {
				userGender = 'unknown';
			}
			return $( '<p>' ).append(
				$( '<strong>' ).text( mw.msg( 'revisionslider-label-username', userGender ) + mw.msg( 'colon-separator' ) ),
				$( '<bdi>' ).append(
					$( '<a>' ).addClass( 'mw-userlink' ).attr( 'href', mw.util.getUrl( this.getUserPage( userString ) ) ).text( this.stripInvalidCharacters( userString ) )
				)
			);
		},

		/**
		 * @param {string} s
		 * @return {string}
		 */
		stripInvalidCharacters: function ( s ) {
			return s.replace( /[<>&]/g, '' );
		},

		/**
		 * Generates the HTML for the comment label
		 *
		 * @param {Revision} rev
		 * @return {string|jQuery}
		 */
		makeCommentLine: function ( rev ) {
			if ( rev.hasEmptyComment() ) {
				return '';
			}

			return $( '<p>' ).append(
				$( '<strong>' ).text( mw.msg( 'revisionslider-label-comment' ) + mw.msg( 'colon-separator' ) ),
				$( '<em>' ).append(
					$( '<bdi>' ).append(
						rev.getParsedComment()
					)
				)
			);
		},

		/**
		 * Generates the HTML for the page size label
		 *
		 * @param {number} size
		 * @return {jQuery}
		 */
		makePageSizeLine: function ( size ) {
			return $( '<p>' ).append(
				$( '<strong>' ).text( mw.msg( 'revisionslider-label-page-size' ) + mw.msg( 'colon-separator' ) ),
				mw.msg( 'revisionslider-page-size', mw.language.convertNumber( size ), size )
			);
		},

		/**
		 * Generates the HTML for the change size label
		 *
		 * @param {number} relativeSize
		 * @return {jQuery}
		 */
		makeChangeSizeLine: function ( relativeSize ) {
			var changeSizeClass = 'mw-revslider-change-none',
				leadingSign = '',
				$changeNumber;

			if ( relativeSize > 0 ) {
				changeSizeClass = 'mw-revslider-change-positive';
				leadingSign = '+';
			} else if ( relativeSize < 0 ) {
				changeSizeClass = 'mw-revslider-change-negative';
			}

			$changeNumber = $( '<span>' )
				.addClass( changeSizeClass )
				.attr( {
					dir: 'ltr' // Make sure that minus/plus is on the left
				} )
				.text( leadingSign + mw.language.convertNumber( relativeSize ) );

			return $( '<p>' ).append(
				$( '<strong>' ).text( mw.msg( 'revisionslider-label-change-size' ) + mw.msg( 'colon-separator' ) ),
				mw.message( 'revisionslider-change-size', $changeNumber, relativeSize, Math.abs( relativeSize ) ).parse()
			);
		},

		/**
		 * Set direction for the view
		 *
		 * @param {string} dir
		 */
		setDir: function ( dir ) {
			this.dir = dir;
		},

		/**
		 * @return {jQuery}
		 */
		getElement: function () {
			return this.$html;
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.RevisionListView = RevisionListView;
}( mediaWiki, jQuery ) );
