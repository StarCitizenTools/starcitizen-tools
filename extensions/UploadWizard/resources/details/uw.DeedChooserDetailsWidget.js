( function ( mw, uw, $, OO ) {

	/**
	 * A deed chooser field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 */
	uw.DeedChooserDetailsWidget = function UWDeedChooserDetailsWidget() {
		uw.DeedChooserDetailsWidget.parent.call( this );

		this.deedChooser = false;
		this.$element.addClass( 'mwe-upwiz-deedChooserDetailsWidget' );
	};
	OO.inheritClass( uw.DeedChooserDetailsWidget, uw.DetailsWidget );

	/**
	 * toggles whether we use the 'macro' deed or our own
	 */
	uw.DeedChooserDetailsWidget.prototype.useCustomDeedChooser = function ( upload ) {
		var config, deed, deedDiv;

		// Defining own deedChooser for uploads coming from external service
		if ( upload.fromURL ) {
			// XXX can be made a seperate class as mw.UploadFromUrlDeedChooser
			this.deedChooser = upload.deedChooser = { valid: function () { return true; } };

			if ( upload.providedFile.license ) {
				// XXX need to add code in the remaining functions
				this.$element.append( upload.providedFile.licenseMessage );
				this.deedChooser.deed = this.getDeed( upload );
			} else {
				config = { type: 'or', licenses: [ 'custom' ], special: 'custom' };
				deed = {};

				deed.licenseInput = new mw.UploadWizardLicenseInput(
					undefined,
					config,
					1,
					new mw.Api()
				);
				deed.licenseInput.$element.addClass( 'mwe-upwiz-custom-deed' );
				deed.licenseInputField = new uw.FieldLayout( deed.licenseInput );
				this.$element.append( deed.licenseInputField.$element );
				deed.licenseInput.setDefaultValues();

				this.$element.append( upload.providedFile.licenseMessage );
				this.deedChooser.deed = this.getDeed( upload, deed, {
					getFields: function () { return [ this.licenseInputField ]; },
					getLicenseWikiText: function () {
						if ( upload.providedFile.licenseValue ) {
							return upload.providedFile.licenseValue + this.licenseInput.getWikiText();
						} else {
							return this.licenseInput.getWikiText();
						}
					}
				} );
			}
		} else {
			deedDiv = $( '<div class="mwe-upwiz-custom-deed" />' );
			this.$element.append( deedDiv );
			this.deedChooser = upload.deedChooser = new mw.UploadWizardDeedChooser(
				mw.UploadWizard.config,
				deedDiv,
				mw.UploadWizard.getLicensingDeeds( 1, mw.UploadWizard.config ),
				[ upload ] );
			this.deedChooser.onLayoutReady();
		}
	};

	uw.DeedChooserDetailsWidget.prototype.getDeed = function ( upload, deed, overrides ) {
		deed = deed || {};
		overrides = overrides || {};

		// XXX need to add code in the remaining functions
		return $.extend( deed, {
			getFields: function () { return []; },
			getSourceWikiText: function () {
				if ( typeof upload.providedFile.sourceURL !== 'undefined' ) {
					return upload.providedFile.sourceURL;
				} else {
					return upload.providedFile.url;
				}
			},
			getAuthorWikiText: function () {
				return upload.providedFile.author;
			},
			getLicenseWikiText: function () {
				return upload.providedFile.licenseValue;
			}
		}, overrides );
	};

	/**
	 * @inheritdoc
	 */
	uw.DeedChooserDetailsWidget.prototype.getErrors = function () {
		var errors = [];
		if ( this.deedChooser ) {
			if ( !this.deedChooser.deed ) {
				errors.push( mw.message( 'mwe-upwiz-deeds-need-deed' ) );
			}
		}
		return $.Deferred().resolve( errors ).promise();
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
