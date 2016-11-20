( function ( mw, $ ) {

	var Revision = function ( data ) {
		this.size = data.size;
		this.comment = data.comment;
		this.parsedComment = data.parsedcomment;
		this.timestamp = data.timestamp;
		this.user = data.user;
	};

	$.extend( Revision.prototype, {
		/**
		 * @type {int}
		 */
		size: 0,

		/**
		 * @type {string}
		 */
		comment: '',

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

		getSize: function () {
			return this.size;
		},

		getParsedComment: function () {
			return this.parsedComment;
		},

		getComment: function () {
			return this.comment;
		},

		getSection: function () {
			var comment = this.getComment();
			comment = comment.match(
				new RegExp( '(/\\* [^\\*]* \\*/)', 'gi' )
			);
			if ( !comment ) {
				return '';
			}
			return comment[ 0 ].replace(
				new RegExp( ' \\*/|/\\* ', 'gi' ),
				''
			);
		},

		formatDate: function ( rawDate ) {
			var MONTHS = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec' ],
				f = new Date( rawDate ),
				fDate = f.getUTCDate(),
				fMonth = f.getUTCMonth(),
				fYear = f.getUTCFullYear(),
				fHours = ( '0' + f.getUTCHours() ).slice( -2 ),
				fMinutes = ( '0' + f.getUTCMinutes() ).slice( -2 );

			return fHours + ':' + fMinutes + ', ' + fDate + ' ' + MONTHS[ fMonth ] + ' ' + fYear;
		},

		getFormattedDate: function () {
			return this.formatDate( this.timestamp );
		},

		getUser: function () {
			return this.user;
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.Revision = Revision;
}( mediaWiki, jQuery ) );
