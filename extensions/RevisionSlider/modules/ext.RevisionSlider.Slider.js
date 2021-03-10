( function ( mw, $ ) {
	/**
	 * Module handling the slider logic of the RevisionSlider
	 *
	 * @param {RevisionList} revisions
	 * @constructor
	 */
	var Slider = function ( revisions ) {
		this.revisions = revisions;
		this.view = new mw.libs.revisionSlider.SliderView( this );
	};

	$.extend( Slider.prototype, {
		/**
		 * @type {RevisionList}
		 */
		revisions: null,

		/**
		 * @type {number}
		 */
		oldestVisibleRevisionIndex: 0,

		/**
		 * @type {number}
		 */
		revisionsPerWindow: 0,

		/**
		 * @type {SliderView}
		 */
		view: null,

		/**
		 * @return {RevisionList}
		 */
		getRevisionList: function () {
			return this.revisions;
		},

		/**
		 * @return {SliderView}
		 */
		getView: function () {
			return this.view;
		},

		/**
		 * Sets the number of revisions that are visible at once (depending on browser window size)
		 *
		 * @param {number} n
		 */
		setRevisionsPerWindow: function ( n ) {
			this.revisionsPerWindow = n;
		},

		/**
		 * @return {number}
		 */
		getRevisionsPerWindow: function () {
			return this.revisionsPerWindow;
		},

		/**
		 * Returns the index of the oldest revision that is visible in the current window
		 *
		 * @return {number}
		 */
		getOldestVisibleRevisionIndex: function () {
			return this.oldestVisibleRevisionIndex;
		},

		/**
		 * Returns the index of the newest revision that is visible in the current window
		 *
		 * @return {number}
		 */
		getNewestVisibleRevisionIndex: function () {
			return this.oldestVisibleRevisionIndex + this.revisionsPerWindow - 1;
		},

		/**
		 * @return {boolean}
		 */
		isAtStart: function () {
			return this.getOldestVisibleRevisionIndex() === 0 || this.revisions.getLength() <= this.revisionsPerWindow;
		},

		/**
		 * @return {boolean}
		 */
		isAtEnd: function () {
			return this.getNewestVisibleRevisionIndex() === this.revisions.getLength() - 1 || this.revisions.getLength() <= this.revisionsPerWindow;
		},

		/**
		 * Sets the index of the first revision that is visible in the current window
		 *
		 * @param {number} value
		 */
		setFirstVisibleRevisionIndex: function ( value ) {
			this.oldestVisibleRevisionIndex = value;
		},

		/**
		 * Sets the new oldestVisibleRevisionIndex after sliding in a direction
		 *
		 * @param {number} direction - Either -1, 0 or 1
		 */
		slide: function ( direction ) {
			var highestPossibleFirstRev = this.revisions.getLength() - this.revisionsPerWindow;

			this.oldestVisibleRevisionIndex += direction * this.revisionsPerWindow;
			this.oldestVisibleRevisionIndex = Math.min( this.oldestVisibleRevisionIndex, highestPossibleFirstRev );
			this.oldestVisibleRevisionIndex = Math.max( 0, this.oldestVisibleRevisionIndex );
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.Slider = Slider;
}( mediaWiki, jQuery ) );
