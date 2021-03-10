( function ( uw ) {

	/**
	 * A multi-language input field in UploadWizard's "Details" step form.
	 *
	 * @class uw.MultipleLanguageInputWidget
	 * @extends uw.DetailsWidget
	 * @mixins OO.ui.mixin.GroupElement
	 * @constructor
	 * @param {Object} [config]
	 * @cfg {boolean} [required=true]
	 * @cfg {mw.Message} [label] Text for label
	 * @cfg {mw.Message} [placeholder] Placeholder text for input field
	 * @cfg {mw.Message} [remove] Title text for remove icon
	 * @cfg {mw.Message} [error] Error message
	 * @cfg {number} [minLength=0] Minimum input length
	 * @cfg {number} [maxLength=99999] Maximum input length
	 * @cfg {Object} [languages] { langcode: text } map of languages
	 */
	uw.MultipleLanguageInputWidget = function UWMultipleLanguageInputWidget( config ) {
		this.config = $.extend( {
			required: true,
			label: mw.message( '' ),
			languages: this.getLanguageOptions()
		}, config );
		uw.MultipleLanguageInputWidget.parent.call( this );
		OO.ui.mixin.GroupElement.call( this );

		this.required = !!this.config.required;
		this.addButton = new OO.ui.ButtonWidget( {
			classes: [ 'mwe-upwiz-multipleLanguageInputWidget-addItem' ],
			framed: true,
			icon: 'add',
			flags: [ 'progressive' ],
			label: this.getLabelText()
		} );
		this.addButton.connect( this, { click: [ 'addLanguageInput', this.config ] } );

		// if a language becomes available because the input gets removed,
		// or unavailable because it gets added, we'll need to update other
		// language dropdowns to reflect the change
		this.connect( this, { add: 'onChangeLanguages' } );
		this.connect( this, { remove: 'onChangeLanguages' } );

		// update the 'add language' button accordingly
		this.connect( this, { add: 'recount' } );
		this.connect( this, { remove: 'recount' } );

		// Aggregate 'change' event
		this.aggregate( { change: 'change' } );

		this.$element.addClass( 'mwe-upwiz-multipleLanguageInputsWidget' );
		this.$element.append(
			this.$group,
			this.addButton.$element
		);

		// Add empty input (non-removable if this field is required)
		this.addLanguageInput( $.extend( {}, this.config, { canBeRemoved: !this.required } ) );
	};
	OO.inheritClass( uw.MultipleLanguageInputWidget, uw.DetailsWidget );
	OO.mixinClass( uw.MultipleLanguageInputWidget, OO.ui.mixin.GroupElement );

	/**
	 * @param {Object} config
	 * @param {string} [text]
	 */
	uw.MultipleLanguageInputWidget.prototype.addLanguageInput = function ( config, text ) {
		var allLanguages = this.config.languages,
			unusedLanguages = this.getUnusedLanguages(),
			languages = {},
			item;

		if ( unusedLanguages.length === 0 ) {
			return;
		}

		// only add given language + unused/remaining languages - we don't want
		// languages that have already been selected to show up in the next dropdown...
		if ( config.defaultLanguage ) {
			languages[ config.defaultLanguage ] = allLanguages[ config.defaultLanguage ];
			languages = $.extend( {}, languages, unusedLanguages );
		} else {
			languages = unusedLanguages;
		}

		config = $.extend( {}, config, { languages: languages } );
		item = new uw.SingleLanguageInputWidget( config );
		item.setText( text || '' );

		// if a language is changed, we'll need to update other language dropdowns
		// to reflect the change
		item.connect( this, { select: 'onChangeLanguages' } );

		this.addItems( [ item ] );
	};

	/**
	 * When a language changes (or an input is removed), the old language
	 * becomes available again in other language dropdowns, and the new
	 * language should no longer be selected.
	 * This will iterate all inputs, destroy then, and construct new ones
	 * with the updated language selections.
	 */
	uw.MultipleLanguageInputWidget.prototype.onChangeLanguages = function () {
		var allLanguages = this.config.languages,
			unusedLanguages = this.getUnusedLanguages(),
			items = this.getItems(),
			languages,
			item,
			i;

		for ( i = 0; i < items.length; i++ ) {
			item = items[ i ];

			// only add existing language + unused/remaining languages - we don't want
			// languages that have already been selected to show up in the next dropdown...
			languages = {};
			languages[ item.getLanguage() ] = allLanguages[ item.getLanguage() ];
			languages = $.extend( {}, languages, unusedLanguages );
			item.updateLanguages( languages );
		}
	};

	/**
	 * Returns an object of `langcode: text` pairs with the languages
	 * already used in dropdowns.
	 *
	 * @return {Object}
	 */
	uw.MultipleLanguageInputWidget.prototype.getUsedLanguages = function () {
		var allLanguages = this.config.languages,
			items = this.getItems();

		return items.reduce( function ( obj, item ) {
			var languageCode = item.getLanguage();
			obj[ languageCode ] = allLanguages[ languageCode ];
			return obj;
		}, {} );
	};

	/**
	 * Returns an object of `langcode: text` pairs with remaining languages
	 * not yet used in dropdowns.
	 *
	 * @return {Object}
	 */
	uw.MultipleLanguageInputWidget.prototype.getUnusedLanguages = function () {
		var allLanguages = this.config.languages,
			usedLanguageCodes = Object.keys( this.getUsedLanguages() );

		return Object.keys( allLanguages ).reduce( function ( remaining, language ) {
			if ( usedLanguageCodes.indexOf( language ) < 0 ) {
				remaining[ language ] = allLanguages[ language ];
			}
			return remaining;
		}, {} );
	};

	/**
	 * Update the button label after adding or removing inputs.
	 */
	uw.MultipleLanguageInputWidget.prototype.recount = function () {
		var text = this.getLabelText(),
			unusedLanguages = this.getUnusedLanguages();

		this.addButton.setLabel( text );
		// hide the button if there are no remaining languages...
		this.addButton.toggle( Object.keys( unusedLanguages ).length > 0 );
	};

	/**
	 * @return {string}
	 */
	uw.MultipleLanguageInputWidget.prototype.getLabelText = function () {
		var text = '', msg;
		if ( this.config.label.exists() ) {
			// clone the original object: `.params` doesn't replace existing
			// params so follow-up calls here would otherwise just keep adding
			// to the params instead of setting a new value for the first param
			msg = mw.message( this.config.label.key ).params( this.config.label.parameters );
			text = msg.params( [ this.items.length ] ).text();
		}

		return text;
	};

	/**
	 * @return {Object}
	 */
	uw.MultipleLanguageInputWidget.prototype.getLanguageOptions = function () {
		var languages, code;

		languages = {};
		for ( code in mw.UploadWizard.config.uwLanguages ) {
			if ( Object.prototype.hasOwnProperty.call( mw.UploadWizard.config.uwLanguages, code ) ) {
				languages[ code ] = mw.UploadWizard.config.uwLanguages[ code ];
			}
		}
		return languages;
	};

	/**
	 * @inheritdoc
	 */
	uw.MultipleLanguageInputWidget.prototype.getErrors = function () {
		var self = this,
			// Gather errors from each item
			errorPromises = this.getItems().map( function ( item ) {
				return item.getErrors();
			} );

		return $.when.apply( $, errorPromises ).then( function () {
			var i, errors;
			errors = [];
			// Fold all errors into a single one (they are displayed in the UI for each item, but we still
			// need to return an error here to prevent form submission).
			for ( i = 0; i < arguments.length; i++ ) {
				if ( arguments[ i ].length ) {
					// One of the items has errors
					errors.push( self.config.error );
					break;
				}
			}
			// And add some more:
			if ( this.required && this.getWikiText() === '' ) {
				errors.push( mw.message( 'mwe-upwiz-error-blank' ) );
			}
			// TODO Check for duplicate languages
			return errors;
		}.bind( this ) );
	};

	/**
	 * @return {Object} Object where the properties are language codes & values are input
	 */
	uw.MultipleLanguageInputWidget.prototype.getValues = function () {
		var values = {},
			widgets = this.getItems(),
			language,
			text,
			i;

		for ( i = 0; i < widgets.length; i++ ) {
			language = widgets[ i ].getLanguage();
			text = widgets[ i ].getText();

			if ( text !== '' ) {
				values[ language ] = text;
			}
		}

		return values;
	};

	/**
	 * @inheritdoc
	 */
	uw.MultipleLanguageInputWidget.prototype.getWikiText = function () {
		// Some code here and in mw.UploadWizardDetails relies on this function returning an empty
		// string when there are some inputs, but all are empty.
		return this.getItems().map( function ( widget ) {
			return widget.getWikiText();
		} ).filter( function ( wikiText ) {
			return !!wikiText;
		} ).join( '\n' );
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.MultipleLanguageInputWidget.prototype.getSerialized = function () {
		var inputs = this.getItems().map( function ( widget ) {
			return widget.getSerialized();
		} );
		return {
			inputs: inputs
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {Object[]} serialized.inputs Array of serialized inputs,
	 *   see uw.SingleLanguageInputWidget#setSerialized
	 */
	uw.MultipleLanguageInputWidget.prototype.setSerialized = function ( serialized ) {
		var config = this.config,
			i;

		// remove all existing
		this.removeItems( this.getItems() );

		for ( i = 0; i < serialized.inputs.length; i++ ) {
			config = $.extend( {}, config, { defaultLanguage: serialized.inputs[ i ].language } );
			this.addLanguageInput( config, serialized.inputs[ i ].text );
		}
	};

}( mw.uploadWizard ) );
