( function ( mw, $ ) {
	/**
	 * Module handling the view logic of the RevisionSlider slider
	 *
	 * @param {Slider} slider
	 * @constructor
	 */
	var SliderView = function ( slider ) {
		this.slider = slider;
		this.diffPage = new mw.libs.revisionSlider.DiffPage( this.slider.getRevisionList() );
	};

	$.extend( SliderView.prototype, {
		revisionWidth: 16,

		containerMargin: 140,

		outerMargin: 20,

		/**
		 * @type {jQuery}
		 */
		$element: null,

		/**
		 * @type {DiffPage}
		 */
		diffPage: null,

		/**
		 * @type {Slider}
		 */
		slider: null,

		/**
		 * @type {Pointer}
		 */
		pointerOlder: null,

		/**
		 * @type {Pointer}
		 */
		pointerNewer: null,

		/**
		 * @type {OO.ui.ButtonWidget}
		 */
		backwardArrowButton: null,

		/**
		 * @type {OO.ui.ButtonWidget}
		 */
		forwardArrowButton: null,

		/**
		 * @type {string}
		 *
		 * Value of scrollLeft property when in RTL mode varies between browser. This identifies
		 * an implementation used by user's browser:
		 * - 'default': 0 is the left-most position, values increase when scrolling right (same as scrolling from left to right in LTR mode)
		 * - 'negative': 0 is right-most position, values decrease when scrolling left (ie. all values except 0 are negative)
		 * - 'reverse': 0 is right-most position, values incrase when scrolling left
		 */
		rtlScrollLeftType: 'default',

		/**
		 * @type {boolean}
		 */
		noMoreNewerRevisions: false,

		/**
		 * @type {boolean}
		 */
		noMoreOlderRevisions: false,

		/**
		 * @type {string}
		 */
		dir: null,

		/**
		 * @type {boolean}
		 */
		isDragged: false,

		escapePressed: false,

		/**
		 * {number}
		 */
		lastOldPointerPosition: null,

		/**
		 * {number}
		 */
		lastNewPointerPosition: null,

		render: function ( $container ) {
			var containerWidth = this.calculateSliderContainerWidth(),
				$revisions = this.getRevisionListView().render( this.revisionWidth ),
				sliderArrowView = new mw.libs.revisionSlider.SliderArrowView( this );

			this.dir = $container.css( 'direction' ) || 'ltr';

			if ( this.dir === 'rtl' ) {
				this.rtlScrollLeftType = mw.libs.revisionSlider.determineRtlScrollType();
			}

			this.pointerOlder = this.pointerOlder || new mw.libs.revisionSlider.Pointer( 'mw-revslider-pointer-older' );
			this.pointerNewer = this.pointerNewer || new mw.libs.revisionSlider.Pointer( 'mw-revslider-pointer-newer' );

			this.backwardArrowButton = sliderArrowView.renderBackwardArrow();
			this.forwardArrowButton = sliderArrowView.renderForwardArrow();

			this.$element = $( '<div>' )
				.addClass( 'mw-revslider-revision-slider' )
				.css( {
					direction: $container.css( 'direction' ),
					width: ( containerWidth + this.containerMargin ) + 'px'
				} )
				.append(
					this.backwardArrowButton.$element,
					this.renderRevisionsContainer( containerWidth, $revisions ),
					this.forwardArrowButton.$element,
					mw.libs.revisionSlider.HelpButtonView.render(),
					$( '<div>' ).css( { clear: 'both' } ),
					this.renderPointerContainer( containerWidth ),
					this.pointerOlder.getLine().render(), this.pointerNewer.getLine().render()
				);

			this.initPointers( $revisions );

			this.slider.setRevisionsPerWindow( this.$element.find( '.mw-revslider-revisions-container' ).width() / this.revisionWidth );

			this.initializePointers( this.getOldRevElement( $revisions ), this.getNewRevElement( $revisions ) );
			this.resetRevisionStylesBasedOnPointerPosition( $revisions );
			this.addClickHandlerToRevisions( $revisions );

			$container.html( this.$element );

			this.slideView( Math.floor( ( this.getNewerPointerPos() - 1 ) / this.slider.getRevisionsPerWindow() ), 0 );
			this.diffPage.addHandlersToCoreLinks( this );
			this.diffPage.replaceState( mw.config.get( 'wgDiffNewId' ), mw.config.get( 'wgDiffOldId' ), this );
			this.diffPage.initOnPopState( this );
		},

		/**
		 * Renders the revisions container and adds the revisions to it
		 *
		 * @param {number} containerWidth
		 * @param {jQuery} $revisions
		 * @return {jQuery} the revisions container
		 */
		renderRevisionsContainer: function ( containerWidth, $revisions ) {
			return $( '<div>' )
				.addClass( 'mw-revslider-revisions-container' )
				.css( {
					width: containerWidth + 'px'
				} )
				.append( $revisions );
		},

		/**
		 * Renders the pointer container and adds the pointers to it
		 *
		 * @param {number} containerWidth
		 * @return {jQuery} the pointer container
		 */
		renderPointerContainer: function ( containerWidth ) {
			var pointerContainerPosition = 53,
				pointerContainerWidth = containerWidth + this.revisionWidth - 1,
				pointerContainerStyle, lastMouseMoveRevisionPos,
				self = this;

			pointerContainerStyle = { left: pointerContainerPosition + 'px', width: pointerContainerWidth + 'px' };
			if ( this.dir === 'rtl' ) {
				// Due to properly limit dragging a pointer on the right side of the screen,
				// there must some extra space added to the right of the revision bar container
				// For this reason right position of the pointer container in the RTL mode is
				// a bit moved off right compared to its left position in the LTR mode
				pointerContainerPosition = pointerContainerPosition - this.revisionWidth + 1;
				pointerContainerStyle = { right: pointerContainerPosition + 'px', width: pointerContainerWidth + 'px' };
			}

			return $( '<div>' )
				.addClass( 'mw-revslider-pointer-container' )
				.css( pointerContainerStyle )
				.append( this.renderPointerContainers() )
				.click( function ( event ) {
					self.pointerContainerClickHandler( event );
				} )
				.mouseout( function () {
					if ( !self.isDragged ) {
						self.getRevisionListView().unsetAllHovered();
					}
				} )
				.mouseover( function ( event ) {
					if ( !self.isDragged ) {
						lastMouseMoveRevisionPos = self.showTooltipsOnMouseMoveHandler(
							event,
							null
						);
					}
				} )
				.mousemove( function ( event ) {
					if ( !self.isDragged ) {
						lastMouseMoveRevisionPos = self.showTooltipsOnMouseMoveHandler(
							event,
							lastMouseMoveRevisionPos
						);
					}
				} );
		},

		renderPointerContainers: function () {
			return [
				$( '<div>' )
					.addClass( 'mw-revslider-pointer-container-newer' )
					.append(
						$( '<div>' ).addClass( 'mw-revslider-slider-line' ),
						this.pointerNewer.getView().render()
					),
				$( '<div>' )
					.addClass( 'mw-revslider-pointer-container-older' )
					.append(
						$( '<div>' ).addClass( 'mw-revslider-slider-line' ),
						this.pointerOlder.getView().render()
					)
			];
		},

		/**
		 * Initializes the pointer dragging logic
		 *
		 * @param {jQuery} $revisions
		 */
		initPointers: function ( $revisions ) {
			var $pointers = this.$element.find( '.mw-revslider-pointer' ),
				$pointerOlder = this.pointerOlder.getView().getElement(),
				$pointerNewer = this.pointerNewer.getView().getElement(),
				self = this;

			$( 'body' ).keydown( function ( e ) {
				if ( e.which === 27 ) {
					self.escapePressed = true;
					$pointers.trigger( 'mouseup' );
				}
			} );

			$pointers.on(
				'touchstart touchmove touchend touchcancel touchleave',
				mw.libs.revisionSlider.touchEventConverter
			);

			$pointerOlder.draggable( this.buildDraggableOptions(
				$revisions,
				'.mw-revslider-pointer-container-older'
			) );

			$pointerNewer.draggable( this.buildDraggableOptions(
				$revisions,
				'.mw-revslider-pointer-container-newer'
			) );
		},

		getOlderPointerPos: function () {
			return this.pointerOlder.getPosition();
		},

		getNewerPointerPos: function () {
			return this.pointerNewer.getPosition();
		},

		setOlderPointerPos: function ( pos ) {
			return this.pointerOlder.setPosition( pos );
		},

		setNewerPointerPos: function ( pos ) {
			return this.pointerNewer.setPosition( pos );
		},

		showTooltipsOnMouseMoveHandler: function ( event, lastValidPosition ) {
			var pos = this.getRevisionPositionFromLeftOffset( event.pageX ),
				$hoveredRevisionWrapper, $lastRevisionWrapper;

			if ( pos === lastValidPosition ) {
				return pos;
			}

			$hoveredRevisionWrapper = this.getRevElementAtPosition( this.getRevisionsElement(), pos ).parent();
			$lastRevisionWrapper = this.getRevElementAtPosition( this.getRevisionsElement(), lastValidPosition ).parent();
			this.getRevisionListView().unsetRevisionGhosts( $lastRevisionWrapper );
			this.getRevisionListView().setRevisionHovered( $hoveredRevisionWrapper, event );

			return pos;
		},

		/**
		 * Handler for click events on the pointer container element. Forwards these events to the generic handler for
		 * revision clicks.
		 *
		 * @param {Event} event
		 */
		pointerContainerClickHandler: function ( event ) {
			var clickedPos = this.getRevisionPositionFromLeftOffset( event.pageX ),
				$revisionWrapper = this.getRevElementAtPosition( this.getRevisionsElement(), clickedPos ).parent(),
				hasClickedTop = event.pageY - $revisionWrapper.offset().top < $revisionWrapper.height() / 2;

			this.handleRevisionClick( $revisionWrapper, clickedPos, hasClickedTop );
		},

		/**
		 * Handler for click events on a revisionWrapper element. Forwards these events to the generic handler for
		 * revision clicks.
		 *
		 * @param {Event} event
		 * @param {jQuery} $revisionWrapper
		 */
		revisionWrapperClickHandler: function ( event, $revisionWrapper ) {
			var hasClickedTop = event.pageY - $revisionWrapper.offset().top < $revisionWrapper.height() / 2,
				clickedPos = +$revisionWrapper.find( '.mw-revslider-revision' ).attr( 'data-pos' );

			this.handleRevisionClick( $revisionWrapper, clickedPos, hasClickedTop );
		},

		/**
		 * React on clicks on a revision element and move pointers
		 *
		 * @param {jQuery} $revisionWrapper
		 * @param {number} clickedPos
		 * @param {boolean} hasClickedTop
		 */
		handleRevisionClick: function ( $revisionWrapper, clickedPos, hasClickedTop ) {
			var newNewerPointerPos, newOlderPointerPos;

			if ( hasClickedTop &&
				( $revisionWrapper.hasClass( 'mw-revslider-revision-newer' ) ||
					$revisionWrapper.hasClass( 'mw-revslider-revision-intermediate' ) )
			) {
				newNewerPointerPos = clickedPos;
				newOlderPointerPos = this.pointerOlder.getPosition();
			} else if ( !hasClickedTop &&
				( $revisionWrapper.hasClass( 'mw-revslider-revision-older' ) ||
					$revisionWrapper.hasClass( 'mw-revslider-revision-intermediate' ) )
			) {
				newNewerPointerPos = this.pointerNewer.getPosition();
				newOlderPointerPos = clickedPos;
			} else {
				if ( hasClickedTop ) {
					newNewerPointerPos = clickedPos;
					newOlderPointerPos = clickedPos - 1;
				} else {
					newNewerPointerPos = clickedPos + 1;
					newOlderPointerPos = clickedPos;
				}
			}

			if (
				newOlderPointerPos === newNewerPointerPos ||
				!this.slider.getRevisionList().isValidPosition( newOlderPointerPos ) ||
				!this.slider.getRevisionList().isValidPosition( newNewerPointerPos )
			) {
				return;
			}

			this.updatePointersAndDiffView( newNewerPointerPos, newOlderPointerPos, true );
		},

		/**
		 * @param {jQuery} $revisions
		 */
		addClickHandlerToRevisions: function ( $revisions ) {
			var self = this;
			$revisions.find( '.mw-revslider-revision-wrapper' ).click( function ( event ) {
				self.revisionWrapperClickHandler( event, $( this ) );
			} );
		},

		/**
		 * Build options for the draggable
		 *
		 * @param {jQuery} $revisions
		 * @param {string} containmentClass
		 * @return {Object}
		 */
		buildDraggableOptions: function ( $revisions, containmentClass ) {
			var lastValidLeftPos,
				self = this;

			return {
				axis: 'x',
				grid: [ this.revisionWidth, null ],
				containment: containmentClass,
				start: function () {
					if ( self.pointerIsBlockedByOther( this ) ) {
						return false;
					}
					self.isDragged = true;
					self.getRevisionListView().disableHover();
					self.setPointerDragCursor();
					self.fadeOutPointerLines();
					self.escapePressed = false;
					self.lastOldPointerPosition = self.getOlderPointerPos();
					self.lastNewPointerPosition = self.getNewerPointerPos();
				},
				stop: function () {
					var $p = $( this ),
						relativeIndex = self.getRelativePointerIndex( $p ),
						pointer = self.whichPointer( $p ),
						diff, oldid;

					self.isDragged = false;
					self.getRevisionListView().enableHover();
					self.removePointerDragCursor();

					if ( self.escapePressed ) {
						return;
					}

					mw.track( 'counter.MediaWiki.RevisionSlider.event.pointerMove' );

					pointer.setPosition( self.slider.getOldestVisibleRevisionIndex() + relativeIndex );

					diff = self.getRevElementAtPosition(
						$revisions, self.getNewerPointerPos()
					).data( 'revid' );

					oldid = self.getRevElementAtPosition(
						$revisions, self.getOlderPointerPos()
					).data( 'revid' );

					if ( self.getNewerPointerPos() === self.lastNewPointerPosition &&
						self.getOlderPointerPos() === self.lastOldPointerPosition ) {
						return;
					}

					self.refreshDiffView( diff, oldid, true );
					self.alignPointersAndLines( 0 );
					self.resetRevisionStylesBasedOnPointerPosition( $revisions );
				},
				drag: function ( event, ui ) {
					lastValidLeftPos = self.draggableDragAction(
						event,
						ui,
						this,
						lastValidLeftPos
					);
				},
				revert: function () {
					return self.escapePressed;
				}
			};
		},

		pointerIsBlockedByOther: function ( pointerElement ) {
			var pointer = this.whichPointer( $( pointerElement ) ),
				isNewer = pointer.getView().isNewerPointer();

			return ( isNewer && this.getOlderPointerPos() >= this.slider.getNewestVisibleRevisionIndex() + 1 ) ||
				( !isNewer && this.getNewerPointerPos() <= this.slider.getOldestVisibleRevisionIndex() + 1 );

		},

		draggableDragAction: function ( event, ui, pointer, lastValidLeftPos ) {
			var pos, $revisions, $hoveredRevisionWrapper;

			pos = this.getRevisionPositionFromLeftOffset(
				$( pointer ).offset().left + this.revisionWidth / 2
			);

			if ( pos === lastValidLeftPos ) {
				return pos;
			}

			$revisions = this.getRevisionsElement();
			$hoveredRevisionWrapper = this.getRevElementAtPosition( $revisions, pos ).parent();
			this.getRevisionListView().showTooltip( $hoveredRevisionWrapper );

			return pos;
		},

		getRevisionPositionFromLeftOffset: function ( leftOffset ) {
			var $revisions = this.getRevisionsElement(),
				revisionsX = mw.libs.revisionSlider.correctElementOffsets( $revisions.offset() ).left,
				pos = Math.ceil( Math.abs( leftOffset - revisionsX ) / this.revisionWidth );

			if ( this.dir === 'rtl' ) {
				// pre-loading the revisions on the right side leads to shifted position numbers
				if ( this.slider.isAtStart() ) {
					pos = this.slider.getRevisionsPerWindow() - pos + 1;
				} else {
					pos += this.slider.getRevisionsPerWindow();
				}
			}

			return pos;
		},

		setPointerDragCursor: function () {
			$( '.mw-revslider-pointer, ' +
				'.mw-revslider-pointer-container, ' +
				'.mw-revslider-pointer-container-newer, ' +
				'.mw-revslider-pointer-container-older, ' +
				'.mw-revslider-pointer-line, ' +
				'.mw-revslider-revision-wrapper' )
				.addClass( 'mw-revslider-pointer-grabbing' );
		},

		removePointerDragCursor: function () {
			$( '.mw-revslider-pointer, ' +
				'.mw-revslider-pointer-container, ' +
				'.mw-revslider-pointer-container-newer, ' +
				'.mw-revslider-pointer-container-older, ' +
				'.mw-revslider-pointer-line, ' +
				'.mw-revslider-revision-wrapper' )
				.removeClass( 'mw-revslider-pointer-grabbing' );
		},

		/**
		 * Get the relative index for a pointer.
		 *
		 * @param {jQuery} $pointer
		 * @return {number}
		 */
		getRelativePointerIndex: function ( $pointer ) {
			var pos = $pointer.position().left,
				pointer = this.whichPointer( $pointer );

			if ( this.dir === 'rtl' ) {
				pos = pointer.getView().getAdjustedLeftPositionWhenRtl( pos );
			}
			return Math.ceil( ( pos + this.revisionWidth / 2 ) / this.revisionWidth );
		},

		/**
		 * Loads a new diff and optionally adds a state to the history
		 *
		 * @param {number} diff
		 * @param {number} oldid
		 * @param {boolean} pushState
		 */
		refreshDiffView: function ( diff, oldid, pushState ) {
			this.diffPage.refresh( diff, oldid, this );
			if ( pushState ) {
				this.diffPage.pushState( diff, oldid, this );
			}
		},

		showNextDiff: function () {
			this.updatePointersAndDiffView(
				this.getNewerPointerPos() + 1,
				this.getNewerPointerPos(),
				true
			);
		},

		showPrevDiff: function () {
			this.updatePointersAndDiffView(
				this.getOlderPointerPos(),
				this.getOlderPointerPos() - 1,
				true
			);
		},

		/**
		 * Updates and moves pointers to new positions, resets styles and refreshes diff accordingly
		 *
		 * @param {number} newPointerPos
		 * @param {number} oldPointerPos
		 * @param {boolean} pushState
		 */
		updatePointersAndDiffView: function (
			newPointerPos,
			oldPointerPos,
			pushState
		) {
			this.setNewerPointerPos( newPointerPos );
			this.setOlderPointerPos( oldPointerPos );
			this.alignPointersAndLines();
			this.resetRevisionStylesBasedOnPointerPosition( this.getRevisionsElement() );
			this.refreshDiffView(
				+$( '.mw-revslider-revision[data-pos="' + newPointerPos + '"]' ).attr( 'data-revid' ),
				+$( '.mw-revslider-revision[data-pos="' + oldPointerPos + '"]' ).attr( 'data-revid' ),
				pushState
			);
		},

		/**
		 * @param {jQuery} $revs
		 * @param {number} pos
		 * @return {jQuery}
		 */
		getRevElementAtPosition: function ( $revs, pos ) {
			return $revs.find( 'div.mw-revslider-revision[data-pos="' + pos + '"]' );
		},

		/**
		 * Gets the jQuery element of the older selected revision
		 *
		 * @param {jQuery} $revs
		 * @return {jQuery}
		 */
		getOldRevElement: function ( $revs ) {
			return $revs.find( 'div.mw-revslider-revision[data-revid="' + mw.config.get( 'wgDiffOldId' ) + '"]' );
		},

		/**
		 * Gets the jQuery element of the newer selected revision
		 *
		 * @param {jQuery} $revs
		 * @return {jQuery}
		 */
		getNewRevElement: function ( $revs ) {
			return $revs.find( 'div.mw-revslider-revision[data-revid="' + mw.config.get( 'wgDiffNewId' ) + '"]' );
		},

		/**
		 * Initializes the Pointer objects based on the selected revisions
		 *
		 * @param {jQuery} $oldRevElement
		 * @param {jQuery} $newRevElement
		 */
		initializePointers: function ( $oldRevElement, $newRevElement ) {
			if ( this.getOlderPointerPos() !== 0 || this.getNewerPointerPos() !== 0 ) {
				return;
			}
			if ( $oldRevElement.length === 0 && $newRevElement.length === 0 ) {
				// Note: this is currently caught in init.js
				throw new Error( 'RS-revs-not-specified' );
			}
			if ( $oldRevElement.length !== 0 ) {
				this.setOlderPointerPos( $oldRevElement.data( 'pos' ) );
			} else {
				this.setOlderPointerPos( -1 );
			}
			this.setNewerPointerPos( $newRevElement.data( 'pos' ) );
			this.resetSliderLines();
		},

		/**
		 * Resets the slider lines based on the selected revisions
		 */
		resetSliderLines: function () {
			this.updateOlderSliderLineCSS();
			this.updateNewerSliderLineCSS();
		},

		updateOlderSliderLineCSS: function () {
			var widthToSet = ( this.getOlderDistanceToOldest() + this.getDistanceBetweenPointers() ) *
					this.revisionWidth,
				marginToSet = -this.revisionWidth / 2;

			widthToSet = Math.min( widthToSet, this.calculateSliderContainerWidth() + this.revisionWidth );

			this.setSliderLineCSS(
				$( '.mw-revslider-pointer-container-older' ), widthToSet, marginToSet
			);
		},

		updateNewerSliderLineCSS: function () {
			var widthToSet = ( this.getNewerDistanceToNewest() + this.getDistanceBetweenPointers() + 2 ) *
					this.revisionWidth,
				marginToSet = ( this.getOlderDistanceToOldest() * this.revisionWidth ) -
					this.revisionWidth / 2;

			widthToSet = Math.min( widthToSet, this.calculateSliderContainerWidth() + this.revisionWidth );
			marginToSet = Math.max( marginToSet, -0.5 * this.revisionWidth );

			this.setSliderLineCSS(
				$( '.mw-revslider-pointer-container-newer' ), widthToSet, marginToSet
			);
		},

		setSliderLineCSS: function ( $lineContainer, widthToSet, marginToSet ) {
			if ( this.dir === 'ltr' ) {
				$lineContainer.css( {
					width: widthToSet,
					'margin-left': marginToSet
				} );
			} else {
				$lineContainer.css( {
					width: widthToSet,
					'margin-right': marginToSet + this.revisionWidth
				} );
			}
		},

		getOlderDistanceToOldest: function () {
			return this.getOlderPointerPos() - this.slider.getOldestVisibleRevisionIndex();
		},

		getNewerDistanceToNewest: function () {
			return this.slider.getNewestVisibleRevisionIndex() - this.getNewerPointerPos();
		},

		getDistanceBetweenPointers: function () {
			return this.getNewerPointerPos() - this.getOlderPointerPos();
		},

		/**
		 * Highlights revisions between the pointers
		 *
		 * @param {jQuery} $revisions
		 */
		resetRevisionStylesBasedOnPointerPosition: function ( $revisions ) {
			var olderRevPosition = this.getOlderPointerPos(),
				newerRevPosition = this.getNewerPointerPos(),
				startPosition = this.slider.getOldestVisibleRevisionIndex(),
				endPosition = this.slider.getNewestVisibleRevisionIndex(),
				positionIndex = startPosition;

			$revisions.find( 'div.mw-revslider-revision' )
				.removeClass( 'mw-revslider-revision-old mw-revslider-revision-new' );
			$revisions.find( 'div.mw-revslider-revision-wrapper' )
				.removeClass( 'mw-revslider-revision-intermediate mw-revslider-revision-older mw-revslider-revision-newer' );

			this.getRevElementAtPosition( $revisions, olderRevPosition ).addClass( 'mw-revslider-revision-old' );
			this.getRevElementAtPosition( $revisions, newerRevPosition ).addClass( 'mw-revslider-revision-new' );

			while ( positionIndex <= endPosition ) {
				positionIndex++;
				if ( positionIndex <= olderRevPosition ) {
					this.getRevElementAtPosition( $revisions, positionIndex ).parent().addClass( 'mw-revslider-revision-older' );
				} else if ( positionIndex > olderRevPosition && positionIndex < newerRevPosition ) {
					this.getRevElementAtPosition( $revisions, positionIndex ).parent().addClass( 'mw-revslider-revision-intermediate' );
				} else if ( positionIndex >= newerRevPosition ) {
					this.getRevElementAtPosition( $revisions, positionIndex ).parent().addClass( 'mw-revslider-revision-newer' );
				}
			}
		},

		/**
		 * Redraws the lines for the pointers
		 */
		redrawPointerLines: function () {
			this.fadeInPointerLines();
			$( '.mw-revslider-pointer-line-upper, .mw-revslider-pointer-line-lower' )
				.removeClass( 'mw-revslider-bottom-line mw-revslider-left-line mw-revslider-right-line' );
			this.pointerOlder.getLine().drawLine();
			this.pointerNewer.getLine().drawLine();
		},

		/**
		 * Fades out the lines for the pointers
		 */
		fadeOutPointerLines: function () {
			$( '.mw-revslider-pointer-line' ).fadeTo( 0, 0.3 );
		},

		/**
		 * Fades in the lines for the pointers
		 */
		fadeInPointerLines: function () {
			$( '.mw-revslider-pointer-line' ).fadeTo( 0, 1 );
		},

		/**
		 * @return {number}
		 */
		calculateSliderContainerWidth: function () {
			return Math.min(
				this.slider.getRevisionList().getLength(),
				mw.libs.revisionSlider.calculateRevisionsPerWindow( this.containerMargin + this.outerMargin, this.revisionWidth )
			) * this.revisionWidth;
		},

		/**
		 * Slide the view to the next chunk of older / newer revisions
		 *
		 * @param {number} direction - Either -1, 0 or 1
		 * @param {number|string} [duration]
		 */
		slideView: function ( direction, duration ) {
			var animateObj,
				$animatedElement = this.$element.find( '.mw-revslider-revisions-container' ),
				self = this;

			this.slider.slide( direction );
			this.pointerOlder.getView().getElement().draggable( 'disable' );
			this.pointerNewer.getView().getElement().draggable( 'disable' );

			if ( this.slider.isAtStart() ) {
				this.backwardArrowButton.setDisabled( true );
			} else {
				this.backwardArrowButton.setDisabled( false );
			}
			if ( this.slider.isAtEnd() ) {
				this.forwardArrowButton.setDisabled( true );
			} else {
				this.forwardArrowButton.setDisabled( false );
			}

			animateObj = { scrollLeft: this.slider.getOldestVisibleRevisionIndex() * this.revisionWidth };
			if ( this.dir === 'rtl' ) {
				animateObj.scrollLeft = this.getRtlScrollLeft( $animatedElement, animateObj.scrollLeft );
			}

			$animatedElement.animate(
				animateObj,
				duration,
				null,
				function () {
					self.pointerOlder.getView().getElement().draggable( 'enable' );
					self.pointerNewer.getView().getElement().draggable( 'enable' );

					if ( self.slider.isAtStart() && !self.noMoreOlderRevisions ) {
						self.addOlderRevisionsIfNeeded( $( '.mw-revslider-revision-slider' ) );
					}
					if ( self.slider.isAtEnd() && !self.noMoreNewerRevisions ) {
						self.addNewerRevisionsIfNeeded( $( '.mw-revslider-revision-slider' ) );
					}

					self.resetRevisionStylesBasedOnPointerPosition(
						self.getRevisionsElement()
					);
				}
			);

			this.alignPointersAndLines( duration );
		},

		/**
		 * @param {jQuery} $element
		 * @param {number} scrollLeft
		 * @return {number}
		 */
		getRtlScrollLeft: function ( $element, scrollLeft ) {
			if ( this.rtlScrollLeftType === 'reverse' ) {
				return scrollLeft;
			}
			if ( this.rtlScrollLeftType === 'negative' ) {
				return -scrollLeft;
			}
			return $element.prop( 'scrollWidth' ) - $element.width() - scrollLeft;
		},

		/**
		 * Visually move pointers to the positions set and reset pointer- and slider-lines
		 *
		 * @param {number|string} [duration]
		 */
		alignPointersAndLines: function ( duration ) {
			var self = this;

			this.fadeOutPointerLines();

			this.pointerOlder.getView()
				.slideToSideOrPosition( this.slider, duration )
				.promise().done( function () {
					self.resetSliderLines();
					self.redrawPointerLines();
				} );
			this.pointerNewer.getView()
				.slideToSideOrPosition( this.slider, duration )
				.promise().done( function () {
					self.resetSliderLines();
					self.redrawPointerLines();
				} );
		},

		/**
		 * Returns the Pointer object that belongs to the passed element
		 *
		 * @param {jQuery} $e
		 * @return {Pointer}
		 */
		whichPointer: function ( $e ) {
			return $e.hasClass( 'mw-revslider-pointer-older' ) ? this.pointerOlder : this.pointerNewer;
		},

		/**
		 * @param {jQuery} $slider
		 */
		addNewerRevisionsIfNeeded: function ( $slider ) {
			var api = new mw.libs.revisionSlider.Api( mw.util.wikiScript( 'api' ) ),
				self = this,
				revisions = this.slider.getRevisionList().getRevisions(),
				revisionCount = mw.libs.revisionSlider.calculateRevisionsPerWindow( this.containerMargin + this.outerMargin, this.revisionWidth ),
				revs;
			if ( this.noMoreNewerRevisions || !this.slider.isAtEnd() ) {
				return;
			}
			api.fetchRevisionData( mw.config.get( 'wgPageName' ), {
				startId: revisions[ revisions.length - 1 ].getId(),
				dir: 'newer',
				limit: revisionCount + 1,
				knownUserGenders: this.slider.getRevisionList().getUserGenders()
			} ).then( function ( data ) {
				revs = data.revisions.slice( 1 );
				if ( revs.length === 0 ) {
					self.noMoreNewerRevisions = true;
					return;
				}

				self.addRevisionsAtEnd( $slider, revs );

				if ( data.continue === undefined ) {
					self.noMoreNewerRevisions = true;
				}
			} );
		},

		/**
		 * @param {jQuery} $slider
		 */
		addOlderRevisionsIfNeeded: function ( $slider ) {
			var api = new mw.libs.revisionSlider.Api( mw.util.wikiScript( 'api' ) ),
				self = this,
				revisions = this.slider.getRevisionList().getRevisions(),
				revisionCount = mw.libs.revisionSlider.calculateRevisionsPerWindow( this.containerMargin + this.outerMargin, this.revisionWidth ),
				revs,
				precedingRevisionSize = 0;
			if ( this.noMoreOlderRevisions || !this.slider.isAtStart() ) {
				return;
			}
			api.fetchRevisionData( mw.config.get( 'wgPageName' ), {
				startId: revisions[ 0 ].getId(),
				dir: 'older',
				// fetch an extra revision if there are more older revision than the current "window",
				// this makes it possible to correctly set a size of the bar related to the oldest revision to add
				limit: revisionCount + 2,
				knownUserGenders: this.slider.getRevisionList().getUserGenders()
			} ).then( function ( data ) {
				revs = data.revisions.slice( 1 ).reverse();
				if ( revs.length === 0 ) {
					self.noMoreOlderRevisions = true;
					return;
				}

				if ( revs.length === revisionCount + 1 ) {
					precedingRevisionSize = revs[ 0 ].size;
					revs = revs.slice( 1 );
				}
				self.addRevisionsAtStart( $slider, revs, precedingRevisionSize );

				if ( data.continue === undefined ) {
					self.noMoreOlderRevisions = true;
				}
			} );
		},

		/**
		 * @param {jQuery} $slider
		 * @param {Array} revs
		 */
		addRevisionsAtEnd: function ( $slider, revs ) {
			var revPositionOffset = this.slider.getRevisionList().getLength(),
				$revisions = $slider.find( '.mw-revslider-revisions-container .mw-revslider-revisions' ),
				revisionsToRender,
				$addedRevisions;

			this.slider.getRevisionList().push( mw.libs.revisionSlider.makeRevisions( revs ) );

			// Pushed revisions have their relative sizes set correctly with regard to the last previously
			// loaded revision. This should be taken into account when rendering newly loaded revisions (tooltip)
			revisionsToRender = this.slider.getRevisionList().slice( revPositionOffset );

			$addedRevisions = new mw.libs.revisionSlider.RevisionListView( revisionsToRender, this.dir ).render( this.revisionWidth, revPositionOffset );

			$addedRevisions.find( '.mw-revslider-revision-wrapper' ).each( function () {
				$revisions.append( $( this ) );
			} );

			this.addClickHandlerToRevisions( this.getRevisionsElement() );

			if ( this.shouldExpandSlider( $slider ) ) {
				this.expandSlider( $slider );
			}

			this.getRevisionListView().adjustRevisionSizes( $slider );

			if ( !this.slider.isAtEnd() ) {
				this.forwardArrowButton.setDisabled( false );
			}
		},

		/**
		 * @param {jQuery} $slider
		 * @param {Array} revs
		 * @param {number} precedingRevisionSize optional size of the revision preceding the first of revs,
		 *                                        used to correctly determine first revision's relative size
		 */
		addRevisionsAtStart: function ( $slider, revs, precedingRevisionSize ) {
			var self = this,
				$revisions = $slider.find( '.mw-revslider-revisions-container .mw-revslider-revisions' ),
				$revisionContainer = $slider.find( '.mw-revslider-revisions-container' ),
				revisionsToRender,
				$addedRevisions,
				revIdOld, revIdNew,
				revisionStyleResetRequired = false,
				$oldRevElement,
				scrollLeft;

			this.slider.getRevisionList().unshift( mw.libs.revisionSlider.makeRevisions( revs ), precedingRevisionSize );

			$slider.find( '.mw-revslider-revision' ).each( function () {
				$( this ).attr( 'data-pos', parseInt( $( this ).attr( 'data-pos' ), 10 ) + revs.length );
			} );

			// Pushed (unshifted) revisions have their relative sizes set correctly with regard to the last previously
			// loaded revision. This should be taken into account when rendering newly loaded revisions (tooltip)
			revisionsToRender = this.slider.getRevisionList().slice( 0, revs.length );

			$addedRevisions = new mw.libs.revisionSlider.RevisionListView( revisionsToRender, this.dir ).render( this.revisionWidth );

			this.addClickHandlerToRevisions( this.getRevisionsElement() );

			if ( this.getOlderPointerPos() !== -1 ) {
				this.setOlderPointerPos( this.getOlderPointerPos() + revisionsToRender.getLength() );
			} else {
				// Special case: old revision has been previously not loaded, need to initialize correct position
				$oldRevElement = this.getOldRevElement( $addedRevisions );
				if ( $oldRevElement.length !== 0 ) {
					this.setOlderPointerPos( $oldRevElement.data( 'pos' ) );
					revisionStyleResetRequired = true;
				}

			}
			this.setNewerPointerPos( this.getNewerPointerPos() + revisionsToRender.getLength() );

			$( $addedRevisions.find( '.mw-revslider-revision-wrapper' ).get().reverse() ).each( function () { // TODO: this is horrible
				$revisions.prepend( $( this ) );
			} );

			if ( revisionStyleResetRequired ) {
				this.resetRevisionStylesBasedOnPointerPosition( $slider );
			}

			this.slider.setFirstVisibleRevisionIndex( this.slider.getOldestVisibleRevisionIndex() + revisionsToRender.getLength() );

			revIdOld = self.getRevElementAtPosition( $revisions, this.getOlderPointerPos() ).data( 'revid' );
			revIdNew = self.getRevElementAtPosition( $revisions, this.getNewerPointerPos() ).data( 'revid' );
			this.diffPage.replaceState( revIdNew, revIdOld, this );

			scrollLeft = this.slider.getOldestVisibleRevisionIndex() * this.revisionWidth;
			$revisionContainer.scrollLeft( scrollLeft );
			if ( this.dir === 'rtl' ) {
				$revisionContainer.scrollLeft( self.getRtlScrollLeft( $revisionContainer, scrollLeft ) );
			}

			if ( this.shouldExpandSlider( $slider ) ) {
				this.expandSlider( $slider );
			}

			this.getRevisionListView().adjustRevisionSizes( $slider );

			this.backwardArrowButton.setDisabled( false );
		},

		/**
		 * @param {jQuery} $slider
		 * @return {boolean}
		 */
		shouldExpandSlider: function ( $slider ) {
			var sliderWidth = $slider.width(),
				maxAvailableWidth = this.calculateSliderContainerWidth() + this.containerMargin;

			return !( this.noMoreNewerRevisions && this.noMoreOlderRevisions ) && sliderWidth < maxAvailableWidth;
		},

		/**
		 * @param {jQuery} $slider
		 */
		expandSlider: function ( $slider ) {
			var containerWidth = this.calculateSliderContainerWidth(),
				expandedRevisionWindowCapacity;

			$slider.css( { width: ( containerWidth + this.containerMargin ) + 'px' } );
			$slider.find( '.mw-revslider-revisions-container' ).css( { width: containerWidth + 'px' } );
			$slider.find( '.mw-revslider-pointer-container' ).css( { width: containerWidth + this.revisionWidth - 1 + 'px' } );

			expandedRevisionWindowCapacity = $slider.find( '.mw-revslider-revisions-container' ).width() / this.revisionWidth;
			this.slider.setRevisionsPerWindow( expandedRevisionWindowCapacity );

			this.slideView( Math.floor( ( this.getNewerPointerPos() - 1 ) / expandedRevisionWindowCapacity ), 0 );
		},

		/**
		 * @return {RevisionListView}
		 */
		getRevisionListView: function () {
			return this.slider.getRevisionList().getView();
		},

		/**
		 * @return {jQuery}
		 */
		getRevisionsElement: function () {
			return this.getRevisionListView().getElement();
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.SliderView = SliderView;
}( mediaWiki, jQuery ) );
