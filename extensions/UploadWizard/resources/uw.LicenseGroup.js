( function ( uw ) {

	/**
	 * @extends OO.ui.LicenseGroup
	 *
	 * @constructor
	 * @inheritdoc
	 * @param {Object} config License configuration
	 * @param {Array} config.licenses Array of license names
	 * @param {string} [config.head] Header for the group of licenses (if present, the group of
	 *   licenses will be collapsed and this will be the clickable title to expand the group)
	 * @param {string} [config.subhead] Subtitle for the group of licenses
	 * @param {string} [config.special] 'custom' if a text input field should be added
	 * @param {string} [config.template] Filter templates. If 'filterTemplate' was 'filter',
	 *   then  [ 'fooLicense', 'barLicense' ] -> {{filter|fooLicense|barLicense}}
	 * @param {Array} [config.prependTemplates] Array of templates to prepend. If prependTemplates
	 *   were [ 'pre', 'pended' ], then [ 'fooLicense' ] -> "{{pre}}{{pended}}{{fooLicense}}"
	 * @param {string} type 'radio' or 'checkbox'
	 * @param {mw.Api} api API object, used for wikitext previews
	 * @param {number} count Number of the things we are licensing (it matters to some texts)
	 */
	uw.LicenseGroup = function UWLicenseGroup( config, type, api, count ) {
		var self = this;

		uw.LicenseGroup.parent.call( this, {} );

		if ( typeof config.licenses !== 'object' ) {
			throw new Error( 'improper license config' );
		}

		if ( [ 'radio', 'checkbox' ].indexOf( type ) < 0 ) {
			throw new Error( 'Invalid type: ' + type );
		}

		this.config = config;
		this.type = type;
		this.api = api;
		this.count = count;
		this.collapsible = !!config.head;
		this.textareas = {};
		this.previewDialog = new uw.LicensePreviewDialog();
		this.windowManager = new OO.ui.WindowManager();
		this.windowManager.addWindows( [ this.previewDialog ] );
		$( document.body ).append( this.windowManager.$element );

		if ( this.type === 'radio' ) {
			this.group = this.createRadioGroup( [ 'mwe-upwiz-deed-license-group-body' ] );
			this.group.connect( this, { choose: [ 'emit', 'change', this ] } );
		} else if ( this.type === 'checkbox' ) {
			this.group = this.createCheckboxGroup( [ 'mwe-upwiz-deed-license-group-body' ] );
			this.group.connect( this, { select: [ 'emit', 'change', this ] } );
		}

		// when selecting an item that has a custom textarea, we'll immediately focus it
		this.on( 'change', function ( group, item ) {
			if ( item && item.isSelected && item.isSelected() ) {
				// wrapped inside setTimeout to ensure it goes at the end of the call stack,
				// just in case something steals focus in the meantime...
				setTimeout( function () {
					var name = item.getData();
					if ( self.textareas[ name ] ) {
						self.textareas[ name ].focus();
					}
				} );
			}
		} );

		this.fieldset = this.createFieldset( this.group );
		this.$element = this.fieldset.$element;
	};
	OO.inheritClass( uw.LicenseGroup, OO.ui.Widget );

	uw.LicenseGroup.prototype.unload = function () {
		this.windowManager.$element.remove();
	};

	/**
	 * @param {OO.ui.RadioSelectWidget|OO.ui.CheckboxMultiselectInputWidget} group
	 * @return {OO.ui.FieldsetLayout}
	 */
	uw.LicenseGroup.prototype.createFieldset = function ( group ) {
		var $head = this.config.head && $( '<a>' )
				.addClass( 'mwe-upwiz-deed-license-group-head mw-collapsible-arrow' )
				.msg( this.config.head, this.count ),
			$subhead = this.config.subhead && $( '<div>' )
				.addClass( 'mwe-upwiz-deed-license-group-subhead' )
				.msg( this.config.subhead, this.count ),
			fieldset = new OO.ui.FieldsetLayout( {
				label: $head,
				items: [ group ],
				classes: [ 'mwe-upwiz-deed-license-group' ]
			} );

		if ( this.collapsible ) {
			fieldset.$group.makeCollapsible( { collapsed: true, $customTogglers: $head, toggleClasses: true } );
		}
		if ( this.config.subhead ) {
			fieldset.addItems(
				[ new OO.ui.FieldLayout( new OO.ui.Widget( { content: [] } ), { label: $subhead, align: 'top' } ) ],
				0 // = index; add to top
			);
		}

		return fieldset;
	};

	/**
	 * @param {Array} classes to add
	 * @return {OO.ui.RadioSelectWidget}
	 */
	uw.LicenseGroup.prototype.createRadioGroup = function ( classes ) {
		var self = this,
			options = [];

		this.config.licenses.forEach( function ( licenseName ) {
			var option;

			if ( mw.UploadWizard.config.licenses[ licenseName ] === undefined ) {
				// unknown license
				return;
			}

			option = new OO.ui.RadioOptionWidget( {
				label: self.createLabel( licenseName ),
				data: licenseName
			} );

			// when custom text area receives focus, we should make sure this element is selected
			if ( self.textareas[ licenseName ] ) {
				self.textareas[ licenseName ].on( 'focus', function () {
					option.setSelected( true );
				} );
			}

			options.push( option );
		} );

		return new OO.ui.RadioSelectWidget( { items: options, classes: classes } );
	};

	/**
	 * @param {Array} classes to add
	 * @return {OO.ui.CheckboxMultiselectInputWidget}
	 */
	uw.LicenseGroup.prototype.createCheckboxGroup = function ( classes ) {
		var self = this,
			options = [];

		this.config.licenses.forEach( function ( licenseName ) {
			var option;

			if ( mw.UploadWizard.config.licenses[ licenseName ] === undefined ) {
				// unknown license
				return;
			}

			option = new OO.ui.CheckboxMultioptionWidget( {
				label: self.createLabel( licenseName ),
				data: licenseName
			} );

			// when custom text area receives focus, we should make sure this element is selected
			if ( self.textareas[ licenseName ] ) {
				self.textareas[ licenseName ].on( 'focus', function () {
					option.setSelected( true );
				} );
			}

			options.push( option );
		} );

		return new OO.ui.CheckboxMultiselectWidget( { items: options, classes: classes } );
	};

	/**
	 * @return {string}
	 */
	uw.LicenseGroup.prototype.getWikiText = function () {
		var wikiTexts,
			self = this,
			values = this.getValue();

		wikiTexts = Object.keys( values ).map( function ( name ) {
			var wikiText = self.getLicenceWikiText( name ),
				value = values[ name ];
			if ( typeof value === 'string' ) {
				// `value` is custom input
				wikiText += '\n' + value.trim();
			}
			return wikiText;
		} );

		return wikiTexts.join( '' ).trim();
	};

	/**
	 * Returns a string unique to the group (if defined)
	 *
	 * @return {string}
	 */
	uw.LicenseGroup.prototype.getGroup = function () {
		return this.config.head || '';
	};

	/**
	 * @return {Object} Map of { licenseName: true }, or { licenseName: "custom input" }
	 */
	uw.LicenseGroup.prototype.getValue = function () {
		var self = this,
			result = {},
			selected,
			name;

		if ( this.type === 'radio' ) {
			selected = this.group.findSelectedItem();
			if ( selected ) {
				name = selected.getData();
				result[ name ] = !this.textareas[ name ] || this.textareas[ name ].getValue();
			}
		} else if ( this.type === 'checkbox' ) {
			selected = this.group.findSelectedItems();
			selected.forEach( function ( item ) {
				name = item.getData();
				result[ name ] = !self.textareas[ name ] || self.textareas[ name ].getValue();
			} );
		}

		return result;
	};

	/**
	 * @param {Object} values Map of { licenseName: true }, or { licenseName: "custom input" }
	 */
	uw.LicenseGroup.prototype.setValue = function ( values ) {
		var self = this,
			selectArray = [],
			selected;

		Object.keys( values ).forEach( function ( name ) {
			var value = values[ name ];
			if ( typeof value === 'string' && self.textareas[ name ] ) {
				self.textareas[ name ].setValue( value );
				// add to list of items to select
				selectArray.push( name );
			}

			// add to list of items to select
			// (only true/string values should be included in `values`, but might
			// as well play it safe...)
			if ( value === true ) {
				selectArray.push( name );
			}
		} );

		if ( this.type === 'radio' ) {
			this.group.selectItemByData( selectArray[ 0 ] );
			selected = this.group.findSelectedItem() !== null;
		} else if ( this.type === 'checkbox' ) {
			this.group.selectItemsByData( selectArray );
			selected = this.group.findSelectedItems().length > 0;
		}

		// pop open the 'toggle' group if is now on. Do nothing if it is now off.
		if ( selected && this.collapsible ) {
			this.fieldset.$group.data( 'mw-collapsible' ).expand();
		}
	};

	/**
	 * @private
	 * @param {string} name
	 * @return {Object}
	 */
	uw.LicenseGroup.prototype.getLicenseInfo = function ( name ) {
		return {
			name: name,
			props: mw.UploadWizard.config.licenses[ name ]
		};
	};

	/**
	 * @private
	 * @param {string} name
	 * @return {string[]}
	 */
	uw.LicenseGroup.prototype.getTemplates = function ( name ) {
		var licenseInfo = this.getLicenseInfo( name );
		return licenseInfo.props.templates === undefined ?
			[ licenseInfo.name ] :
			licenseInfo.props.templates.slice( 0 );
	};

	/**
	 * License templates are these abstract ideas like cc-by-sa. In general they map directly to a license template.
	 * However, configuration for a particular option can add other templates or transform the templates,
	 * such as wrapping templates in an outer "self" template for own-work
	 *
	 * @private
	 * @param {string} name license template name
	 * @return {string} of wikitext
	 */
	uw.LicenseGroup.prototype.getLicenceWikiText = function ( name ) {
		var templates = this.getTemplates( name ),
			wikiTexts;

		if ( this.config.prependTemplates !== undefined ) {
			this.config.prependTemplates.forEach( function ( template ) {
				templates.unshift( template );
			} );
		}

		if ( this.config.template !== undefined ) {
			templates.unshift( this.config.template );
			templates = [ templates.join( '|' ) ];
		}

		wikiTexts = templates.map( function ( t ) {
			return '{{' + t + '}}';
		} );
		return wikiTexts.join( '' );
	};

	/**
	 * Get a label for the form element
	 *
	 * @private
	 * @param {string} name license template name
	 * @return {jQuery}
	 */
	uw.LicenseGroup.prototype.createLabel = function ( name ) {
		var licenseInfo = this.getLicenseInfo( name ),
			messageKey = licenseInfo.props.msg === undefined ?
				'[missing msg for ' + licenseInfo.name + ']' :
				licenseInfo.props.msg,
			languageCode = mw.config.get( 'wgUserLanguage' ),
			// The URL is optional, but if the message includes it as $2, we surface the fact
			// that it's missing.
			licenseURL = licenseInfo.props.url === undefined ? '#missing license URL' : licenseInfo.props.url,
			$licenseLink,
			$icons = $( '<span>' ),
			$label;

		if ( licenseInfo.props.languageCodePrefix !== undefined ) {
			licenseURL += licenseInfo.props.languageCodePrefix + languageCode;
		}
		$licenseLink = $( '<a>' ).attr( { target: '_blank', href: licenseURL } );
		if ( licenseInfo.props.icons !== undefined ) {
			licenseInfo.props.icons.forEach( function ( icon ) {
				$icons.append( $( '<span>' ).addClass( 'mwe-upwiz-license-icon mwe-upwiz-' + icon + '-icon' ) );
			} );
		}

		$label = $( '<label>' )
			.msg( messageKey, this.count || 0, $licenseLink )
			.append( $icons ).addClass( 'mwe-upwiz-copyright-info' );

		if ( this.config.special === 'custom' ) {
			$label.append( this.createCustom( name, licenseInfo.props.defaultText ) );
		}

		return $label.contents();
	};

	/**
	 * @private
	 * @param {string} name license name
	 * @param {string} [defaultText] Default custom license text
	 * @return {jQuery} Wrapped textarea
	 */
	uw.LicenseGroup.prototype.createCustom = function ( name, defaultText ) {
		var self = this,
			button;

		this.textareas[ name ] = new OO.ui.MultilineTextInputWidget( {
			value: defaultText,
			autosize: true
		} );

		// Update displayed errors as the user is typing
		this.textareas[ name ].on( 'change', OO.ui.debounce( this.emit.bind( this, 'change', this ), 500 ) );

		button = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-license-custom-preview' ).text(),
			flags: [ 'progressive' ]
		} ).on( 'click', function () {
			self.showPreview( self.textareas[ name ].getValue() );
		} );

		return $( '<div>' ).addClass( 'mwe-upwiz-license-custom' ).append(
			button.$element,
			this.textareas[ name ].$element
		);
	};

	/**
	 * Preview wikitext in a popup window
	 *
	 * @private
	 * @param {string} wikiText
	 */
	uw.LicenseGroup.prototype.showPreview = function ( wikiText ) {
		var input;

		this.previewDialog.setLoading( true );
		this.windowManager.openWindow( this.previewDialog );

		input = this;

		function show( html ) {
			input.previewDialog.setPreview( html );
			input.windowManager.openWindow( input.previewDialog );
		}

		function error( code, result ) {
			var message = result.errors[ 0 ].html;

			uw.eventFlowLogger.logError( 'license', { code: code, message: message } );
			show( $( '<div>' ).append(
				$( '<h3>' ).append( code ),
				$( '<p>' ).append( message )
			) );
		}

		this.api.parse( wikiText, { pst: true } ).done( show ).fail( error );
	};

}( mw.uploadWizard ) );
