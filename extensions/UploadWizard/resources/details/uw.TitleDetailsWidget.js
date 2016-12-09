( function ( mw, uw, $, OO ) {

	/**
	 * A title field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 */
	uw.TitleDetailsWidget = function UWTitleDetailsWidget( config ) {
		config = config || {};
		uw.TitleDetailsWidget.parent.call( this );

		this.extension = config.extension;
		// We wouldn't want or use any of mw.widgets.TitleInputWidget functionality.
		this.titleInput = new OO.ui.TextInputWidget( {
			classes: [ 'mwe-title', 'mwe-upwiz-titleDetailsWidget-title' ],
			maxLength: 250
		} );

		// Aggregate 'change' event (with delay)
		this.titleInput.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

		this.$element.addClass( 'mwe-upwiz-titleDetailsWidget' );
		this.$element.append(
			this.titleInput.$element
		);
	};
	OO.inheritClass( uw.TitleDetailsWidget, uw.DetailsWidget );

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.pushPending = function () {
		this.titleInput.pushPending();
	};

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.popPending = function () {
		this.titleInput.popPending();
	};

	/**
	 * Get a mw.Title object for current value.
	 *
	 * @return {mw.Title|null}
	 */
	uw.TitleDetailsWidget.prototype.getTitle = function () {
		var value, extRegex, cleaned, title;
		value = this.titleInput.getValue().trim();
		if ( !value ) {
			return null;
		}
		extRegex = new RegExp( '\\.' + this.extension + '$', 'i' );
		cleaned = value.replace( extRegex, '' ).replace( /\.+$/g, '' ).trim();
		title = mw.UploadWizardDetails.makeTitleInFileNS( cleaned + '.' + this.extension );
		return title;
	};

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.getErrors = function () {
		var
			errors = [],
			value = this.titleInput.getValue().trim(),
			processDestinationCheck = this.processDestinationCheck,
			title = this.getTitle();

		if ( value === '' ) {
			errors.push( mw.message( 'mwe-upwiz-error-blank' ) );
			return $.Deferred().resolve( errors ).promise();
		}

		if ( !title ) {
			errors.push( mw.message( 'mwe-upwiz-unparseable-title' ) );
			return $.Deferred().resolve( errors ).promise();
		}

		return mw.DestinationChecker.checkTitle( title.getPrefixedText() )
			.then( function ( result ) {
				var moreErrors = processDestinationCheck( result );
				if ( result.blacklist.unavailable ) {
					// We don't have a title blacklist, so just check for some likely undesirable patterns.
					moreErrors = moreErrors.concat(
						mw.QuickTitleChecker.checkTitle( title.getNameText() ).map( function ( errorCode ) {
							// Messages:
							// mwe-upwiz-error-title-badchars, mwe-upwiz-error-title-senselessimagename,
							// mwe-upwiz-error-title-thumbnail, mwe-upwiz-error-title-extension,
							return mw.message( 'mwe-upwiz-error-title-' + errorCode );
						} )
					);
				}
				return moreErrors;
			} )
			.then( function ( moreErrors ) {
				return [].concat( errors, moreErrors );
			}, function () {
				return $.Deferred().resolve( errors );
			} );
	};

	/**
	 * Process the result of a destination filename check, return array of mw.Messages objects
	 * representing errors.
	 *
	 * @private
	 * @param {Object} result Result to process, output from mw.DestinationChecker
	 * @return {mw.Message[]} Error messages
	 */
	uw.TitleDetailsWidget.prototype.processDestinationCheck = function ( result ) {
		var messageParams, errors, titleString;

		if ( result.unique.isUnique && result.blacklist.notBlacklisted && !result.unique.isProtected ) {
			return [];
		}

		// Something is wrong with this title.
		errors = [];

		try {
			titleString = result.unique.title || result.title;
			titleString = mw.UploadWizardDetails.makeTitleInFileNS( titleString ).getPrefixedText();
		} catch ( e ) {
			// Unparseable result? This shouldn't happen, we checked for that earlier...
			errors.push( mw.message( 'mwe-upwiz-unparseable-title' ) );
			return errors;
		}

		if ( !result.unique.isUnique ) {
			// result is NOT unique
			if ( result.unique.href ) {
				errors.push( mw.message(
					'mwe-upwiz-fileexists-replace-on-page',
					titleString,
					$( '<a>' ).attr( { href: result.unique.href, target: '_blank' } )
				) );
			} else {
				errors.push( mw.message( 'mwe-upwiz-fileexists-replace-no-link', titleString ) );
			}
		} else if ( result.unique.isProtected ) {
			errors.push( mw.message( 'mwe-upwiz-error-title-protected' ) );
		} else {
			messageParams = [
				'mwe-upwiz-blacklisted-details',
				titleString,
				function () {
					mw.errorDialog( result.blacklist.blacklistReason );
				}
			];

			// feedback request for titleblacklist
			if ( mw.UploadWizard.config.blacklistIssuesPage !== undefined && mw.UploadWizard.config.blacklistIssuesPage !== '' ) {
				messageParams[ 0 ] = 'mwe-upwiz-blacklisted-details-feedback';
				messageParams.push( function () {
					var feedback = new mw.Feedback( {
						title: new mw.Title( mw.UploadWizard.config.blacklistIssuesPage ),
						dialogTitleMessageKey: 'mwe-upwiz-feedback-title'
					} );
					feedback.launch( {
						message: mw.message( 'mwe-upwiz-feedback-blacklist-line-intro', result.blacklist.blacklistLine ).text(),
						subject: mw.message( 'mwe-upwiz-feedback-blacklist-subject', titleString ).text()
					} );
				} );
			}

			errors.push( mw.message.apply( mw, messageParams ) );
		}

		return errors;
	};

	/**
	 * @inheritdoc
	 */
	uw.TitleDetailsWidget.prototype.getWikiText = function () {
		return this.titleInput.getValue().trim();
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.TitleDetailsWidget.prototype.getSerialized = function () {
		return {
			title: this.titleInput.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {string} serialized.language Title language code
	 * @param {string} serialized.title Title text
	 */
	uw.TitleDetailsWidget.prototype.setSerialized = function ( serialized ) {
		this.titleInput.setValue( serialized.title );
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
