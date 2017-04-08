// Only turning these jscs options off for ''this file''
/* jscs:disable disallowDanglingUnderscores, requireCamelCaseOrUpperCaseIdentifiers */
( function ( mw, $, OO ) {
	mw.FlickrChecker = function ( wizard, upload ) {
		this.wizard = wizard;
		this.upload = upload;
		this.imageUploads = [];
		this.apiUrl = mw.UploadWizard.config.flickrApiUrl;
		this.apiKey = mw.UploadWizard.config.flickrApiKey;

		this.selectButton = new OO.ui.ButtonWidget( {
			id: 'mwe-upwiz-select-flickr',
			label: mw.message( 'mwe-upwiz-add-file-0-free' ).text(),
			flags: [ 'constructive', 'primary' ]
		} );

		$( '#mwe-upwiz-flickr-select-list-container' ).append( this.selectButton.$element );
	};

	/**
	 * Static list of all Flickr upload filenames.
	 * Used to avoid name conflicts. Filenames are not removed when an upload is cancelled, so this can
	 * contain fakes. Since we only use the list to choose an ugly but more unique file format on conflict,
	 * and never refuse an upload based on it, that is not really a problem.
	 * @type {Object}
	 */
	mw.FlickrChecker.fileNames = {};

	/**
	 * Cache for Flickr blacklist lookups.
	 * Resolves to a hash whose keys are the blacklisted Flickr NSIDs.
	 * Use `FlickrChecker.getBlacklist()` instead of accessing this directly.
	 * @type {jQuery.Promise}
	 */
	mw.FlickrChecker.blacklist = null;

	/**
	 * Cache for Flickr license lookups.
	 * @type {jQuery.Promise}
	 */
	mw.FlickrChecker.licensePromise = null;

	/**
	 * Flickr licenses.
	 */
	mw.FlickrChecker.licenseList = [];

	// Map each Flickr license name to the equivalent templates.
	// These are the current Flickr license names as of April 26, 2011.
	// Live list at http://api.flickr.com/services/rest/?&method=flickr.photos.licenses.getInfo&api_key=...
	mw.FlickrChecker.licenseMaps = {
		'All Rights Reserved': 'invalid',
		'Attribution License': '{{FlickrVerifiedByUploadWizard|cc-by-2.0}}{{cc-by-2.0}}',
		'Attribution-NoDerivs License': 'invalid',
		'Attribution-NonCommercial-NoDerivs License': 'invalid',
		'Attribution-NonCommercial License': '{{FlickrVerifiedByUploadWizard|cc-by-nc-2.0}}{{cc-by-nc-2.0}}',
		'Attribution-NonCommercial-ShareAlike License': '{{FlickrVerifiedByUploadWizard|cc-by-nc-sa-2.0}}{{cc-by-nc-sa-2.0}}',
		'Attribution-ShareAlike License': '{{FlickrVerifiedByUploadWizard|cc-by-sa-2.0}}{{cc-by-sa-2.0}}',
		'No known copyright restrictions': '{{FlickrVerifiedByUploadWizard|Flickr-no known copyright restrictions}}{{Flickr-no known copyright restrictions}}',
		'United States Government Work': '{{FlickrVerifiedByUploadWizard|PD-USGov}}{{PD-USGov}}',
		'Public Domain Dedication (CC0)': '{{FlickrVerifiedByUploadWizard|cc-zero}}{{cc-zero}}',
		'Public Domain Mark': '{{FlickrVerifiedByUploadWizard|Public Domain Mark}}' // T105629
	};

	mw.FlickrChecker.prototype = {
		/**
		 * If a photo is from Flickr, retrieve its license. If the license is valid, display the license
		 * to the user, hide the normal license selection interface, and set it as the deed for the upload.
		 * If the license is not valid, alert the user with an error message. If no recognized license is
		 * retrieved, do nothing. Note that the license look-up system is fragile on purpose. If Flickr
		 * changes the name associated with a license ID, it's better for the lookup to fail than to use
		 * an incorrect license.
		 *
		 * @param {string} flickrInputUrl The source URL to check
		 */
		checkFlickr: function ( flickrInputUrl ) {
			var photoIdMatches, albumIdMatches, userCollectionMatches, userPhotostreamMatches, groupPoolMatches, userGalleryMatches, userFavoritesMatches;

			photoIdMatches = flickrInputUrl.match( /flickr\.com\/(?:x\/t\/[^\/]+\/)?photos\/[^\/]+\/([0-9]+)/ );
			albumIdMatches = flickrInputUrl.match( /flickr\.com\/photos\/[^\/]+\/(sets|albums)\/([0-9]+)/ );
			userCollectionMatches = flickrInputUrl.match( /flickr\.com\/(?:x\/t\/[^\/]+\/)?photos\/[^\/]+\/collections\/?([0-9]+)?/ );
			userPhotostreamMatches = flickrInputUrl.match( /flickr\.com\/(?:x\/t\/[^\/]+\/)?photos\/([^\/]+)/ );
			groupPoolMatches = flickrInputUrl.match( /flickr\.com\/groups\/[^\/]+(?:\/pool\/([^\/]+))?/ );
			userGalleryMatches = flickrInputUrl.match( /flickr\.com\/(?:x\/t\/[^\/]+\/)?photos\/[^\/]+\/galleries\/([0-9]+)/ );
			userFavoritesMatches = flickrInputUrl.match( /flickr\.com\/(?:x\/t\/[^\/]+\/)?photos\/([^\/]+)\/favorites/ );

			if ( photoIdMatches === null ) {
				// try static urls
				photoIdMatches = flickrInputUrl.match( /static\.?flickr\.com\/[^\/]+\/([0-9]+)_/ );
			}
			if ( albumIdMatches || photoIdMatches || userCollectionMatches || userPhotostreamMatches ||
				groupPoolMatches || userGalleryMatches || userFavoritesMatches ) {
				$( '#mwe-upwiz-upload-add-flickr-container' ).hide();
				this.imageUploads = [];
				if ( albumIdMatches && albumIdMatches[ 2 ] > 0 ) {
					this.getPhotoset( albumIdMatches, flickrInputUrl );
				} else if ( photoIdMatches && photoIdMatches[ 1 ] > 0 ) {
					this.getPhoto( photoIdMatches, flickrInputUrl );
				} else if ( userCollectionMatches ) {
					this.getCollection( userCollectionMatches, flickrInputUrl );
				} else if ( userFavoritesMatches && userFavoritesMatches[ 1 ] ) {
					this.getPhotostream( 'favorites', userPhotostreamMatches, flickrInputUrl );
				} else if ( userGalleryMatches && userGalleryMatches[ 1 ] ) {
					this.getGallery( flickrInputUrl );
				} else if ( userPhotostreamMatches && userPhotostreamMatches[ 1 ] ) {
					this.getPhotostream( 'stream', flickrInputUrl );
				} else if ( groupPoolMatches ) {
					this.getGroupPool( groupPoolMatches, flickrInputUrl );
				}
			} else {
				// XXX show user the message that the URL entered was not valid
				mw.errorDialog( mw.message( 'mwe-upwiz-url-invalid', 'Flickr' ).escaped() );
				this.wizard.flickrInterfaceReset();
			}
		},

		/**
		 * Returns a suggested filename for the image.
		 * Usually the filename is just the Flickr title plus an extension, but in case of name conflicts
		 * or empty title a unique filename is generated.
		 *
		 * @param {string} title image title on Flickr
		 * @param {number} id image id on Flickr
		 * @param {string} ownername owner name on Flickr
		 * @return {string}
		 */
		getFilenameFromItem: function ( title, id, ownername ) {
			var fileName;

			if ( title === '' ) {
				fileName = ownername + ' - ' + id + '.jpg';
			} else if ( mw.FlickrChecker.fileNames[ title + '.jpg' ] ) {
				fileName = title + ' - ' + id + '.jpg';
			} else {
				fileName = title + '.jpg';
			}

			return fileName;
		},

		/**
		 * Reserves a filename; used by `mw.FlickrChecker.getFileNameFromItem()` which tries to
		 * avoid returning a filename which is already reserved.
		 * This works even when the filename was reserved in a different FlickrChecker instance.
		 *
		 * @param {string} fileName
		 */
		reserveFileName: function ( fileName ) {
			mw.FlickrChecker.fileNames[ fileName ] = true;
		},

		/**
		 * @param {Object} params
		 * @return {jQuery.Promise} a promise with the response data
		 */
		flickrRequest: function ( params ) {
			params = $.extend( {
				api_key: this.apiKey,
				format: 'json',
				nojsoncallback: 1
			}, params );
			return $.getJSON( this.apiUrl, params );
		},

		/*
		 * Retrieves a list of photos in photostream and displays it.
		 * @see {@link getPhotos}
		 * @param {string} mode may be: 'favorites' - user's favorites are retrieved,
		 * or 'stream' - user's photostream is retrieved
		 * @param {string} url URL to get the user from.
		 * @return {jQuery.Promise}
		 */
		getPhotostream: function ( mode, url ) {
			var checker = this;
			return this.flickrRequest( {
				method: 'flickr.urls.lookupUser',
				url: url
			} ).then( function ( data ) {
				var method;
				if ( mode === 'stream' ) {
					method = 'flickr.people.getPublicPhotos';
				} else if ( mode === 'favorites' ) {
					method = 'flickr.favorites.getPublicList';
				}
				return checker.getPhotos( 'photos', {
					method: method,
					user_id: data.user.id
				} );
			} );
		},

		/**
		 * Retrieves a list of photos in group pool and displays it.
		 *
		 * @param {Object} groupPoolMatches Groups in the input URL
		 * @param {string} url The URL from which to get the group.
		 * @see {@link getPhotos}
		 * @return {jQuery.Promise}
		 */
		getGroupPool: function ( groupPoolMatches, url ) {
			var checker = this;

			return this.flickrRequest( {
				method: 'flickr.urls.lookupGroup',
				url: url
			} ).then( function ( data ) {
				var gid = data.group.id;

				if ( groupPoolMatches[ 1 ] ) { // URL contains a user ID
					return checker.flickrRequest( {
						method: 'flickr.urls.lookupUser',
						url: 'http://www.flickr.com/photos/' + groupPoolMatches[ 1 ]
					} ).then( function ( data ) {
						return checker.getPhotos( 'photos', {
							method: 'flickr.groups.pools.getPhotos',
							group_id: gid,
							user_id: data.user.id
						} );
					} );
				}

				return checker.getPhotos( 'photos', {
					method: 'flickr.groups.pools.getPhotos',
					group_id: gid
				} );
			} );
		},

		/**
		 * Constructs an unordered list of sets in the collection.
		 *
		 * @param {boolean} appendId True if you want to append
		 * id="mwe-upwiz-files-collection-chooser"; false otherwise
		 * @param {Object} data The retrieved data
		 * @see {@link getCollection}
		 */
		buildCollectionLinks: function ( appendId, data ) {
			var elem = $( '<ul>' ),
				that = this,
				li, ul;
			if ( appendId ) {
				elem.attr( 'id', 'mwe-upwiz-files-collection-chooser' );
			}
			$.each( data.collection, function ( index, value ) {
				li = $( '<li>' );
				li.append( value.title );
				if ( value.collection !== undefined ) {
					li.append( that.buildCollectionLinks( false, value ) );
				}
				if ( value.set !== undefined ) {
					ul = $( '<ul>' );
					$.each( value.set, function ( index2, value2 ) {
						var link = $( '<a>', { href: '#', role: 'button', 'data-id': value2.id } );
						link.append( value2.title );
						link.click( function () {
							$( '#mwe-upwiz-files-collection-chooser' ).remove();
							that.getPhotos( 'photoset', {
								method: 'flickr.photosets.getPhotos',
								photoset_id: link.data( 'id' )
							} );
						} );
						ul.append( $( '<li>' ).append( link ) );
					} );
					li.append( ul );
				}
				elem.append( li );
			} );
			return elem;
		},

		/**
		 * Retrieves a list of sets in a collection and displays it.
		 *
		 * @param {Object} userCollectionMatches Result of this.url.match
		 * @param {string} url URL with which to look up the user.
		 * @return {jQuery.Promise}
		 */
		getCollection: function ( userCollectionMatches, url ) {
			var checker = this;

			return checker.flickrRequest( {
				method: 'flickr.urls.lookupUser',
				url: url
			} ).then( function ( data ) {
				var req = {
					method: 'flickr.collections.getTree',
					extras: 'license, url_sq, owner_name, original_format, date_taken, geo',
					user_id: data.user.id
				};

				if ( userCollectionMatches[ 1 ] ) {
					req.collection_id = userCollectionMatches[ 1 ];
				}

				return checker.flickrRequest( req ).then( function ( data ) {
					$( '#mwe-upwiz-files' ).append( checker.buildCollectionLinks( true, data.collections ) );
				} );
			} );
		},

		/**
		 * Retrieves a list of photos in gallery and displays it.
		 *
		 * @see {@link getPhotos}
		 * @param {string} url URL with which to look up the gallery information.
		 * @return {jQuery.Promise}
		 */
		getGallery: function ( url ) {
			var checker = this;

			return this.flickrRequest( {
				method: 'flickr.urls.lookupGallery',
				url: url
			} ).then( function ( data ) {
				return checker.getPhotos( 'photos', {
					method: 'flickr.galleries.getPhotos',
					gallery_id: data.gallery.id
				} );
			} );
		},

		/**
		 * Retrieves a list of photos in photoset and displays it.
		 *
		 * @see {@link getPhotos}
		 * @param {Object} albumIdMatches Result of this.url.match
		 * @return {jQuery.Promise}
		 */
		getPhotoset: function ( albumIdMatches ) {
			return this.getPhotos( 'photoset', {
				method: 'flickr.photosets.getPhotos',
				photoset_id: albumIdMatches[ 2 ]
			} );
		},

		/**
		 * Retrieves a list of photos and displays it.
		 *
		 * @param {string} mode may be: 'photoset' - for use with photosets,
		 *	 or 'photos' - for use with everything else (the parameter is used
		 *	 to determine how the properties in retrieved JSON are named)
		 * @param {Object} options options to pass to the API call; especially API method
		 *	 and some "***_id"s (photoset_id, etc.)
		 * @return {jQuery.Promise}
		 */
		getPhotos: function ( mode, options ) {
			var checker = this,
				flickrPromise,
				req = {};

			this.selectButton.setLabel( mw.message( 'mwe-upwiz-select-flickr' ).text() );
			this.selectButton.setDisabled( true );

			$.extend( req, options, {
				extras: 'license, url_sq, owner_name, original_format, date_taken, geo, path_alias',
				per_page: '500'
			} );

			flickrPromise = this.flickrRequest( req ).then( function ( data ) {
				var photoset;
				if ( mode === 'photoset' ) {
					photoset = data.photoset;
				} else if ( mode === 'photos' ) {
					photoset = data.photos;
				}
				if ( !photoset ) {
					$.Deferred().reject( mw.message( 'mwe-upwiz-url-invalid', 'Flickr' ).escaped() );
				}
				return photoset;
			} );

			// would be better to use isBlacklisted(), but didn't find a nice way of combining it with $.each
			return $.when( flickrPromise, this.getBlacklist() ).then( function ( photoset, blacklist ) {
				var fileName, sourceURL,
					imagesHTML = '',
					x = 0;

				$.each( photoset.photo, function ( i, item ) {
					var flickrUpload, license, licenseValue, ownerId;

					license = checker.checkLicense( item.license, i );
					licenseValue = license.licenseValue;
					if ( licenseValue === 'invalid' ) {
						return;
					}

					if ( mode === 'photoset' ) {
						ownerId = photoset.owner;
						sourceURL = 'http://www.flickr.com/photos/' + photoset.owner + '/' + item.id + '/';
					} else if ( mode === 'photos' ) {
						ownerId = item.owner;
						sourceURL = 'http://www.flickr.com/photos/' + item.owner + '/' + item.id + '/';
					}

					if ( ownerId in blacklist || item.pathalias in blacklist ) {
						return;
					}

					// Limit to maximum of 500 valid images
					// (Flickr's API returns a maximum of 500 images anyway.)
					if ( x++ >= 500 ) {
						return false;
					}

					fileName = checker.getFilenameFromItem( item.title, item.id, item.ownername );

					flickrUpload = {
						name: fileName,
						url: '',
						type: 'JPEG',
						fromURL: true,
						licenseValue: licenseValue,
						licenseMessage: license.licenseMessage,
						license: license.licenseName !== 'Public Domain Mark',
						photoId: item.id,
						location: {
							latitude: item.latitude,
							longitude: item.longitude
						},
						author: item.ownername,
						date: item.datetaken,
						originalFormat: item.originalformat,
						sourceURL: sourceURL,
						index: i
					};
					// Adding all the Photoset files which have a valid license with the required info to an array so that they can be referenced later
					checker.imageUploads[ i ] = flickrUpload;
					checker.reserveFileName( fileName );

					// setting up the thumbnail previews in the Selection list
					if ( item.url_sq ) {
						imagesHTML += '<li id="upload-' + i + '" class="ui-state-default"><img class="lazy-thumbnail" data-original="' + item.url_sq + '"></li>';
					}
				} );
				$( '#mwe-upwiz-flickr-select-list' ).append( imagesHTML );
				// Lazy-load images
				$( 'img.lazy-thumbnail' ).lazyload( {
					// jQuery considers all images without 'src' to not be ':visible'
					skip_invisible: false
				} );
				// Trigger initial update (HACK)
				setTimeout( function () {
					$( window ).triggerHandler( 'resize' );
				} );
				// Calling jquery ui selectable
				$( '#mwe-upwiz-flickr-select-list' ).selectable( {
					filter: 'li',
					stop: function () {
						// If at least one item is selected, activate the upload button
						checker.selectButton.setDisabled( $( '.ui-selected' ).length === 0 );
					},
					selecting: function ( event, ui ) {
						// Limit the number of selectable images
						if ( $( '.ui-selected, .ui-selecting' ).length > mw.UploadWizard.config.maxUploads ) {
							$( ui.selecting ).removeClass( 'ui-selecting' );
						}
					}
				} );
				// Set up action for 'Upload selected images' button
				checker.selectButton.on( 'click', function () {
					$( '#mwe-upwiz-flickr-select-list-container' ).hide();
					$( '#mwe-upwiz-upload-ctrls' ).show();
					$( '.ui-selected' ).each( function ( index, image ) {
						image = $( this ).attr( 'id' );
						image = image.split( '-' )[ 1 ];
						checker.setUploadDescription( checker.imageUploads[ image ] );
						checker.setImageURL( image );
					} );
					checker.wizard.flickrInterfaceDestroy();
				} );

				if ( checker.imageUploads.length === 0 ) {
					return $.Deferred().reject( mw.message( 'mwe-upwiz-license-photoset-invalid' ).escaped() );
				} else {
					$( '#mwe-upwiz-flickr-select-list-container' ).show();
				}
			} ).fail( function ( message ) {
				mw.errorDialog( message );
				checker.wizard.flickrInterfaceReset();
			} );
		},

		/**
		 * Get a single photo from Flickr.
		 *
		 * @param {Object} photoIdMatches Result of matching input URL against a regex
		 *   for photo IDs.
		 * @return {jQuery.Promise}
		 */
		getPhoto: function ( photoIdMatches ) {
			var fileName, photoAuthor, sourceURL,
				checker = this,
				photoId = photoIdMatches[ 1 ];

			return this.flickrRequest( {
				method: 'flickr.photos.getInfo',
				photo_id: photoId
			} ).then( function ( data ) {
				if ( !data.photo ) {
					return $.Deferred().reject( mw.message( 'mwe-upwiz-url-invalid', 'Flickr' ).escaped() );
				}
				return data.photo;
			} ).then( function ( photo ) {
				var isBlacklistedPromise = checker.isBlacklisted( photo.owner.nsid, photo.owner.path_alias );
				return isBlacklistedPromise.then( function ( isBlacklisted ) {
					if ( isBlacklisted ) {
						return $.Deferred().reject( mw.message( 'mwe-upwiz-user-blacklisted', 'Flickr' ).escaped() );
					} else {
						return photo;
					}
				} );
			} ).then( function ( photo ) {
				var license, flickrUpload;

				license = checker.checkLicense( photo.license );
				if ( license.licenseValue === 'invalid' ) {
					return $.Deferred().reject( license.licenseMessage );
				}

				fileName = checker.getFilenameFromItem( photo.title._content, photo.id,
					photo.owner.username );

				// if owner doesn't have a real name, use username
				if ( photo.owner.realname !== '' ) {
					photoAuthor = photo.owner.realname;
				} else {
					photoAuthor = photo.owner.username;
				}
				// get the URL of the photo page
				$.each( photo.urls.url, function ( index, url ) {
					if ( url.type === 'photopage' ) {
						sourceURL = url._content;
						// break each loop
						return false;
					}
				} );
				flickrUpload = {
					name: fileName,
					url: '',
					type: 'JPEG',
					fromURL: true,
					licenseValue: license.licenseValue,
					licenseMessage: license.licenseMessage,
					license: license.licenseName !== 'Public Domain Mark',
					author: photoAuthor,
					originalFormat: photo.originalformat,
					date: photo.dates.taken,
					location: photo.location,
					photoId: photo.id,
					sourceURL: sourceURL
				};
				checker.setUploadDescription( flickrUpload, photo.description._content );
				checker.imageUploads.push( flickrUpload );
				checker.setImageURL( 0, checker );
				checker.reserveFileName( fileName );
			} ).fail( function ( message ) {
				mw.errorDialog( message );
				checker.wizard.flickrInterfaceReset();
			} );
		},

		/**
		 * Checks a user against the blacklist. Both the NSID and the path_alias (if it exists) MUST be
		 * supplied, as the blacklist will probably only contain one of them. (Users don't have a
		 * path_alias in the beginning, and must set it manually; if it does not exist, it can be left
		 * undefined, or an empty string can be supplied (which is what the Flickr API usually returns
		 * as the path_alias for such users).
		 *
		 * @param {string} nsid Flickr NSID of the author
		 * @param {string} [path_alias] Flickr username of the author (the unchangeable one, in the URL)
		 * @return {jQuery.Promise} a promise which resolves to a boolean - true if the user is blacklisted
		 */
		isBlacklisted: function ( nsid, path_alias ) {
			path_alias = String( path_alias );
			return this.getBlacklist().then( function ( blacklist ) {
				// the blacklist should never contain the empty string, but better safe then sorry
				return ( nsid in blacklist || path_alias && path_alias in blacklist );
			} );
		},

		/**
		 * Returns a promise for the Flickr user blacklist.
		 * The promise resolves to a hash with the blacklisted NSIDs/path_alias-es as its keys.
		 * (path_alias is the username that appears in the URL.)
		 * The blacklist will usually contain the path_alias or the NSID of the user, but not both;
		 * it is the caller's responsibility to check against both of them.
		 *
		 * @return {jQuery.Promise}
		 */
		getBlacklist: function () {
			var api = new mw.Api();
			if ( !mw.FlickrChecker.blacklist ) {
				mw.FlickrChecker.blacklist = api.get( {
					action: 'flickrblacklist',
					list: 1,
					format: 'json'
				} ).then( function ( data ) {
					var blacklist = {};
					if ( data.flickrblacklist && data.flickrblacklist.list ) {
						$.each( data.flickrblacklist.list, function ( i, username ) {
							blacklist[ username ] = true;
						} );
					}
					return blacklist;
				} );
			}
			return mw.FlickrChecker.blacklist;
		},

		/**
		 * Retrieve the list of all current Flickr licenses and store it in an array (`mw.FlickrChecker.licenseList`)
		 *
		 * @return {jQuery.Promise}
		 */
		getLicenses: function () {
			if ( mw.FlickrChecker.licensePromise ) {
				return mw.FlickrChecker.licensePromise;
			}

			// Workaround for http://bugs.jquery.com/ticket/8283
			jQuery.support.cors = true;
			mw.FlickrChecker.licensePromise = this.flickrRequest( {
				method: 'flickr.photos.licenses.getInfo'
			} ).then( function ( data ) {
				if ( typeof data.licenses !== 'undefined' ) {
					$.each( data.licenses.license, function ( index, value ) {
						mw.FlickrChecker.licenseList[ value.id ] = value.name;
					} );
				}
			} );

			return mw.FlickrChecker.licensePromise;
		},

		setUploadDescription: function ( upload, description ) {
			if ( description !== undefined ) {
				// If a Flickr description has a | character in it, it will
				// mess up the MediaWiki description. Escape them.
				upload.description = description.replace( /\|/g, '&#124;' );
			} else {
				this.setImageDescription( upload );
			}
		},

		setImageDescription: function ( upload ) {
			var checker = this,
				photoId = upload.photoId;

			this.flickrRequest( {
				method: 'flickr.photos.getInfo',
				photo_id: photoId
			} ).done( function ( data ) {
				checker.setUploadDescription( upload, data.photo.description._content );
			} );
		},

		/**
		 * Retrieve the URL of the largest version available on Flickr and set that
		 * as the upload URL.
		 *
		 * @param {number} index Index of the image for which we need to set the URL
		 */
		setImageURL: function ( index ) {
			var largestSize,
				checker = this,
				upload = this.imageUploads[ index ],
				photoId = upload.photoId;

			this.flickrRequest( {
				method: 'flickr.photos.getSizes',
				photo_id: photoId
			} ).done( function ( data ) {
				var nameParts, uploadObj;

				if (
					typeof data.sizes !== 'undefined' &&
					typeof data.sizes.size !== 'undefined' &&
					data.sizes.size.length > 0
				) {
					// Flickr always returns the largest version as the final size.
					// TODO: Make this less fragile by actually comparing sizes.
					largestSize = data.sizes.size.pop();
					// Flickr provides the original format for images coming from pro users, hence we need to change the default JPEG to this format
					if ( largestSize.label === 'Original' ) {
						upload.type = upload.originalFormat;

						nameParts = upload.name.split( '.' );
						if ( nameParts.length > 1 ) {
							nameParts.pop();
						}
						upload.name = nameParts.join( '.' ) + '.' + upload.originalFormat;
					}
					upload.url = largestSize.source;
					// Need to call the addUpload here, otherwise some code would have to be written to detect the completion of the API call.
					uploadObj = checker.wizard.addUpload( upload );
					$( '#mwe-upwiz-filelist' ).append( uploadObj.ui.div );
					uploadObj.ui.showThumbnail();
				} else {
					mw.errorDialog( mw.message( 'mwe-upwiz-error-no-image-retrieved', 'Flickr' ).escaped() );
					checker.wizard.flickrInterfaceReset();
				}
			} );
		},

		checkLicense: function ( licenseId ) {
			var licenseMessage, license,
				// The returned data.photo.license is just an ID that we use to look up the license name
				licenseName = mw.FlickrChecker.licenseList[ licenseId ],
				// Use the license name to retrieve the template values
				licenseValue = mw.FlickrChecker.licenseMaps[ licenseName ];

			// Set the license message to show the user.
			if ( licenseValue === 'invalid' ) {
				licenseMessage = mw.message( 'mwe-upwiz-license-external-invalid', 'Flickr', licenseName ).escaped();
			} else {
				licenseMessage = mw.message( 'mwe-upwiz-license-external', 'Flickr', licenseName ).escaped();
			}

			license = {
				licenseName: licenseName,
				licenseMessage: licenseMessage,
				licenseValue: licenseValue
			};

			return license;
		}
	};

} )( mediaWiki, jQuery, OO );
