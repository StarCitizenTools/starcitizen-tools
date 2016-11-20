( function ( mw, $ ) {
	var revisionTickWidth = 16,
		containerMargin = 80, // 2 * arrow + margins
		$container = null,
		$revisionSlider = null,
		revs = [],
		pointerPosL = -1,
		pointerPosR = -1;

	// Function called when a tick on the slider is clicked
	// Params: v1 - Left revision ID; v2 - Right revision ID
	// function refresh( v1, v2 ) {
	// 	if( v1 === -1 || v2 === -1 ) return;
	//
	// 	var $url = gServer + gScript + '?title=' + gPageName + '&diff=' + v2 + '&oldid=' + v1;
	// 	location.href = $url;
	// }

	function getComposedRevData( revs ) {
		var max = 0,
			changeSize = 0,
			section,
			sectionMap = {},
			result,
			i,
			sectionName;

		for ( i = 1; i < revs.length; i++ ) {
			changeSize = Math.abs( revs[ i ].getSize() - revs[ i - 1 ].getSize() );
			section = revs[ i ].getSection();
			if ( changeSize > max ) {
				max = changeSize;
			}
			if ( section.length > 0 && !( section in sectionMap ) ) {
				sectionMap[ section ] = '';
			}
		}

		i = 0;
		for ( sectionName in sectionMap ) {
			sectionMap[ sectionName ] = mw.libs.revisionSlider.rainbow( Object.keys( sectionMap ).length, i );
			i++;
		}

		result = {
			maxChangeSize: max,
			sectionMap: sectionMap
		};

		return result;
	}

	// Setting the tick marks on the slider
	// Params: element - jQuery slider; revs - revisions data from API
	function setSliderTicks( element, revs ) {
		var $slider = $( element ),
			revData = getComposedRevData( revs ),
			maxChangeSizeLogged = Math.log( revData.maxChangeSize ),
			i, diffSize, relativeChangeSize, section, html;

		for ( i = 1; i < revs.length; i++ ) {
			diffSize = revs[ i ].getSize() - revs[ i - 1 ].getSize();
			relativeChangeSize = Math.ceil( 65.0 * Math.log( Math.abs( diffSize ) ) / maxChangeSizeLogged ) + 5;
			section = revs[ i ].getSection();
			html = '<b>' + revs[ i ].getFormattedDate() + '</b><br>';

			html += mw.html.escape( revs[ i ].getUser() ) + '<br>';
			if ( revs[ i ].getComment() !== '' ) {
				html += '<br><i>' + mw.html.escape( revs[ i ].getParsedComment() ) + '</i>';
			}
			html += '<br>' + diffSize + ' byte';

			$( '<div class="ui-slider-tick-mark revision" title="<center>' + html + '</center>"/>' )
				.css( {
					left: revisionTickWidth * ( i - 1 ) + 'px',
					height: relativeChangeSize + 'px',
					width: revisionTickWidth + 'px',
					top: diffSize > 0 ? '-' + relativeChangeSize + 'px' : 0,
					background: revData.sectionMap[ section ] || 'black'
				} )
				.tipsy( {
					gravity: 's',
					html: true,
					fade: true
				} )
				.appendTo( $slider );
			$( '<div class="stopper"/>' )
				.css( {
					left: revisionTickWidth * ( i - 1 ) + 'px',
					width: revisionTickWidth + 'px'
				} )
				.appendTo( $slider );
		}
	}

	/**
	 * Checks whether pointerPos is between start and end
	 *
	 * @param {int} pointerPos
	 * @param {int} start
	 * @param {int} end
	 * @return {boolean}
	 */
	function isPointerInRange( pointerPos, start, end ) {
		return pointerPos >= start &&
			pointerPos <= Math.min( revs.length, end );
	}

	/**
	 * Slowly slides/scrolls $container one viewport in a given direction
	 *
	 * @param {jQuery} $container
	 * @param {int} direction
	 */
	function slide( $container, direction ) {
		$container.animate( {
			scrollLeft: $container.scrollLeft() + ( $container.width() * direction )
		} );
	}

	/**
	 * Determines the revision from a position in the revisionsContainer
	 *
	 * @param {int} pos
	 * @return {number}
	 */
	function revisionOfPosition( pos ) {
		return Math.floor( pos / revisionTickWidth );
	}

	/**
	 * Slides a $pointer to a position
	 *
	 * @param {jQuery} $pointer
	 * @param {int} pos
	 */
	function slideToPosition( $pointer, pos ) {
		var containerOffset = $container.offset().left - $revisionSlider.offset().left,
			left = ( pos % 100 ) * revisionTickWidth;

		$pointer.animate( { left: left + containerOffset } );
	}

	/**
	 * Slides a $pointer to the side of the slider
	 *
	 * @param {jQuery} $pointer
	 * @param {int} pointerPos
	 * @param {int} direction
	 */
	function slideToSide( $pointer, pointerPos, direction ) {
		var containerOffset = $revisionSlider.find( '.arrow' ).outerWidth() + 20, // 20 == margin right
			isLeft = pointerPos < revisionOfPosition( $container.scrollLeft() ) + direction * revisionOfPosition( $container.width() ),
			sideFactor = isLeft ? -1 : 1,
			sideOffset = 3 * revisionTickWidth * sideFactor / 2,
			offsetRight = $pointer.hasClass( 'left-pointer' ) ? -revisionTickWidth : 0,
			xPos = isLeft ? containerOffset : $container.width() + containerOffset;

		$pointer.animate( { left: xPos + offsetRight + sideOffset } );
	}

	function getSectionLegend( revs ) {
		var revData = getComposedRevData( revs ),
			html = '<div class="revisions-legend">',
			sectionName;
		for ( sectionName in revData.sectionMap ) {
			html += '<span class="rvslider-legend-box" style="color:' + revData.sectionMap[ sectionName ] + ';"> ■</span>' + sectionName;
		}
		return html + '</div>';
	}

	function getMaxTicksPerPage() {
		return Math.floor( ( $( '#mw-content-text' ).width() - containerMargin ) / revisionTickWidth );
	}

	function getTickContainerWidth( numberOfTicks, maxNumberOfTicks, maxWidthPerTick ) {
		return Math.min( numberOfTicks, maxNumberOfTicks ) * maxWidthPerTick;
	}

	function initializeRevs( revs ) {
		var revisions = [],
			i;

		for ( i = 0; i < revs.length; i++ ) {
			revisions.push( new mw.libs.revisionSlider.Revision( revs[ i ] ) );
		}

		return revisions;
	}

	function addSlider( revs ) {
		var maxNumberOfTicks = getMaxTicksPerPage(),
			containerWidth = getTickContainerWidth( revs.length, maxNumberOfTicks, revisionTickWidth ),
			$revisions = $( '<div class="revisions"></div>' ),
			$leftPointer = $( '<div class="pointer left-pointer" />' ),
			$rightPointer = $( '<div class="pointer right-pointer" />' );

		$revisionSlider = $( '<div class="revision-slider" />' )
			.css( {
				width: containerWidth + containerMargin + 'px'
			} )
			.append( $( '<a class="arrow left-arrow" data-dir="-1"></a>' ) )
			.append( $( '<div class="revisions-container" />' )
				.css( {
					width: containerWidth  + 'px'
				} )
				.append( $revisions ) )
			.append( $( '<a class="arrow right-arrow" data-dir="1"></a>' ) )
			.append( $( '<div style="clear: both" />' ) )
			.append(
				$( '<div class="pointer-container" />' )
					.css( {
						left: 40 - revisionTickWidth + 'px', // 40 == arrow + margin right
						width: containerWidth + revisionTickWidth * 1.5 + 'px'
					} )
					.append( $leftPointer )
					.append( $rightPointer )
			);

		$container = $revisionSlider.find( '.revisions-container' );

		$revisionSlider.find( '.arrow' ).click( function () {
			var direction = $( this ).data( 'dir' ),
				newStart = revisionOfPosition(
						Math.min( $container.find( '.revisions' ).width() - $container.width(), Math.max( 0, $container.scrollLeft() ) )
					) + ( direction * revisionOfPosition( $container.width() ) ),
				newEnd = newStart + revisionOfPosition( $container.width() );

			if ( isPointerInRange( pointerPosL, newStart, newEnd ) ) {
				slideToPosition( $leftPointer, pointerPosL );
			} else {
				slideToSide( $leftPointer, pointerPosL, direction );
			}

			if ( isPointerInRange( pointerPosR, newStart, newEnd ) ) {
				slideToPosition( $rightPointer, pointerPosR );
			} else {
				slideToSide( $rightPointer, pointerPosR, direction );
			}

			slide( $container, direction );
		} );

		setSliderTicks( $revisions, revs );

		$revisionSlider.find( '.pointer' ).draggable( {
			axis: 'x',
			snap: '.stopper',
			containment: '.pointer-container',
			stop: function () {
				var posLeft = parseInt( $( this ).css( 'left' ), revisionTickWidth ),
					offset = $revisionSlider.find( '.arrow' ).outerWidth() + 20,
					pos = Math.round( ( posLeft + $container.scrollLeft() - offset ) / containerWidth );

				if ( $( this ).hasClass( 'left-pointer' ) ) {
					pointerPosL = pos;
				} else {
					pointerPosR = pos;
				}

				// refresh( pointerPosL, pointerPosR );
			}
		} );

		$( '#revision-slider-placeholder' ).remove();

		$( '#revision-slider-container' )
			.append( $revisionSlider )
			.append( getSectionLegend( revs ) );

		slideToSide( $leftPointer, -1, 1 );
		slideToSide( $rightPointer, -1, 1 );
	}

	mw.loader.using( [ 'jquery.ui.draggable', 'jquery.ui.tooltip', 'jquery.tipsy' ], function () {
		$( function () {
			mw.libs.revisionSlider.fetchRevisions( {
				pageName: mw.config.get( 'wgPageName' ),
				startId: mw.config.get( 'wgCurRevisionId' ),

				success: function ( data ) {
					revs = data.query.pages[ 0 ].revisions;
					if ( !revs ) {
						return;
					}
					revs.reverse();

					addSlider( initializeRevs( revs ) );
				}
			} );
		} );
	} );

}( mediaWiki, jQuery ) );
