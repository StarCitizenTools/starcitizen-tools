( function ( mw, uw, $ ) {

	/**
	 * Interface widget to choose among various deeds -- for instance, if own work, or not own work, or other such cases.
	 *
	 * @param {Object} config The UW config
	 * @param {string|jQuery} selector where to put this deed chooser
	 * @param {UploadWizardDeed[]} deeds
	 * @param {UploadWizardUpload[]} uploads that this applies to (this is just to make deleting and plurals work)
	 */
	mw.UploadWizardDeedChooser = function ( config, selector, deeds, uploads ) {
		var chooser = this;
		this.$selector = $( selector );
		this.uploads = uploads;
		this.deeds = deeds;

		// name for radio button set
		mw.UploadWizardDeedChooser.prototype.widgetCount++;
		this.name = 'deedChooser' + mw.UploadWizardDeedChooser.prototype.widgetCount.toString();

		this.onLayoutReady = function () {};

		$.each( this.deeds, function ( i, deed ) {
			var id = chooser.name + '-' + deed.name,
				$deedInterface = $(
					'<div class="mwe-upwiz-deed mwe-upwiz-deed-' + deed.name + '">' +
						'<div class="mwe-upwiz-deed-option-title">' +
							'<span class="mwe-upwiz-deed-header">' +
								'<input id="' + id + '" name="' + chooser.name + '" type="radio" value="' + deed.name + ' /">' +
								'<label for="' + id + '" class="mwe-upwiz-deed-name">' +
									mw.message( 'mwe-upwiz-source-' + deed.name, chooser.uploads.length ).escaped() +
								'</label>' +
							'</span>' +
						'</div>' +
						'<div class="mwe-upwiz-deed-form"></div>' +
					'</div>'
				);

			chooser.$selector.append( $deedInterface );

			deed.setFormFields( $deedInterface.find( '.mwe-upwiz-deed-form' ) );

			if ( deeds.length === 1 ) {
				chooser.onLayoutReady = chooser.selectDeed.bind( chooser, deed );
			} else {
				if ( config.licensing.defaultType === deed.name ) {
					chooser.onLayoutReady = chooser.selectDeed.bind( chooser, deed );
				}
				$deedInterface.find( 'span.mwe-upwiz-deed-header input' ).click( function () {
					if ( $( this ).is( ':checked' ) ) {
						chooser.selectDeed( deed );
					}
				} );
			}
		} );

		// deselect all deeds
		this.deselectDeedInterface( this.$selector.find( '.mwe-upwiz-deed' ) );
	};

	mw.UploadWizardDeedChooser.prototype = {
		/**
		 * How many deed choosers there are (important for creating unique ids, element names)
		 */
		widgetCount: 0,

		/**
		 * Check if this form is filled out correctly.
		 *
		 * @return {boolean} true if valid, false if not
		 */
		valid: function () {
			return !!this.deed;
		},

		/**
		 * Uploads this deed controls
		 */
		uploads: [],

		selectDeed: function ( deed ) {
			var $deedInterface = this.$selector.find( '.mwe-upwiz-deed.mwe-upwiz-deed-' + deed.name );

			this.choose( deed );
			this.selectDeedInterface( $deedInterface );
			$deedInterface.find( 'span.mwe-upwiz-deed-header input' ).prop( 'checked', true );
		},

		choose: function ( deed ) {
			var chooser = this;

			this.deed = deed;

			$.each( this.uploads, function ( i, upload ) {
				upload.deedChooser = chooser;
			} );

			$( '#mwe-upwiz-stepdiv-deeds .mwe-upwiz-button-next' ).show();
		},

		/**
		 * From the deed choices, make a choice fade to the background a bit, hide the extended form
		 *
		 * @param {jQuery} $deedSelector
		 */
		deselectDeedInterface: function ( $deedSelector ) {
			$deedSelector.removeClass( 'selected' );
			$.each( $deedSelector.find( '.mwe-upwiz-deed-form' ), function ( i, form ) {
				var $form = $( form );
				// Prevent validation of deselected deeds by disabling all form inputs
				$form.find( ':input' ).prop( 'disabled', true );
				if ( $form.parents().is( ':hidden' ) ) {
					$form.hide();
				} else {
					$form.slideUp( 500 );
				}
			} );
		},

		/**
		 * From the deed choice page, show a particular deed
		 *
		 * @param {jQuery} $deedSelector
		 */
		selectDeedInterface: function ( $deedSelector ) {
			var $otherDeeds = $deedSelector.siblings().filter( '.mwe-upwiz-deed' );
			this.deselectDeedInterface( $otherDeeds );
			$deedSelector.addClass( 'selected' ).fadeTo( 'fast', 1.0 );
			$.each( $deedSelector.find( '.mwe-upwiz-deed-form' ), function ( i, form ) {
				var $form = $( form );
				// (Re-)enable all form inputs
				$form.find( ':input' ).prop( 'disabled', false );
				if ( $form.is( ':hidden' ) ) {
					// if the form was hidden, set things up so a slide-down works
					$form.show().slideUp( 0 );
				}
				$form.slideDown( 500 );
			} );
		},

		remove: function () {
			this.$selector.html( '' );
		},

		/**
		 * @return {Object}
		 */
		getSerialized: function () {
			return this.valid() ? this.deed.getSerialized() : {};
		},

		/**
		 * @param {Object} serialized
		 */
		setSerialized: function ( serialized ) {
			var deed;

			if ( serialized.name && serialized.name in this.deeds ) {
				deed = this.deeds[ serialized.name ];
				deed.setSerialized( serialized );
				this.selectDeed( deed );
			}
		}

	};

}( mediaWiki, mediaWiki.uploadWizard, jQuery ) );
