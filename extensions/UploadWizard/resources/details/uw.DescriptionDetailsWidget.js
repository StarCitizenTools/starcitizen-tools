( function ( mw, uw, $, OO ) {

	/**
	 * A description field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 */
	uw.DescriptionDetailsWidget = function UWDescriptionDetailsWidget() {
		uw.DescriptionDetailsWidget.parent.call( this );

		this.languageDropdown = new OO.ui.DropdownWidget( {
			menu: { items: this.getLanguageDropdownOptions() },
			classes: [ 'mwe-upwiz-desc-lang-select', 'mwe-upwiz-descriptionDetailsWidget-language' ]
		} );
		this.descriptionInput = new OO.ui.TextInputWidget( {
			classes: [ 'mwe-upwiz-desc-lang-text', 'mwe-upwiz-descriptionDetailsWidget-description' ],
			placeholder: mw.message( 'mwe-upwiz-desc-placeholder' ).text(),
			multiline: true,
			autosize: true,
			rows: 2
		} );

		this.languageDropdown.getMenu()
			.selectItemByData( this.constructor.static.getDefaultLanguage() );

		// Aggregate 'change' event
		this.languageDropdown.connect( this, { change: [ 'emit', 'change' ] } );
		// (but do not flash warnings in the user's face while they're typing)
		this.descriptionInput.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

		this.$element.addClass( 'mwe-upwiz-descriptionDetailsWidget' );
		this.$element.append(
			this.languageDropdown.$element,
			this.descriptionInput.$element
		);
	};
	OO.inheritClass( uw.DescriptionDetailsWidget, uw.DetailsWidget );

	/**
	 * Check if the given language code can be used for descriptions.
	 * If not, try finding a similar language code that can be.
	 *
	 * @private
	 * @param {string} code Language code
	 * @param {string} [fallback] Language code to use when there's nothing close,
	 *   defaults to result of #getDefaultLanguage
	 * @return {string|null}
	 */
	uw.DescriptionDetailsWidget.static.getClosestAllowedLanguage = function ( code, fallback ) {
		// Is this still needed?
		if ( code === 'nan' || code === 'minnan' ) {
			code = 'zh-min-nan';
		}
		if ( mw.UploadWizard.config.uwLanguages[ code ] ) {
			return code;
		}
		if ( code.lastIndexOf( '-' ) !== -1 ) {
			return this.getClosestAllowedLanguage( code.substring( 0, code.lastIndexOf( '-' ) ) );
		}
		return arguments.length > 1 ? fallback : this.getDefaultLanguage();
	};

	/**
	 * Get the default language to use for descriptions.
	 * Choose a sane default based on user preferences and wiki config.
	 *
	 * @private
	 * @return {string}
	 */
	uw.DescriptionDetailsWidget.static.getDefaultLanguage = function () {
		var defaultLanguage;

		if ( this.defaultLanguage !== undefined ) {
			return this.defaultLanguage;
		}

		if ( this.getClosestAllowedLanguage( mw.config.get( 'wgUserLanguage' ), null ) ) {
			defaultLanguage = this.getClosestAllowedLanguage( mw.config.get( 'wgUserLanguage' ) );
		} else if ( this.getClosestAllowedLanguage( mw.config.get( 'wgContentLanguage' ), null ) ) {
			defaultLanguage = this.getClosestAllowedLanguage( mw.config.get( 'wgContentLanguage' ) );
		} else if ( this.getClosestAllowedLanguage( 'en', null ) ) {
			defaultLanguage = this.getClosestAllowedLanguage( 'en' );
		} else {
			defaultLanguage = Object.keys( mw.UploadWizard.config.uwLanguages )[ 0 ];
		}

		// Logic copied from MediaWiki:UploadForm.js
		// Per request from Portuguese and Brazilian users, treat Brazilian Portuguese as Portuguese.
		if ( defaultLanguage === 'pt-br' ) {
			defaultLanguage = 'pt';
		// this was also in UploadForm.js, but without the heartwarming justification
		} else if ( defaultLanguage === 'en-gb' ) {
			defaultLanguage = 'en';
		}

		this.defaultLanguage = defaultLanguage;
		return defaultLanguage;
	};

	/**
	 * Get options for the dropdown list of all allowed languages.
	 *
	 * @private
	 * @return {OO.ui.MenuOptionWidget[]}
	 */
	uw.DescriptionDetailsWidget.prototype.getLanguageDropdownOptions = function () {
		var options, code, language;

		options = [];
		for ( code in mw.UploadWizard.config.uwLanguages ) {
			language = mw.UploadWizard.config.uwLanguages[ code ];
			options.push( new OO.ui.MenuOptionWidget( {
				data: code,
				label: language
			} ) );
		}
		return options;
	};

	/**
	 * @inheritdoc
	 */
	uw.DescriptionDetailsWidget.prototype.getErrors = function () {
		var
			errors = [],
			minLength = mw.UploadWizard.config.minDescriptionLength,
			maxLength = mw.UploadWizard.config.maxDescriptionLength,
			descriptionText = this.descriptionInput.getValue().trim();

		if ( descriptionText.length !== 0 && descriptionText.length < minLength ) {
			// Empty description is allowed
			errors.push( mw.message( 'mwe-upwiz-error-too-short', minLength ) );
		}
		if ( descriptionText.length > maxLength ) {
			errors.push( mw.message( 'mwe-upwiz-error-too-long', maxLength ) );
		}

		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.DescriptionDetailsWidget.prototype.getWikiText = function () {
		var
			language = this.languageDropdown.getMenu().getSelectedItem().getData(),
			description = this.descriptionInput.getValue().trim();

		if ( !description ) {
			return '';
		}

		if ( mw.UploadWizard.config.languageTemplateFixups[ language ] ) {
			language = mw.UploadWizard.config.languageTemplateFixups[ language ];
		}

		return description ;
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.DescriptionDetailsWidget.prototype.getSerialized = function () {
		return {
			language: this.languageDropdown.getMenu().getSelectedItem().getData(),
			description: this.descriptionInput.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {string} serialized.language Description language code
	 * @param {string} serialized.description Description text
	 */
	uw.DescriptionDetailsWidget.prototype.setSerialized = function ( serialized ) {
		this.languageDropdown.getMenu().selectItemByData( serialized.language );
		this.descriptionInput.setValue( serialized.description );
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
