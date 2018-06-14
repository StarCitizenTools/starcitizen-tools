( function ( mw, $ ) {
	/**
	 * @param {Revision[]} revs
	 * @constructor
	 */
	var RevisionList = function ( revs ) {
		this.revisions = [];
		this.initialize( revs );
		this.view = new mw.libs.revisionSlider.RevisionListView( this );
	};

	$.extend( RevisionList.prototype, {
		/**
		 * @type {Revision[]}
		 */
		revisions: [],

		/**
		 * @type {RevisionListView}
		 */
		view: null,

		/**
		 * Initializes the RevisionList from a list of Revisions
		 *
		 * @param {Revision[]} revs
		 */
		initialize: function ( revs ) {
			var i, rev;

			for ( i = 0; i < revs.length; i++ ) {
				rev = revs[ i ];
				rev.setRelativeSize( i > 0 ? rev.getSize() - revs[ i - 1 ].getSize() : rev.getSize() );

				this.revisions.push( rev );
			}
		},

		/**
		 * @return {number}
		 */
		getBiggestChangeSize: function () {
			var max = 0,
				i;

			for ( i = 0; i < this.revisions.length; i++ ) {
				max = Math.max( max, Math.abs( this.revisions[ i ].getRelativeSize() ) );
			}

			return max;
		},

		/**
		 * @return {Revision[]}
		 */
		getRevisions: function () {
			return this.revisions;
		},

		/**
		 * @return {number}
		 */
		getLength: function () {
			return this.revisions.length;
		},

		/**
		 * @return {RevisionListView}
		 */
		getView: function () {
			return this.view;
		},

		getUserGenders: function () {
			var userGenders = {};
			this.revisions.forEach( function ( revision ) {
				if ( revision.getUser() ) {
					userGenders[ revision.getUser() ] = revision.getUserGender();
				}
			} );
			return userGenders;
		},

		/**
		 * Adds revisions to the end of the list.
		 *
		 * @param {Revision[]} revs
		 */
		push: function ( revs ) {
			var i, rev;
			for ( i = 0; i < revs.length; i++ ) {
				rev = revs[ i ];
				rev.setRelativeSize(
					i > 0 ?
						rev.getSize() - revs[ i - 1 ].getSize() :
						rev.getSize() - this.revisions[ this.revisions.length - 1 ].getSize()
				);

				this.revisions.push( rev );
			}
		},

		/**
		 * Adds revisions to the beginning of the list.
		 *
		 * @param {Revision[]} revs
		 * @param {number} sizeBefore optional size of the revision preceding the first of revs, defaults to 0
		 */
		unshift: function ( revs, sizeBefore ) {
			var originalFirstRev = this.revisions[ 0 ],
				i, rev;
			sizeBefore = sizeBefore || 0;

			originalFirstRev.setRelativeSize( originalFirstRev.getSize() - revs[ revs.length - 1 ].getSize() );
			for ( i = revs.length - 1; i >= 0; i-- ) {
				rev = revs[ i ];
				rev.setRelativeSize( i > 0 ? rev.getSize() - revs[ i - 1 ].getSize() : rev.getSize() - sizeBefore );

				this.revisions.unshift( rev );
			}
		},

		/**
		 * Returns a subset of the list.
		 *
		 * @param {number} begin
		 * @param {number} end
		 * @return {RevisionList}
		 */
		slice: function ( begin, end ) {
			var slicedList = new mw.libs.revisionSlider.RevisionList( [] );
			slicedList.view = new mw.libs.revisionSlider.RevisionListView( slicedList );
			slicedList.revisions = this.revisions.slice( begin, end );
			return slicedList;
		},

		/**
		 * @param {number} pos
		 * @return {boolean}
		 */
		isValidPosition: function ( pos ) {
			return pos > 0 && pos <= this.getLength();
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.RevisionList = RevisionList;

	/**
	 * Transforms an array of revision data returned by MediaWiki API (including user gender information) into
	 * an array of Revision objects
	 *
	 * @param {Array} revs
	 * @return {Revision[]}
	 */
	mw.libs.revisionSlider.makeRevisions = function ( revs ) {
		return revs.map( function ( revData ) {
			return new mw.libs.revisionSlider.Revision( revData );
		} );
	};
}( mediaWiki, jQuery ) );
