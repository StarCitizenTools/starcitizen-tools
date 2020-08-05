( function ( mw, $ ) {
	/* global moment:false */
	/**
	 * @param {Object} data - Containing keys `id`, `size`, `comment`, `parsedcomment`, `timestamp`, `user` and `minor`
	 * @constructor
	 */
	var Revision = function ( data ) {
		this.id = data.revid;
		this.size = data.size;
		this.timestamp = data.timestamp;
		this.minor = data.hasOwnProperty( 'minor' ) && ( data.minor || data.minor === '' );

		// Comments and users can be suppressed thus we must check if they exist
		if ( typeof data.comment !== 'undefined' ) {
			this.comment = data.comment;
		}
		if ( typeof data.parsedcomment !== 'undefined' ) {
			this.parsedComment = data.parsedcomment;
		}
		if ( typeof data.user !== 'undefined' ) {
			this.user = data.user;
			if ( typeof data.userGender !== 'undefined' ) {
				this.userGender = data.userGender;
			}
		}
	};

	$.extend( Revision.prototype, {
		/**
		 * @type {number}
		 */
		id: 0,

		/**
		 * @type {number}
		 */
		size: 0,

		/**
		 * @type {string}
		 */
		comment: '',

		/**
		 * @type {boolean}
		 */
		minor: false,

		/**
		 * @type {string}
		 */
		parsedComment: '',

		/**
		 * @type {string}
		 */
		timestamp: '',

		/**
		 * @type {string}
		 */
		user: '',

		/**
		 * @type {string}
		 */
		userGender: '',

		/**
		 * @type {number}
		 */
		relativeSize: 0,

		/**
		 * @return {number}
		 */
		getId: function () {
			return this.id;
		},

		/**
		 * @return {number}
		 */
		getSize: function () {
			return this.size;
		},

		/**
		 * @return {boolean}
		 */
		isMinor: function () {
			return this.minor;
		},

		/**
		 * @return {string}
		 */
		getParsedComment: function () {
			return this.parsedComment;
		},

		/**
		 * @return {boolean}
		 */
		hasEmptyComment: function () {
			return this.getComment().trim().length === 0;
		},

		/**
		 * @return {string}
		 */
		getComment: function () {
			return this.comment;
		},

		/**
		 * Uses moment.js to format the date
		 *
		 * @param {string} rawDate
		 * @return {string}
		 */
		formatDate: function ( rawDate ) {
			var offset = parseInt( mw.libs.revisionSlider.userOffset );
			return moment( rawDate ).utcOffset( offset ).format( 'LLL' );
		},

		/**
		 * @return {string}
		 */
		getFormattedDate: function () {
			return this.formatDate( this.timestamp );
		},

		/**
		 * @return {string}
		 */
		getUser: function () {
			return this.user;
		},

		/**
		 * @return {string}
		 */
		getUserGender: function () {
			return this.userGender;
		},

		/**
		 * @param {number} size
		 */
		setRelativeSize: function ( size ) {
			this.relativeSize = size;
		},

		/**
		 * @return {number}
		 */
		getRelativeSize: function () {
			return this.relativeSize;
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.Revision = Revision;
}( mediaWiki, jQuery ) );
