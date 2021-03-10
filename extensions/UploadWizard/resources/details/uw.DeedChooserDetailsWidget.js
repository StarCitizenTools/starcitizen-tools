( function ( uw ) {

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

	uw.DeedChooserDetailsWidget.prototype.unload = function () {
		if ( this.deedChooser.deed ) {
			this.deedChooser.deed.unload();
		}
	};

	/**
	 * Toggles whether we use the 'macro' deed or our own
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.DeedChooserDetailsWidget.prototype.useCustomDeedChooser = function ( upload ) {
		var $deedDiv;

		// Defining own deedChooser for uploads coming from external service
		if ( upload.file.fromURL ) {
			// XXX can be made a separate class as mw.UploadFromUrlDeedChooser
			this.deedChooser = upload.deedChooser = {
				deed: {},
				valid: function () {
					return true;
				},
				getSerialized: function () {
					return this.deed ? this.deed.getSerialized() : {};
				},
				setSerialized: function ( serialized ) {
					if ( this.deed.setSerialized ) {
						this.deed.setSerialized( serialized );
					}
				}
			};

			if ( upload.file.license ) {
				// XXX need to add code in the remaining functions
				this.$element.append( document.createTextNode( upload.file.licenseMessage ) );
				this.deedChooser.deed = new uw.deed.Custom( mw.UploadWizard.config, upload );
			} else {
				this.deedChooser.deed = new uw.deed.External(
					mw.UploadWizard.config,
					upload,
					{ type: 'or', licenses: [ 'custom' ], special: 'custom' }
				);
				this.$element.append( this.deedChooser.deed.licenseInputField.$element );
				this.$element.append( document.createTextNode( upload.file.licenseMessage ) );
			}
		} else {
			$deedDiv = $( '<div>' ).addClass( 'mwe-upwiz-custom-deed' );
			this.$element.append( $deedDiv );
			this.deedChooser = upload.deedChooser = new mw.UploadWizardDeedChooser(
				mw.UploadWizard.config,
				$deedDiv,
				mw.UploadWizard.getLicensingDeeds( [ upload ], mw.UploadWizard.config ),
				[ upload ] );
			this.deedChooser.onLayoutReady();
		}
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

	/**
	 * @return {Object}
	 */
	uw.DeedChooserDetailsWidget.prototype.getSerialized = function () {
		if ( this.deedChooser ) {
			return this.deedChooser.getSerialized();
		}

		return {};
	};

	/**
	 * @param {Object} serialized
	 */
	uw.DeedChooserDetailsWidget.prototype.setSerialized = function ( serialized ) {
		if ( this.deedChooser ) {
			this.deedChooser.setSerialized( serialized );
		}
	};

}( mw.uploadWizard ) );
