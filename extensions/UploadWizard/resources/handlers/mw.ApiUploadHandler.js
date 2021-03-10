( function ( uw ) {
	var NS_FILE = mw.config.get( 'wgNamespaceIds' ).file;

	/**
	 * @param {mw.UploadWizardUpload} upload
	 * @param {mw.Api} api
	 */
	mw.ApiUploadHandler = function ( upload, api ) {
		this.upload = upload;
		this.api = api;

		this.ignoreWarnings = [
			// we ignore these warnings, because the title is not our final title.
			'page-exists',
			'exists',
			'exists-normalized',
			'was-deleted',
			'badfilename',
			'bad-prefix'
		];

		this.upload.on( 'remove-upload', this.abort.bind( this ) );
	};

	/**
	 * @method
	 * @abstract
	 */
	mw.ApiUploadHandler.prototype.abort = null;

	/**
	 * @method
	 * @abstract
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadHandler.prototype.submit = null;

	/**
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadHandler.prototype.start = function () {
		return this.submit().then(
			this.setTransported.bind( this ),
			this.setTransportError.bind( this )
		);
	};

	/**
	 * Process a successful upload.
	 *
	 * @param {Object} result
	 */
	mw.ApiUploadHandler.prototype.setTransported = function ( result ) {
		var code;
		if ( result.upload && result.upload.warnings ) {
			for ( code in result.upload.warnings ) {
				if ( !this.isIgnoredWarning( code ) ) {
					this.setTransportWarning( code, result );
					return;
				}
			}
		}

		if ( !result.upload || result.upload.result !== 'Success' ) {
			this.setError( 'unknown', mw.message( 'unknown-error' ).parse() );
			return;
		}

		if ( !result.upload.imageinfo ) {
			this.setError( 'noimageinfo', mw.message( 'api-error-noimageinfo' ).parse() );
			return;
		}

		this.upload.setSuccess( result );
	};

	/**
	 * Process an upload with a warning.
	 *
	 * @param {string} code The API warning code
	 * @param {Object} result The API result in parsed JSON form
	 */
	mw.ApiUploadHandler.prototype.setTransportWarning = function ( code, result ) {
		var param, duplicates, links;

		uw.eventFlowLogger.logApiError( 'file', result );

		switch ( code ) {
			case 'duplicate':
				duplicates = result.upload.warnings.duplicate;
				if ( result.upload.warnings.exists && result.upload.warnings.nochange ) {
					// An existing same (nochange) file will not show up as
					// duplicate, but it should also be present in order to
					// figure out how to process the attempted upload)
					duplicates.push( result.upload.warnings.exists );
				}
				this.processDuplicateError( code, result, result.upload.warnings.duplicate );
				return;
			case 'nochange':
				// This is like 'duplicate', but also the filename is the same, which doesn't matter
				if ( result.upload.warnings.exists ) {
					links = this.getFileLinks( [ result.upload.warnings.exists ] );
					this.setDuplicateError( code, result, links, {}, 1 - links.length );
				}
				return;
			case 'duplicate-archive':
				this.setDuplicateArchiveError( code, result, result.upload.warnings[ 'duplicate-archive' ] );
				return;
			default:
				param = code;
				if ( typeof result.upload.warnings[ code ] === 'string' ) {
					// tack the original error code onto the warning message
					param += mw.message( 'colon-separator' ).text() + result.upload.warnings[ code ];
				}

				// we have an unknown warning, so let's say what we know
				this.setError( code, mw.message( 'api-error-unknown-warning', param ).parse() );
				return;
		}
	};

	/**
	 * Process an erroneous upload.
	 *
	 * @param {string} code The API error code
	 * @param {Object} result The API result in parsed JSON form
	 */
	mw.ApiUploadHandler.prototype.setTransportError = function ( code, result ) {
		var $extra;

		uw.eventFlowLogger.logApiError( 'file', result );

		if ( code === 'badtoken' ) {
			this.api.badToken( 'csrf' );

			// Try again once
			if ( this.ignoreWarning( code ) ) {
				this.start();
				return;
			}
		}

		if ( code === 'abusefilter-warning' ) {
			$extra = new OO.ui.ButtonWidget( {
				label: mw.message( 'mwe-upwiz-override' ).text(),
				title: mw.message( 'mwe-upwiz-override-upload' ).text(),
				flags: 'progressive',
				framed: false
			} ).on( 'click', function () {
				// No need to ignore the error, AbuseFilter will only return it once
				this.start();
			}.bind( this ) ).$element;
		}

		this.setError( code, result.errors[ 0 ].html, $extra );
	};

	/**
	 * Figure out the source of duplicates (local or foreign) and distribute
	 * them to the correct function to display the accurate error messages.
	 *
	 * @param {string} code
	 * @param {Object} result
	 * @param {string[] }duplicates
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadHandler.prototype.processDuplicateError = function ( code, result, duplicates ) {
		var files = this.getFileLinks( duplicates ),
			unknownAmount = duplicates.length - Object.keys( files ).length;

		return this.getDuplicateSource( Object.keys( files ) ).then(
			function ( data ) {
				this.setDuplicateError( code, result, data.local, data.foreign, unknownAmount );
			}.bind( this ),
			function () {
				// if anything goes wrong trying to figure out the source of
				// duplicates, just move on with local duplicate handling
				this.setDuplicateError( code, result, files, {}, unknownAmount );
			}.bind( this )
		);
	};

	/**
	 * @param {string[]} duplicates Array of duplicate filenames
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadHandler.prototype.getDuplicateSource = function ( duplicates ) {
		return this.getImageInfo( duplicates, 'url' ).then( function ( result ) {
			var local = [],
				foreign = [],
				normalized = [];

			if ( !result.query || !result.query.pages ) {
				return $.Deferred().reject();
			}

			// map of normalized titles, so we can find original title
			if ( result.query.normalized ) {
				result.query.normalized.forEach( function ( data ) {
					normalized[ data.to ] = data.from;
				} );
			}

			Object.keys( result.query.pages ).forEach( function ( pageId ) {
				var page = result.query.pages[ pageId ],
					title = page.title in normalized ? normalized[ page.title ] : page.title;
				if ( page.imagerepository === 'local' ) {
					local[ title ] = page.imageinfo[ 0 ].descriptionurl;
				} else if ( page.imagerepository !== '' ) {
					foreign[ title ] = page.imageinfo[ 0 ].descriptionurl;
				}
			} );

			return $.Deferred().resolve( { local: local, foreign: foreign } );
		} );
	};

	/**
	 * Helper function to generate existing duplicate errors in a possibly collapsible list.
	 *
	 * @param {string} code Warning code, should have matching strings in .i18n.php
	 * @param {Object} result The API result in parsed JSON form
	 * @param {Object} localDuplicates Array of [duplicate filenames => local url]
	 * @param {Object} foreignDuplicates Array of [duplicate filenames => foreign url]
	 * @param {number} unknownAmount Amount of unknown filenames (e.g. revdeleted)
	 */
	mw.ApiUploadHandler.prototype.setDuplicateError = function ( code, result, localDuplicates, foreignDuplicates, unknownAmount ) {
		var allDuplicates = $.extend( {}, localDuplicates, foreignDuplicates ),
			$extra = $( '<div>' ),
			$ul = $( '<ul>' ).appendTo( $extra ),
			$a,
			override,
			i;

		unknownAmount = unknownAmount || 0;

		Object.keys( allDuplicates ).forEach( function ( filename ) {
			var href = allDuplicates[ filename ];
			$a = $( '<a>' ).text( filename );
			$a.attr( { href: href, target: '_blank' } );
			$ul.append( $( '<li>' ).append( $a ) );
		} );

		for ( i = 0; i < unknownAmount; i++ ) {
			$a = $( '<em>' ).text( mw.msg( 'mwe-upwiz-deleted-duplicate-unknown-filename' ) );
			$ul.append( $( '<li>' ).append( $a ) );
		}

		if ( allDuplicates.length > 1 ) {
			$ul.makeCollapsible( { collapsed: true } );
		}

		// allow upload to continue if it's only a duplicate of files in a
		// foreign repo, not when it's a local dupe
		if ( Object.keys( localDuplicates ).length === 0 ) {
			override = new OO.ui.ButtonWidget( {
				label: mw.message( 'mwe-upwiz-override' ).text(),
				title: mw.message( 'mwe-upwiz-override-upload' ).text(),
				flags: 'progressive',
				framed: false
			} ).on( 'click', function () {
				// mark this warning as ignored & process the API result again
				this.ignoreWarning( 'duplicate' );
				this.setTransported( result );
			}.bind( this ) );

			override.$element.appendTo( $extra );
		}

		this.setError( code, mw.message( 'file-exists-duplicate', allDuplicates.length ).parse(), $extra );
	};

	/**
	 * Helper function to generate deleted duplicate errors in a possibly collapsible list.
	 *
	 * @param {string} code Warning code, should have matching strings in .i18n.php
	 * @param {Object} result The API result in parsed JSON form
	 * @param {string} duplicate Duplicate filename
	 */
	mw.ApiUploadHandler.prototype.setDuplicateArchiveError = function ( code, result, duplicate ) {
		var filename = mw.Title.makeTitle( NS_FILE, duplicate ).getPrefixedText(),
			uploadDuplicate = new OO.ui.ButtonWidget( {
				label: mw.message( 'mwe-upwiz-override' ).text(),
				title: mw.message( 'mwe-upwiz-override-upload' ).text(),
				flags: 'progressive',
				framed: false
			} ).on( 'click', function () {
				// mark this warning as ignored & process the API result again
				this.ignoreWarning( 'duplicate-archive' );
				this.setTransported( result );
			}.bind( this ) );

		this.setError( code, mw.message( 'file-deleted-duplicate', filename ).parse(), uploadDuplicate.$element );
	};

	/**
	 * @param {string|string[]} titles File title or array of titles
	 * @param {string|string[]} prop Image props
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadHandler.prototype.getImageInfo = function ( titles, prop ) {
		return this.api.get( {
			action: 'query',
			titles: titles,
			prop: 'imageinfo',
			iiprop: prop
		} );
	};

	/**
	 * Convert an array of non-prefixed filenames into a [filename => url] map.
	 *
	 * @param {string[]} filenames Array of non-prefixed filenames
	 * @return {Object} Map of [prefixed filename => url]
	 */
	mw.ApiUploadHandler.prototype.getFileLinks = function ( filenames ) {
		var files = [];

		filenames.forEach( function ( filename ) {
			var title;
			try {
				title = mw.Title.makeTitle( NS_FILE, filename );
				files[ title.getPrefixedText() ] = title.getUrl( {} );
			} catch ( e ) {
				// invalid filename (e.g. file was revdeleted)
			}
		} );

		return files;
	};

	/**
	 * @param {string} code Error code from API
	 * @param {string} html Error message
	 * @param {jQuery} [$extra]
	 */
	mw.ApiUploadHandler.prototype.setError = function ( code, html, $extra ) {
		this.upload.setError( code, html, $extra );
	};

	/**
	 * Marks a warning to be ignored.
	 *
	 * @param {string} code
	 * @return {boolean}
	 */
	mw.ApiUploadHandler.prototype.ignoreWarning = function ( code ) {
		if ( this.isIgnoredWarning( code ) ) {
			return false;
		}

		// mark the warning as being ignored, then restart the request
		this.ignoreWarnings.push( code );
		return true;
	};

	/**
	 * Returns whether or not the warning is being ignored.
	 *
	 * @param {string} code
	 * @return {boolean}
	 */
	mw.ApiUploadHandler.prototype.isIgnoredWarning = function ( code ) {
		return this.ignoreWarnings.indexOf( code ) > -1;
	};
}( mw.uploadWizard ) );
