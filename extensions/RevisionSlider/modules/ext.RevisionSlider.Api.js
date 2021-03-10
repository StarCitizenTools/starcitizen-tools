( function ( mw, $ ) {
	/**
	 * @param {string} apiUrl
	 * @constructor
	 */
	var Api = function ( apiUrl ) {
		this.url = apiUrl;
	};

	$.extend( Api.prototype, {
		url: '',

		/**
		 * Fetches a batch of revision data, including a gender setting for users who edited the revision
		 *
		 * @param {string} pageName
		 * @param {Object} options Options
		 * @param {string} [options.dir='older'] Sort direction
		 * @param {number} [options.limit=500] Result limit
		 * @param {number} [options.startId] Start ID
		 * @param {number} [options.endId] End ID
		 * @param {Object} [options.knownUserGenders] Known user genders
		 * @return {jQuery.promise}
		 */
		fetchRevisionData: function ( pageName, options ) {
			var xhr, userXhr,
				deferred = $.Deferred(),
				self = this;

			options = options || {};

			xhr = this.fetchRevisions( pageName, options )
				.done( function ( data ) {
					var revs = data.query.pages[ 0 ].revisions,
						revContinue = data.continue,
						genderData = options.knownUserGenders || {},
						userNames;

					if ( !revs ) {
						return;
					}

					userNames = self.getUserNames( revs, genderData );

					userXhr = self.fetchUserGenderData( userNames )
						.done( function ( data ) {
							var users = typeof data !== 'undefined' ? data.query.users : [];

							if ( users.length > 0 ) {
								$.extend( genderData, self.getUserGenderData( users, genderData ) );
							}

							revs.forEach( function ( rev ) {
								if ( typeof rev.user !== 'undefined' && typeof genderData[ rev.user ] !== 'undefined' ) {
									rev.userGender = genderData[ rev.user ];
								}
							} );

							deferred.resolve( { revisions: revs, 'continue': revContinue } );
						} )
						.fail( deferred.reject );
				} )
				.fail( deferred.reject );

			return deferred.promise( {
				abort: function () {
					xhr.abort();
					if ( userXhr ) {
						userXhr.abort();
					}
				}
			} );
		},

		/**
		 * Fetches up to 500 revisions at a time
		 *
		 * @param {string} pageName
		 * @param {Object} [options] Options
		 * @param {string} [options.dir='older'] Sort direction
		 * @param {number} [options.limit=500] Result limit
		 * @param {number} [options.startId] Start ID
		 * @param {number} [options.endId] End ID
		 * @return {jQuery.jqXHR}
		 */
		fetchRevisions: function ( pageName, options ) {
			var dir, data;

			options = options || {};
			dir = options.dir !== undefined ? options.dir : 'older';
			data = {
				action: 'query',
				prop: 'revisions',
				format: 'json',
				rvprop: 'ids|timestamp|user|comment|parsedcomment|size|flags',
				titles: pageName,
				formatversion: 2,
				'continue': '',
				rvlimit: 500,
				rvdir: dir
			};

			if ( options.startId !== undefined ) {
				data.rvstartid = options.startId;
			}
			if ( options.endId !== undefined ) {
				data.rvendid = options.endId;
			}
			if ( options.limit !== undefined && options.limit <= 500 ) {
				data.rvlimit = options.limit;
			}

			return $.ajax( {
				url: this.url,
				data: data
			} );
		},

		/**
		 * Fetches gender data for up to 500 user names
		 *
		 * @param {string[]} users
		 * @return {jQuery.jqXHR}
		 */
		fetchUserGenderData: function ( users ) {
			if ( users.length === 0 ) {
				return $.Deferred().resolve();
			}
			return $.ajax( {
				url: this.url,
				data: {
					formatversion: 2,
					action: 'query',
					list: 'users',
					format: 'json',
					usprop: 'gender',
					ususers: users.join( '|' )
				}
			} );
		},

		/**
		 * @param {Object[]} revs
		 * @param {Object} knownUserGenders
		 * @return {string[]}
		 */
		getUserNames: function ( revs, knownUserGenders ) {
			var allUsers = revs.map( function ( rev ) {
				return typeof rev.user !== 'undefined' ? rev.user : '';
			} );
			return allUsers.filter( function ( value, index, array ) {
				return value !== '' && typeof knownUserGenders[ value ] === 'undefined' && array.indexOf( value ) === index;
			} );
		},

		/**
		 * @param {Object[]} data
		 * @return {Object}
		 */
		getUserGenderData: function ( data ) {
			var genderData = {},
				usersWithGender = data.filter( function ( item ) {
					return typeof item.gender !== 'undefined' && item.gender !== 'unknown';
				} );
			usersWithGender.forEach( function ( item ) {
				genderData[ item.name ] = item.gender;
			} );
			return genderData;
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.Api = Api;
}( mediaWiki, jQuery ) );
