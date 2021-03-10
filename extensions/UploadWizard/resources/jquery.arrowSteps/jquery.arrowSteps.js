/*!
 * jQuery arrowSteps plugin
 * Copyright Neil Kandalgaonkar, 2010
 *
 * This work is licensed under the terms of the GNU General Public License,
 * version 2 or later.
 * (see http://www.fsf.org/licensing/licenses/gpl.html).
 * Derivative works and later versions of the code must be free software
 * licensed under the same or a compatible license.
 */

/**
 * @class jQuery.plugin.arrowSteps
 */
( function () {
	/**
	 * Show users their progress through a series of steps, via a row of items that fit
	 * together like arrows. One item can be highlighted at a time.
	 *
	 *     <ul id="robin-hood-daffy">
	 *       <li id="guard"><div>Guard!</div></li>
	 *       <li id="turn"><div>Turn!</div></li>
	 *       <li id="parry"><div>Parry!</div></li>
	 *       <li id="dodge"><div>Dodge!</div></li>
	 *       <li id="spin"><div>Spin!</div></li>
	 *       <li id="ha"><div>Ha!</div></li>
	 *       <li id="thrust"><div>Thrust!</div></li>
	 *     </ul>
	 *
	 *     <script>
	 *       $( '#robin-hood-daffy' ).arrowSteps();
	 *     </script>
	 *
	 * @return {jQuery}
	 * @chainable
	 */
	$.fn.arrowSteps = function () {
		var $steps, width,
			$el = this;

		$el.addClass( 'arrowSteps' );
		$steps = $el.find( 'li' );

		width = Math.floor( 100 / $steps.length * 100 ) / 100;
		$steps.css( 'width', width + '%' );

		// Every step except the last one has an arrow pointing forward:
		// at the right hand side in LTR languages, and at the left hand side in RTL.
		$steps.filter( ':not(:last-child)' ).addClass( 'arrow' );

		$el.data( 'arrowSteps', $steps );

		return this;
	};

	/**
	 * Highlights the element selected by the selector.
	 *
	 *       $( '#robin-hood-daffy' ).arrowStepsHighlight( '#guard' );
	 *       // 'Guard!' is highlighted.
	 *
	 *       // ... user completes the 'guard' step ...
	 *
	 *       $( '#robin-hood-daffy' ).arrowStepsHighlight( '#turn' );
	 *       // 'Turn!' is highlighted.
	 *
	 * @param {string} selector
	 */
	$.fn.arrowStepsHighlight = function ( selector ) {
		var $previous,
			$steps = this.data( 'arrowSteps' );
		$steps.each( function () {
			var $step = $( this );
			if ( $step.is( selector ) ) {
				if ( $previous ) {
					$previous.addClass( 'tail' );
				}
				$step.addClass( 'head' );
			} else {
				$step.removeClass( 'head tail lasthead' );
			}
			$previous = $step;
		} );
	};

	/**
	 * @class jQuery
	 * @mixins jQuery.plugin.arrowSteps
	 */
}() );
