( function ( mw, uw, $, OO ) {

	var NS_FILE = mw.config.get( 'wgNamespaceIds' ).file;

	/**
	 * Object that represents the Details (step 2) portion of the UploadWizard
	 * n.b. each upload gets its own details.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 * @param {jQuery} containerDiv The `div` to put the interface into
	 */
	mw.UploadWizardDetails = function ( upload, containerDiv ) {
		var
			descriptionRequired, uri,
			$moreDetailsWrapperDiv, $moreDetailsDiv,
			details = this;

		this.upload = upload;
		this.containerDiv = containerDiv;
		this.api = upload.api;

		this.mainFields = [];

		this.div = $( '<div class="mwe-upwiz-info-file ui-helper-clearfix filled"></div>' );

		this.thumbnailDiv = $( '<div class="mwe-upwiz-thumbnail mwe-upwiz-thumbnail-side"></div>' );

		this.dataDiv = $( '<div class="mwe-upwiz-data"></div>' );

		// descriptions
		// Description is not required if a campaign provides alternative wikitext fields,
		// which are assumed to function like a description
		descriptionRequired = !(
			mw.UploadWizard.config.fields &&
			mw.UploadWizard.config.fields.length &&
			mw.UploadWizard.config.fields[ 0 ].wikitext
		);
		this.descriptionsDetails = new uw.DescriptionsDetailsWidget( {
			required: descriptionRequired
		} );
		this.descriptionsDetailsField = new uw.FieldLayout( this.descriptionsDetails, {
			label: mw.message( 'mwe-upwiz-desc' ).text(),
			help: mw.message( 'mwe-upwiz-tooltip-description' ).text(),
			required: descriptionRequired
		} );
		this.mainFields.push( this.descriptionsDetailsField );

		this.titleDetails = new uw.TitleDetailsWidget( {
			extension: this.upload.title.getExtension()
		} );
		this.titleDetailsField = new uw.FieldLayout( this.titleDetails, {
			label: mw.message( 'mwe-upwiz-title' ).text(),
			help: mw.message( 'mwe-upwiz-tooltip-title' ).text(),
			required: true
		} );
		this.mainFields.push( this.titleDetailsField );

		this.deedChooserDetails = new uw.DeedChooserDetailsWidget();
		this.deedChooserDetailsField = new uw.FieldLayout( this.deedChooserDetails, {
			label: mw.message( 'mwe-upwiz-copyright-info' ).text(),
			required: true
		} );
		this.deedChooserDetailsField.toggle( false ); // See useCustomDeedChooser()
		this.mainFields.push( this.deedChooserDetailsField );

		this.categoriesDetails = new uw.CategoriesDetailsWidget();
		this.categoriesDetailsField = new uw.FieldLayout( this.categoriesDetails, {
			label: mw.message( 'mwe-upwiz-categories' ).text(),
			help: new OO.ui.HtmlSnippet(
				mw.message( 'mwe-upwiz-tooltip-categories', $( '<a>' ).attr( {
					target: '_blank',
					href: 'https://commons.wikimedia.org/wiki/Commons:Categories'
				} ) ).parse()
			)
		} );
		this.mainFields.push( this.categoriesDetailsField );

		this.dateDetails = new uw.DateDetailsWidget();
		this.dateDetailsField = new uw.FieldLayout( this.dateDetails, {
			label: mw.message( 'mwe-upwiz-date-created' ).text(),
			help: mw.message( 'mwe-upwiz-tooltip-date' ).text(),
			required: false
		} );
		this.mainFields.push( this.dateDetailsField );

		$moreDetailsWrapperDiv = $( '<div class="mwe-more-details">' );
		$moreDetailsDiv = $( '<div></div>' );

		this.otherDetails = new uw.OtherDetailsWidget();
		this.otherDetailsField = new uw.FieldLayout( this.otherDetails, {
			label: mw.message( 'mwe-upwiz-other' ).text(),
			help: mw.message( 'mwe-upwiz-tooltip-other' ).text()
		} );
		this.mainFields.push( this.otherDetailsField );

		this.locationInput = new uw.LocationDetailsWidget( { showHeading: true } );
		this.locationInputField = new uw.FieldLayout( this.locationInput, {
			// No 'label', labels are included in this widget
			help: new OO.ui.HtmlSnippet(
				mw.message( 'mwe-upwiz-tooltip-location', $( '<a>' ).attr( {
					target: '_blank',
					href: '//commons.wikimedia.org/wiki/Commons:Geocoding'
				} ) ).parse()
			)
		} );
		this.mainFields.push( this.locationInputField );

		$moreDetailsDiv.append(
			this.locationInputField.$element,
			this.otherDetailsField.$element
		);

		/* Build the form for the file upload */
		this.$form = $( '<form id="mwe-upwiz-detailsform' + this.upload.index + '"></form>' ).addClass( 'detailsForm' );
		this.$form.append(
			this.titleDetailsField.$element,
			this.descriptionsDetailsField.$element,
			this.deedChooserDetailsField.$element,
			this.dateDetailsField.$element,
			this.categoriesDetailsField.$element
		);

		this.$form.on( 'submit', function ( e ) {
			// Prevent actual form submission
			e.preventDefault();
		} );

		this.campaignDetailsFields = [];
		$.each( mw.UploadWizard.config.fields, function ( i, field ) {
			var customDetails, customDetailsField;

			if ( field.wikitext ) {
				customDetails = new uw.CampaignDetailsWidget( field );
				customDetailsField = new uw.FieldLayout( customDetails, {
					label: $( $.parseHTML( field.label ) ),
					required: !!field.required
				} );

				if ( field.initialValue ) {
					customDetails.setSerialized( { value: field.initialValue } );
				}

				details.$form.append( customDetailsField.$element );
				details.campaignDetailsFields.push( customDetailsField );
			}
		} );

		$moreDetailsWrapperDiv
			.append(
				$( '<a>' ).text( mw.msg( 'mwe-upwiz-more-options' ) )
					.addClass( 'mwe-upwiz-details-more-options mw-collapsible-toggle mw-collapsible-arrow' ),
				$moreDetailsDiv.addClass( 'mw-collapsible-content' )
			)
			.makeCollapsible( { collapsed: true } );

		this.$form.append(
			$moreDetailsWrapperDiv
		);

		this.submittingDiv = $( '<div>' ).addClass( 'mwe-upwiz-submitting' )
			.append(
				$( '<div>' ).addClass( 'mwe-upwiz-file-indicator' ),
				$( '<div>' ).addClass( 'mwe-upwiz-details-texts' ).append(
					$( '<div>' ).addClass( 'mwe-upwiz-visible-file-filename-text' ),
					$( '<div>' ).addClass( 'mwe-upwiz-file-status-line' )
				)
			);

		// Add in remove control to submittingDiv
		this.removeCtrl = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-remove' ).text(),
			title: mw.message( 'mwe-upwiz-remove-upload' ).text(),
			flags: 'destructive',
			icon: 'remove',
			framed: false
		} ).on( 'click', function () {
			details.upload.remove();
		} );

		this.submittingDiv.find( '.mwe-upwiz-file-status-line' )
			.append( this.removeCtrl.$element );

		$( this.dataDiv ).append(
			this.$form,
			this.submittingDiv
		).morphCrossfader();

		$( this.div ).append(
			this.thumbnailDiv,
			this.dataDiv
		);

		uri = new mw.Uri( location.href, { overrideKeys: true } );
		if ( mw.UploadWizard.config.defaults.description || uri.query.descriptionlang ) {
			this.descriptionsDetails.setSerialized( {
				descriptions: [
					{
						language: uri.query.descriptionlang ?
							uw.DescriptionDetailsWidget.static.getClosestAllowedLanguage( uri.query.descriptionlang ) :
							uw.DescriptionDetailsWidget.static.getDefaultLanguage(),
						description: mw.UploadWizard.config.defaults.description || ''
					}
				]
			} );
		}
	};

	/**
	 * Reliably turn input into a MediaWiki title that is located in the File: namespace
	 *
	 *     var title = mw.UploadWizardDetails.makeTitleInFileNS( 'filename.ext' );
	 *
	 * @static
	 * @param {string} filename Desired file name; optionally with File: namespace prefixed
	 * @return {mw.Title|null}
	 */
	mw.UploadWizardDetails.makeTitleInFileNS = function ( filename ) {
		var mwTitle = mw.Title.newFromText( filename, NS_FILE );
		if ( mwTitle && mwTitle.getNamespaceId() !== NS_FILE ) {
			// Force file namespace
			mwTitle = mw.Title.makeTitle( NS_FILE, filename );
		}
		return mwTitle;
	};

	mw.UploadWizardDetails.prototype = {

		// Has this details object been attached to the DOM already?
		isAttached: false,

		/*
		 * Append the div for this details object to the DOM.
		 * We need to ensure that we add divs in the right order
		 * (the order in which the user selected files).
		 *
		 * Will only append once.
		 */
		attach: function () {
			if ( !this.isAttached ) {
				$( this.containerDiv ).append( this.div );
				this.isAttached = true;
			}
		},

		// Metadata which we can copy over to other details objects
		// objects with key:metadata name and value:boolean value indicating default checked status
		copyMetadataTypes: {
			title: true,
			description: true,
			date: false,
			categories: true,
			location: false,
			other: true
		},

		/**
		 * Get file page title for this upload.
		 *
		 * @return {mw.Title|null}
		 */
		getTitle: function () {
			return this.titleDetails.getTitle();
		},

		/**
		 * Display error message about multiple uploaded files with the same title specified
		 *
		 * @chainable
		 */
		setDuplicateTitleError: function () {
			// TODO This should give immediate response, not only when submitting the form
			this.titleDetailsField.setErrors( [ mw.message( 'mwe-upwiz-error-title-duplicate' ) ] );
			return this;
		},

		/*
		 * Copy metadata from the first upload to other uploads.
		 *
		 * We don't worry too much about validation here since all input is validated prior to
		 * submission, and the user will be alerted about validation errors in the first upload
		 * description.
		 *
		 * @param String metadataType One of the types defined in the copyMetadataTypes property
		 */
		copyMetadata: function ( metadataType ) {
			var titleZero, matches, i, j, currentTitle,
				uploads = this.upload.wizard.uploads,
				sourceValue,
				sourceUpload = uploads[ 0 ];

			function oouiCopy( property ) {
				var i;

				sourceValue = sourceUpload.details[ property ].getSerialized();
				for ( i = 1; i < uploads.length; i++ ) {
					uploads[ i ].details[ property ].setSerialized( sourceValue );
				}
			}

			if ( metadataType === 'title' ) {

				// Add number suffix to first title if no numbering present
				titleZero = sourceUpload.details.titleDetails.getSerialized().title;
				matches = titleZero.match( /(\D+)(\d{1,3})(\D*)$/ );
				if ( matches === null ) {
					titleZero = titleZero + ' 01';
				}

				// Overwrite remaining title inputs with first title + increment of rightmost
				// number in the title. Note: We ignore numbers with more than three digits, because these
				// are more likely to be years ("Wikimania 2011 Celebration") or other non-sequence
				// numbers.

				// This loop starts from 0 and not 1 - we want to overwrite the source upload too!
				for ( i = 0; i < uploads.length; i++ ) {
					/*jshint loopfunc:true */
					currentTitle = titleZero.replace( /(\D+)(\d{1,3})(\D*)$/,
						function ( str, m1, m2, m3 ) {
							var newstr = String( +m2 + i );
							return m1 + new Array( m2.length + 1 - newstr.length )
								.join( '0' ) + newstr + m3;
						}
					);
					uploads[ i ].details.titleDetails.setSerialized( { title: currentTitle } );
				}

			} else if ( metadataType === 'description' ) {

				oouiCopy( 'descriptionsDetails' );

			} else if ( metadataType === 'date' ) {

				oouiCopy( 'dateDetails' );

			} else if ( metadataType === 'categories' ) {

				oouiCopy( 'categoriesDetails' );

			} else if ( metadataType === 'location' ) {

				oouiCopy( 'locationInput' );

			} else if ( metadataType === 'other' ) {

				oouiCopy( 'otherDetails' );

				// Copy fields added though campaigns
				for ( j = 0; j < sourceUpload.details.campaignDetailsFields.length; j++ ) {
					sourceValue = sourceUpload.details.campaignDetailsFields[ j ].fieldWidget.getSerialized();
					for ( i = 1; i < uploads.length; i++ ) {
						uploads[ i ].details.campaignDetailsFields[ j ].fieldWidget.setSerialized( sourceValue );
					}
				}

			} else {
				throw new Error( 'Attempted to copy unsupported metadata type: ' + metadataType );
			}

			if ( metadataType === 'location' || metadataType === 'other' ) {
				// Expand collapsed sections if we changed the fields within
				for ( i = 1; i < uploads.length; i++ ) {
					uploads[ i ].details.$form.find( '.mwe-more-details' )
						.data( 'mw-collapsible' ).expand();
				}
			}
		},

		/*
		 * Construct and display the widget to copy metadata
		 *
		 * Call before showing the Details step. Builds, adds and displays
		 * a metadata copy widget for the details view of this specific upload
		 */
		buildAndShowCopyMetadata: function () {
			var copyButton,
				copyTypes = {},
				fieldset = new OO.ui.FieldsetLayout(),
				details = this,
				$copyMetadataDiv = $( '<div>' );

			if ( mw.UploadWizard.config.copyMetadataFeature !== true ||
				this.$copyMetadataWrapperDiv !== undefined ) {
				return;
			}

			this.$copyMetadataWrapperDiv = $( '<div>' ).addClass( 'mwe-upwiz-metadata-copier' );

			$copyMetadataDiv.append( fieldset.$element );

			$.each( this.copyMetadataTypes, function ( metadataName, defaultStatus ) {
				var copyMetadataMsg, checkbox, field,
					// mwe-upwiz-copy-title, mwe-upwiz-copy-description, mwe-upwiz-copy-date,
					// mwe-upwiz-copy-categories, mwe-upwiz-copy-location, mwe-upwiz-copy-other
					copyMessage = 'mwe-upwiz-copy-' + metadataName;

				copyMetadataMsg = mw.message( copyMessage ).text();

				checkbox = new OO.ui.CheckboxInputWidget( {
					selected: defaultStatus
				} );

				copyTypes[ metadataName ] = checkbox;

				field = new OO.ui.FieldLayout( checkbox, {
					label: copyMetadataMsg,
					align: 'inline'
				} );

				fieldset.addItems( [ field ] );
			} ) ;

			// Keep our checkboxShiftClick behaviour alive
			$copyMetadataDiv.find( 'input[type=checkbox]' ).checkboxShiftClick();

			copyButton = new OO.ui.ButtonWidget( {
				id: 'mwe-upwiz-copy-metadata-button',
				label: mw.message( 'mwe-upwiz-copy-metadata-button' ).text(),
				flags: [ 'constructive' ]
			} ).on( 'click', function () {
				$.each( details.copyMetadataTypes, function ( metadataType ) {
					if ( copyTypes[ metadataType ].isSelected() ) {
						details.copyMetadata( metadataType );
					}
				} );

				copyButton.setLabel( mw.message( 'mwe-upwiz-copied-metadata-button' ).text() );

				setTimeout( function () {
					copyButton.setLabel( mw.message( 'mwe-upwiz-copy-metadata-button' ).text() );
				}, 1000 );
			} );

			$copyMetadataDiv.append( copyButton.$element );

			this.$copyMetadataWrapperDiv
				.append(
					$( '<a>' ).text( mw.msg( 'mwe-upwiz-copy-metadata' ) )
						.addClass( 'mwe-upwiz-details-copy-metadata mw-collapsible-toggle mw-collapsible-arrow' ),
					$copyMetadataDiv.addClass( 'mw-collapsible-content' )
				)
				.makeCollapsible( { collapsed: true } );

			this.$form.append( this.$copyMetadataWrapperDiv );
			this.$copyMetadataWrapperDiv.show();
		},

		/**
		 * @private
		 */
		getAllFields: function () {
			return [].concat(
				this.mainFields,
				this.upload.deedChooser.deed ? this.upload.deedChooser.deed.getFields() : [],
				this.campaignDetailsFields
			);
		},

		/**
		 * Check all the fields for validity.
		 *
		 * @return {jQuery.Promise} Promise resolved with multiple array arguments, each containing a
		 *   list of error messages for a single field. If API requests necessary to check validity
		 *   fail, the promise may be rejected. The form is valid if the promise is resolved with all
		 *   empty arrays.
		 */
		getErrors: function () {
			return $.when.apply( $, this.getAllFields().map( function ( fieldLayout ) {
				return fieldLayout.fieldWidget.getErrors();
			} ) );
		},

		/**
		 * Check all the fields for warnings.
		 *
		 * @return {jQuery.Promise} Same as #getErrors
		 */
		getWarnings: function () {
			return $.when.apply( $, this.getAllFields().map( function ( fieldLayout ) {
				return fieldLayout.fieldWidget.getWarnings();
			} ) );
		},

		/**
		 * Check all the fields for errors and warnings and display them in the UI.
		 */
		checkValidity: function () {
			this.getAllFields().forEach( function ( fieldLayout ) {
				fieldLayout.checkValidity();
			} );
		},

		/**
		 * Get a thumbnail caption for this upload (basically, the first description).
		 */
		getThumbnailCaption: function () {
			return this.descriptionsDetails.getSerialized().descriptions[ 0 ].description.trim();
		},

		/**
		 * toggles whether we use the 'macro' deed or our own
		 */
		useCustomDeedChooser: function () {
			this.deedChooserDetailsField.toggle( true );
			this.deedChooserDetails.useCustomDeedChooser( this.upload );
		},

		/**
		 * Given the API result pull some info into the form ( for instance, extracted from EXIF, desired filename )
		 *
		 * @param {Object} result Upload API result object
		 */
		populate: function () {
			var thumbnailDiv = this.thumbnailDiv;
			this.upload.getThumbnail(
				mw.UploadWizard.config.thumbnailWidth,
				mw.UploadWizard.config.thumbnailMaxHeight
			).done( function ( thumb ) {
				mw.UploadWizard.placeThumbnail( thumbnailDiv, thumb );
			} );
			this.prefillDate();
			this.prefillAuthor();
			this.prefillTitle();
			this.prefillDescription();
			this.prefillLocation();
		},

		/**
		 * Check if we got an EXIF date back and enter it into the details
		 * XXX We ought to be using date + time here...
		 * EXIF examples tend to be in ISO 8601, but the separators are sometimes things like colons, and they have lots of trailing info
		 * (which we should actually be using, such as time and timezone)
		 */
		prefillDate: function () {
			var dateObj, metadata, dateTimeRegex, matches, dateStr, saneTime,
				dateMode = 'calendar',
				yyyyMmDdRegex = /^(\d\d\d\d)[:\/\-](\d\d)[:\/\-](\d\d)\D.*/,
				timeRegex = /\D(\d\d):(\d\d):(\d\d)/;

			// XXX surely we have this function somewhere already
			function pad( n ) {
				return ( n < 10 ? '0' : '' ) + String( n );
			}

			function getSaneTime( date ) {
				var str = '';

				str += pad( date.getHours() ) + ':';
				str += pad( date.getMinutes() ) + ':';
				str += pad( date.getSeconds() );

				return str;
			}

			if ( this.upload.imageinfo.metadata ) {
				metadata = this.upload.imageinfo.metadata;
				$.each( [ 'datetimeoriginal', 'datetimedigitized', 'datetime', 'date' ], function ( i, propName ) {
					var matches, timeMatches,
						dateInfo = metadata[ propName ];
					if ( dateInfo ) {
						matches = dateInfo.trim().match( yyyyMmDdRegex );
						if ( matches ) {
							timeMatches = dateInfo.trim().match( timeRegex );
							if ( timeMatches ) {
								dateObj = new Date( parseInt( matches[ 1 ], 10 ),
											parseInt( matches[ 2 ], 10 ) - 1,
											parseInt( matches[ 3 ], 10 ),
											parseInt( timeMatches[ 1 ], 10 ),
											parseInt( timeMatches[ 2 ], 10 ),
											parseInt( timeMatches[ 3 ], 10 ) );
							} else {
								dateObj = new Date( parseInt( matches[ 1 ], 10 ),
											parseInt( matches[ 2 ], 10 ) - 1,
											parseInt( matches[ 3 ], 10 ) );
							}
							return false; // break from $.each
						}
					}
				} );
			}

			// If we don't have EXIF lets try other sources - Flickr
			if ( dateObj === undefined && this.upload.file !== undefined && this.upload.file.date !== undefined ) {
				dateTimeRegex = /^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/;
				matches = this.upload.file.date.match( dateTimeRegex );
				if ( matches ) {
					this.dateDetails.setSerialized( {
						mode: dateMode,
						value: this.upload.file.date
					} );
					return;
				}
			}

			// if we don't have EXIF or other metadata, just don't put a date in.
			// XXX if we have FileAPI, it might be clever to look at file attrs, saved
			// in the upload object for use here later, perhaps
			if ( dateObj === undefined ) {
				return;
			}

			dateStr = dateObj.getFullYear() + '-' + pad( dateObj.getMonth() + 1 ) + '-' + pad( dateObj.getDate() );

			// Add the time
			// If the date but not the time is set in EXIF data, we'll get a bogus
			// time value of '00:00:00'.
			// FIXME: Check for missing time value earlier rather than blacklisting
			// a potentially legitimate time value.
			saneTime = getSaneTime( dateObj );
			if ( saneTime !== '00:00:00' ) {
				dateStr += ' ' + saneTime;

				// Switch to freeform date field. DateInputWidget (with calendar) handles dates only, not times.
				dateMode = 'arbitrary';
			}

			// ok by now we should definitely have a dateObj and a date string
			this.dateDetails.setSerialized( {
				mode: dateMode,
				value: dateStr
			} );
		},

		/**
		 * Set the title of the thing we just uploaded, visibly
		 */
		prefillTitle: function () {
			this.titleDetails.setSerialized( {
				title: this.upload.title.getNameText()
			} );
		},

		/**
		 * Prefill the image description if we have a description
		 *
		 * Note that this is not related to specifying the description from the query
		 * string (that happens earlier). This is for when we have retrieved a
		 * description from an upload_by_url upload (e.g. Flickr transfer)
		 * or from the metadata.
		 */
		prefillDescription: function () {
			var m, descText;

			if (
				this.descriptionsDetails.getWikiText() === '' &&
				this.upload.file !== undefined
			) {
				m = this.upload.imageinfo.metadata;
				descText = this.upload.file.description ||
					( m && m.imagedescription && m.imagedescription[ 0 ] && m.imagedescription[ 0 ].value );

				if ( descText ) {
					// strip out any HTML tags
					descText = descText.replace( /<[^>]+>/g, '' );
					// & and " are escaped by Flickr, so we need to unescape
					descText = descText.replace( /&amp;/g, '&' ).replace( /&quot;/g, '"' );

					this.descriptionsDetails.setSerialized( {
						descriptions: [
							{
								// The language is probably wrong in many cases...
								language: uw.DescriptionDetailsWidget.static.getClosestAllowedLanguage( mw.config.get( 'wgContentLanguage' ) ),
								description: descText.trim()
							}
						]
					} );
				}
			}
		},

		/**
		 * Prefill location input from image info and metadata
		 *
		 * As of MediaWiki 1.18, the exif parser translates the rational GPS data tagged by the camera
		 * to decimal format.  Let's just use that.
		 */
		prefillLocation: function () {
			var dir,
				m = this.upload.imageinfo.metadata,
				modified = false,
				values = {};

			if ( mw.UploadWizard.config.defaults.lat ) {
				values.latitude = mw.UploadWizard.config.defaults.lat;
				modified = true;
			}
			if ( mw.UploadWizard.config.defaults.lon ) {
				values.longitude = mw.UploadWizard.config.defaults.lon;
				modified = true;
			}
			if ( mw.UploadWizard.config.defaults.heading ) {
				values.heading = mw.UploadWizard.config.defaults.heading;
				modified = true;
			}

			if ( m ) {
				dir = m.gpsimgdirection || m.gpsdestbearing;

				if ( dir ) {
					if ( dir.match( /^\d+\/\d+$/ ) !== null ) {
						// Apparently it can take the form "x/y" instead of
						// a decimal value. Mighty silly, but let's save it.
						dir = dir.split( '/' );
						dir = parseInt( dir[ 0 ], 10 ) / parseInt( dir[ 1 ], 10 );
					}

					values.heading = dir;

					modified = true;
				}

				// Prefill useful stuff only
				if ( Number( m.gpslatitude ) && Number( m.gpslongitude ) ) {
					values.latitude = m.gpslatitude;
					values.longitude = m.gpslongitude;
					modified = true;
				} else if (
					this.upload.file &&
					this.upload.file.location &&
					this.upload.file.location.latitude &&
					this.upload.file.location.longitude
				) {
					values.latitude = this.upload.file.location.latitude;
					values.longitude = this.upload.file.location.longitude;
					modified = true;
				}
			}

			this.locationInput.setSerialized( values );

			if ( modified ) {
				this.$form.find( '.mwe-more-details' )
					.data( 'mw-collapsible' ).expand();
			}
		},

		/**
		 * Prefill author (such as can be determined) from image info and metadata
		 * XXX user pref?
		 */
		prefillAuthor: function () {
			if ( this.upload.imageinfo.metadata && this.upload.imageinfo.metadata.author ) {
				$( this.authorInput ).val( this.upload.imageinfo.metadata.author );
			}
		},

		/**
		 * Convert entire details for this file into wikiText, which will then be posted to the file
		 *
		 * This function assumes that all input is valid.
		 *
		 * @return {string} wikitext representing all details
		 */
		getWikiText: function () {
			var deed, info, key,
				information,
				wikiText = '';

			// https://commons.wikimedia.org/wiki/Template:Information
			// can we be more slick and do this with maps, applys, joins?
			information = {
				// {{lang|description in lang}}*   required
				description: '',
				// YYYY, YYYY-MM, or YYYY-MM-DD	 required  - use jquery but allow editing, then double check for sane date.
				date: '',
				// {{own}} or wikitext	optional
				source: '',
				// any wikitext, but particularly {{Creator:Name Surname}}   required
				author: '',
				// leave blank unless OTRS pending; by default will be "see below"   optional
				permission: '',
				// pipe separated list, other versions	 optional
				'other versions': ''
			};

			information.description = this.descriptionsDetails.getWikiText();

			$.each( this.campaignDetailsFields, function ( i, layout ) {
				information.description += layout.fieldWidget.getWikiText();
			} );

			information.date = this.dateDetails.getWikiText();

			deed = this.upload.deedChooser.deed;

			information.source = deed.getSourceWikiText();

			information.author = deed.getAuthorWikiText();

			info = '';

			for ( key in information ) {
				info += '|' + key.replace( /:/g, '_' ) + '=' + information[ key ] + '\n';
			}

			wikiText += '=={{int:filedesc}}==\n';
			wikiText += '{{Information\n' + info + '}}\n';

			wikiText += this.locationInput.getWikiText() + '\n';

			// add an "anything else" template if needed
			wikiText += this.otherDetails.getWikiText() + '\n\n';

			// add licensing information
			wikiText += '\n=={{int:license-header}}==\n';
			wikiText += deed.getLicenseWikiText() + '\n\n';

			if ( mw.UploadWizard.config.autoAdd.wikitext !== undefined ) {
				wikiText += mw.UploadWizard.config.autoAdd.wikitext + '\n';
			}

			// add parameters for list callback bot
			// this cue will be used to supplement a wiki page with an image thumbnail
			if ( $( '#imgPicker' + this.upload.index ).prop( 'checked' ) ) {
				wikiText += '\n<!-- WIKIPAGE_UPDATE_PARAMS ' +
					mw.UploadWizard.config.defaults.objref +
					' -->\n';
			}

			// categories
			wikiText += '\n' + this.categoriesDetails.getWikiText();

			// sanitize wikitext if TextCleaner is defined (MediaWiki:TextCleaner.js)
			if ( typeof window.TextCleaner !== 'undefined' && typeof window.TextCleaner.sanitizeWikiText === 'function' ) {
				wikiText = window.TextCleaner.sanitizeWikiText( wikiText, true );
			}

			// remove too many newlines in a row
			wikiText = wikiText.replace( /\n{3,}/g, '\n\n' );

			return wikiText;
		},

		/**
		 * Post wikitext as edited here, to the file
		 * XXX This should be split up -- one part should get wikitext from the interface here, and the ajax call
		 * should be be part of upload
		 *
		 * This function is only called if all input seems valid (which doesn't mean that we can't get
		 * an error, see #processError).
		 *
		 * @return {jQuery.Promise}
		 */
		submit: function () {
			var params, wikiText, apiPromise,
				details = this;

			$( 'form', this.containerDiv ).submit();

			this.upload.title = this.getTitle();
			this.upload.state = 'submitting-details';
			this.setStatus( mw.message( 'mwe-upwiz-submitting-details' ).text() );
			this.showIndicator( 'progress' );

			this.firstPoll = ( new Date() ).getTime();

			params = {
				action: 'upload',
				filekey: this.upload.fileKey,
				filename: this.getTitle().getMain(),
				comment: 'User created page with ' + mw.UploadWizard.userAgent
			};

			// Only enable async publishing if file is larger than 10MiB
			if ( this.upload.transportWeight > 10 * 1024 * 1024 ) {
				params.async = true;
			}

			wikiText = this.getWikiText();

			if ( wikiText !== false ) {
				params.text = wikiText;
				apiPromise = details.upload.api.postWithEditToken( params );
				return apiPromise
					.then(
						function ( result ) {
							if ( !result || result.error || ( result.upload && result.upload.warnings ) ) {
								uw.eventFlowLogger.logApiError( 'details', result );
							}
							return details.handleSubmitResult( result, params );
						},
						function ( code, info, result ) {
							uw.eventFlowLogger.logApiError( 'details', result );
							details.upload.state = 'error';
							details.processError( code, info );
							return $.Deferred().reject( code, info );
						}
					)
					.promise( { abort: apiPromise.abort } );
			}

			return $.Deferred().reject();
		},

		/**
		 * Handles the result of a submission.
		 *
		 * @param {Object} result API result of an upload or status check.
		 * @param {Object} params What we passed to the API that caused this response.
		 * @return {jQuery.Promise}
		 */
		handleSubmitResult: function ( result, params ) {
			var wx, warningsKeys, existingFile, existingFileUrl, existingFileExt, ourFileExt,
				details = this,
				warnings = null,
				ignoreTheseWarnings = false,
				deferred = $.Deferred();

			if ( result && result.upload && result.upload.result === 'Poll' ) {
				// if async publishing takes longer than 10 minutes give up
				if ( ( ( new Date() ).getTime() - this.firstPoll ) > 10 * 60 * 1000 ) {
					return $.Deferred().reject( 'server-error', 'unknown server error' );
				} else {
					if ( result.upload.stage === undefined && window.console ) {
						return $.Deferred().reject( 'no-stage', 'Unable to check file\'s status' );
					} else {
						// Messages that can be returned:
						// * mwe-upwiz-queued
						// * mwe-upwiz-publish
						// * mwe-upwiz-assembling
						this.setStatus( mw.message( 'mwe-upwiz-' + result.upload.stage ).text() );
						setTimeout( function () {
							if ( details.upload.state !== 'aborted' ) {
								details.upload.api.postWithEditToken( {
									action: 'upload',
									checkstatus: true,
									filekey: details.upload.fileKey
								} ).then( function ( result ) {
									if ( !result || result.error || ( result.upload && result.upload.warnings ) ) {
										uw.eventFlowLogger.logApiError( 'details', result );
									}
									return details.handleSubmitResult( result ).then( deferred.resolve, deferred.reject );
								}, function ( code, info, result ) {
									uw.eventFlowLogger.logApiError( 'details', result );
									deferred.reject( code, info );
								} );
							} else {
								deferred.resolve( 'aborted' );
							}
						}, 3000 );

						return deferred.promise();
					}
				}
			}
			if ( result && result.upload && result.upload.warnings ) {
				warnings = result.upload.warnings;
			}
			if ( warnings && warnings.exists ) {
				existingFile = warnings.exists;
			} else if ( warnings && warnings[ 'exists-normalized' ] ) {
				existingFile = warnings[ 'exists-normalized' ];
				existingFileExt = mw.Title.normalizeExtension( existingFile.split( '.' ).pop() );
				ourFileExt = mw.Title.normalizeExtension( this.getTitle().getExtension() );

				if ( existingFileExt !== ourFileExt ) {
					delete warnings[ 'exists-normalized' ];
					ignoreTheseWarnings = true;
				}
			}
			if ( warnings && warnings[ 'was-deleted' ] ) {
				delete warnings[ 'was-deleted' ];
				ignoreTheseWarnings = true;
			}
			for ( wx in warnings ) {
				if ( warnings.hasOwnProperty( wx ) ) {
					// if there are other warnings, deal with those first
					ignoreTheseWarnings = false;
				}
			}
			if ( result && result.upload && result.upload.imageinfo ) {
				this.upload.extractImageInfo( result.upload.imageinfo );
				this.upload.thisProgress = 1.0;
				this.upload.state = 'complete';
				this.showIndicator( 'uploaded' );
				this.setStatus( mw.message( 'mwe-upwiz-published' ).text() );
				return $.Deferred().resolve();
			} else if ( ignoreTheseWarnings ) {
				params.ignorewarnings = 1;
				return this.upload.api.postWithEditToken( params ).then( function ( result ) {
					if ( !result || result.error || ( result.upload && result.upload.warnings ) ) {
						uw.eventFlowLogger.logApiError( 'details', result );
					}
					return details.handleSubmitResult( result );
				}, function ( code, info ) {
					uw.eventFlowLogger.logApiError( 'details', result );
					return $.Deferred().reject( code, info );
				} );
			} else if ( result && result.upload.warnings ) {
				if ( warnings.thumb || warnings[ 'thumb-name' ] ) {
					this.recoverFromError( mw.message( 'mwe-upwiz-error-title-thumbnail' ), 'error-title-thumbnail' );
				} else if ( warnings.badfilename ) {
					this.recoverFromError( mw.message( 'mwe-upwiz-error-title-badchars' ), 'title-badchars' );
				} else if ( warnings[ 'bad-prefix' ] ) {
					this.recoverFromError( mw.message( 'mwe-upwiz-error-title-senselessimagename' ), 'title-senselessimagename' );
				} else if ( existingFile ) {
					existingFileUrl = mw.config.get( 'wgServer' ) + mw.Title.makeTitle( NS_FILE, existingFile ).getUrl();
					this.recoverFromError( mw.message( 'mwe-upwiz-api-warning-exists', existingFileUrl ), 'api-warning-exists' );
				} else if ( warnings.duplicate ) {
					this.recoverFromError( mw.message( 'mwe-upwiz-upload-error-duplicate' ), 'upload-error-duplicate' );
				} else if ( warnings[ 'duplicate-archive' ] !== undefined ) {
					// warnings[ 'duplicate-archive' ] may be '' (empty string) for revdeleted files
					if ( this.upload.ignoreWarning[ 'duplicate-archive' ] ) {
						// We already told the interface to ignore this warning, so
						// let's steamroll over it and re-call this handler.
						params.ignorewarnings = true;
						return this.upload.api.postWithEditToken( params ).then( function ( result ) {
							if ( !result || result.error || ( result.upload && result.upload.warnings ) ) {
								uw.eventFlowLogger.logApiError( 'details', result );
							}
							return details.handleSubmitResult( result );
						}, function ( code, info ) {
							uw.eventFlowLogger.logApiError( 'details', result );
							return $.Deferred().reject( code, info );
						} );
					} else {
						// This should _never_ happen, but just in case....
						this.recoverFromError( mw.message( 'mwe-upwiz-upload-error-duplicate-archive' ), 'upload-error-duplicate-archive' );
					}
				} else {
					warningsKeys = [];
					$.each( warnings, function ( key ) {
						warningsKeys.push( key );
					} );
					this.upload.state = 'error';
					this.recoverFromError( mw.message( 'api-error-unknown-warning', warningsKeys.join( ', ' ) ), 'api-error-unknown-warning' );
				}

				return $.Deferred().resolve();
			} else {
				return $.Deferred().reject( 'this-info-missing', result );
			}
		},

		/**
		 * Create a recoverable error -- show the form again, and highlight the problematic field.
		 *
		 * @param {mw.Message} errorMessage Error message to show.
		 * @param {string} errorCode
		 */
		recoverFromError: function ( errorMessage, errorCode ) {
			uw.eventFlowLogger.logError( 'details', { code: errorCode, message: errorMessage } );
			this.upload.state = 'error';
			this.dataDiv.morphCrossfade( '.detailsForm' );
			this.titleDetailsField.setErrors( [ errorMessage ] );
		},

		/**
		 * Show error state, possibly using a recoverable error form
		 *
		 * @param {string} code Error code
		 * @param {string} statusLine Status line
		 */
		showError: function ( code, statusLine ) {
			uw.eventFlowLogger.logError( 'details', { code: code, message: statusLine } );
			this.showIndicator( 'error' );
			this.setStatus( statusLine );
		},

		/**
		 * Decide how to treat various errors
		 *
		 * @param {string} code Error code
		 * @param {Mixed} result Result from ajax call
		 */
		processError: function ( code, result ) {
			var statusKey, comma,
				statusLine = mw.message( 'api-error-unclassified' ).text(),
				titleBlacklistMessageMap = {
					senselessimagename: 'senselessimagename', // TODO This is probably never hit?
					'titleblacklist-custom-filename': 'hosting',
					'titleblacklist-custom-SVG-thumbnail': 'thumbnail',
					'titleblacklist-custom-thumbnail': 'thumbnail',
					'titleblacklist-custom-double-apostrophe': 'double-apostrophe'
				},
				titleErrorMap = {
					'fileexists-shared-forbidden': 'fileexists-shared-forbidden',
					protectedpage: 'protected'
				};

			if ( code === 'badtoken' ) {
				this.api.badToken( 'csrf' );
				// TODO Automatically try again instead of requiring the user to bonk the button
			}

			if ( code === 'ratelimited' ) {
				// None of the remaining uploads is going to succeed, and every failed one is going to
				// ping the rate limiter again.
				this.upload.wizard.steps.details.queue.abortExecuting();
			} else if ( code === 'http' && result && result.exception === 'abort' ) {
				// This upload has just been aborted because an earlier one got the 'ratelimited' error.
				// This could potentially also come up when an upload is removed by the user, but in that
				// case the UI is invisible anyway, so whatever.
				code = 'ratelimited';
			}

			if ( result && code ) {
				if ( titleErrorMap[ code ] ) {
					this.recoverFromError( mw.message( 'mwe-upwiz-error-title-' + titleErrorMap[ code ] ), 'title-' + titleErrorMap[ code ] );
					return;
				} else if ( code === 'titleblacklist-forbidden' ) {
					this.recoverFromError( this.titleId, mw.message( 'mwe-upwiz-error-title-' + titleBlacklistMessageMap[ result.error.message ] ), 'title-' + titleBlacklistMessageMap[ result.error.message ] );
					return;
				} else {
					statusKey = 'api-error-' + code;
					if ( code === 'filetype-banned' && result.error.blacklisted ) {
						comma = mw.message( 'comma-separator' ).text();
						code = 'filetype-banned-type';
						statusLine = mw.message( 'api-error-filetype-banned-type',
							result.error.blacklisted.join( comma ),
							result.error.allowed.join( comma ),
							result.error.allowed.length,
							result.error.blacklisted.length
						).text();
					} else if ( result.error && result.error.info ) {
						statusLine = mw.message( statusKey, result.error.info ).text();
					} else {
						statusLine = mw.message( statusKey, '[no error info]' ).text();
					}
				}
			}
			this.showError( code, statusLine );
		},

		setStatus: function ( s ) {
			this.div.find( '.mwe-upwiz-file-status-line' ).text( s ).show();
		},

		showIndicator: function ( statusStr ) {
			this.div.find( '.mwe-upwiz-file-indicator' )
				.show()
				.removeClass( 'mwe-upwiz-status-progress mwe-upwiz-status-error mwe-upwiz-status-uploaded' )
				.addClass( 'mwe-upwiz-status-' + statusStr );
		},

		setVisibleTitle: function ( s ) {
			$( this.submittingDiv )
				.find( '.mwe-upwiz-visible-file-filename-text' )
				.text( s );
		}
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
