( function ( uw ) {

	/**
	 * A ULS within a description field in UploadWizard's "Details" step form.
	 *
	 * @constructor
	 * @param {Object} [config]
	 * @cfg {Object} [languages] Keys are 2-letter language codes, values are language autonyms
	 * @cfg {Array} [classes] Classes to apply to the ULS container div
	 */
	uw.UlsWidget = function UWUlsWidget( config ) {
		var i;

		uw.UlsWidget.parent.call( this );

		this.$element = $( '<div>' )
			.append(
				$( '<span>' )
					.attr( 'tabindex', 0 )
					.addClass( 'oo-ui-dropdownWidget-handle' )
					.addClass( 'oo-ui-widget' )
					.addClass( 'oo-ui-indicatorElement' )
					.append(
						$( '<span>' ).addClass( 'oo-ui-labelElement-label' ),
						$( '<span>' )
							.addClass( 'oo-ui-indicatorElement-indicator' )
							.addClass( 'oo-ui-indicator-down' )
					)
			)
			.addClass( 'oo-ui-dropdownWidget' )
			.addClass( 'oo-ui-widget-enabled' );
		for ( i = 0; i < config.classes.length; i++ ) {
			this.$element.addClass( config.classes[ i ] );
		}

		// Show the ULS when a user tabs into the language selection field
		this.$element.find( '.oo-ui-dropdownWidget-handle' ).on( 'keyup', function ( e ) {
			if ( e.key === 'Tab' ) {
				$( this ).trigger( 'click' );
			}
		} );

		if ( mw.loader.getState( 'ext.uls.mediawiki' ) === 'ready' ) {
			this.initialiseUls( config.languages );
		}
	};
	OO.inheritClass( uw.UlsWidget, OO.ui.Widget );
	OO.mixinClass( uw.UlsWidget, OO.EventEmitter );

	uw.UlsWidget.prototype.initialiseUls = function ( languages ) {
		var ulsWidget = this;

		this.languages = languages;

		this.uls = this.$element.uls( {
			onSelect: function ( language ) {
				ulsWidget.setValue( language );
				ulsWidget.$element.parent().find( '.oo-ui-inputWidget-input' ).trigger( 'focus' );
			},
			languages: languages,
			ulsPurpose: 'upload-wizard-description',
			onVisible: function () {
				// Re-position the ULS *after* the widget has been rendered, so that we can be
				// sure it's in the right place
				var offset = ulsWidget.$element.offset();
				if ( this.$menu.css( 'direction' ) === 'rtl' ) {
					offset.left =
						offset.left - parseInt( this.$menu.css( 'width' ) ) + ulsWidget.$element.width();
				}
				this.$menu.css( offset );
			}
		} );
	};

	/**
	 * @param {Object} languages
	 */
	uw.UlsWidget.prototype.updateLanguages = function ( languages ) {
		this.uls.off().removeData( 'uls' );
		this.initialiseUls( languages );
	};

	/**
	 * @param {string} value
	 */
	uw.UlsWidget.prototype.setValue = function ( value ) {
		var current = this.languageValue;
		this.languageValue = value;
		this.$element.find( '.oo-ui-labelElement-label' ).text( this.languages[ value ] );
		if ( current !== value ) {
			this.emit( 'select' );
		}
	};

	/**
	 * @return {string}
	 */
	uw.UlsWidget.prototype.getValue = function () {
		return this.languageValue;
	};

	/**
	 * @return {uw.UlsWidget}
	 */
	uw.UlsWidget.prototype.getElement = function () {
		return this.$element;
	};

}( mw.uploadWizard ) );
