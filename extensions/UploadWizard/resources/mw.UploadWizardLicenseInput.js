( function ( mw, uw, $, OO ) {
	function LicensePreviewDialog( config ) {
		LicensePreviewDialog.parent.call( this, config );
	}

	OO.inheritClass( LicensePreviewDialog, OO.ui.Dialog );

	LicensePreviewDialog.static.name = 'licensePreviewDialog';

	LicensePreviewDialog.prototype.initialize = function () {
		var dialog = this;

		LicensePreviewDialog.parent.prototype.initialize.call( this );

		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		this.$body.append( this.content.$element );
		this.$spinner = $.createSpinner( { size: 'large', type: 'block' } )
			.css( { width: 200, padding: 20, 'float': 'none', margin: '0 auto' } );

		$( 'body' ).on( 'click', function ( e ) {
			if ( !$.contains( dialog.$body.get( 0 ), e.target ) ) {
				dialog.close();
			}
		} );
	};

	LicensePreviewDialog.prototype.addCloseButton = function () {
		var dialog = this,
			closeButton = new OO.ui.ButtonWidget( {
				label: OO.ui.msg( 'ooui-dialog-process-dismiss' )
			} );

		closeButton.on( 'click', function () {
			dialog.close();
		} );

		this.content.$element.append( closeButton.$element );
	};

	LicensePreviewDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	LicensePreviewDialog.prototype.setLoading = function ( isLoading ) {
		if ( isLoading ) {
			this.content.$element.empty().append( this.$spinner );
			this.addCloseButton();
		} else {
			this.content.$element.empty();
		}

		this.updateSize();
	};

	LicensePreviewDialog.prototype.setPreview = function ( html ) {
		this.content.$element.empty().append( html );
		this.addCloseButton();
		this.updateSize();
	};

	/**
	 * Create a group of radio buttons for licenses. N.B. the licenses are named after the templates they invoke.
	 * Note that this is very anti-MVC. The values are held only in the actual form elements themselves.
	 *
	 * @extends OO.ui.Widget
	 * @param {Array|undefined} values License key name(s) to activate by default
	 * @param {Object} config Configuration. Must have following properties:
	 * @param {string} config.type Whether inclusive or exclusive license allowed ("and"|"or")
	 * @param {string[]} config.licenses Template string names (matching keys in mw.UploadWizard.config.licenses)
	 * @param {string[]} [config.licenseGroups] Groups of licenses, with more explanation
	 * @param {string} [config.special] Indicates, don't put licenses here, instead use a special widget
	 * @param {Number} count Number of the things we are licensing (it matters to some texts)
	 * @param {mw.Api} api API object, used for wikitext previews
	 */
	mw.UploadWizardLicenseInput = function ( values, config, count, api ) {
		mw.UploadWizardLicenseInput.parent.call( this );

		this.count = count;

		this.api = api;

		if (
			config.type === undefined ||
			( config.licenses === undefined && config.licenseGroups === undefined )
		) {
			throw new Error( 'improper initialization' );
		}

		this.$selector = this.$element;

		this.type = config.type === 'or' ? 'radio' : 'checkbox';

		this.defaults = [];

		if ( config.defaults ) {
			this.defaults = config.defaults;
		} else if ( config.licenses && config.licenses[ 0 ] ) {
			this.defaults = [ config.licenses[ 0 ] ];
		}

		mw.UploadWizardLicenseInput.prototype.count++;
		this.name = 'license' + mw.UploadWizardLicenseInput.prototype.count;

		// the jquery wrapped inputs (checkboxes or radio buttons) for this licenseInput.
		this.inputs = [];

		// create inputs and licenses from config
		if ( config.licenseGroups === undefined ) {
			this.createInputs( this.$selector, config );
		} else {
			this.createGroupedInputs( this.$selector, config.licenseGroups );
		}

		// set values of the whole license input
		if ( values ) {
			this.setValues( values );
		}

		this.windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( this.windowManager.$element );
		this.previewDialog = new LicensePreviewDialog();
		this.windowManager.addWindows( [ this.previewDialog ] );

		// [wikitext => list of templates used in wikitext] map, used in
		// getUsedTemplates to reduce amount of API calls
		this.templateCache = {};
	};
	OO.inheritClass( mw.UploadWizardLicenseInput, OO.ui.Widget );

	$.extend( mw.UploadWizardLicenseInput.prototype, {
		count: 0,

		/**
		 * Creates the license input interface in toggleable groups.
		 *
		 * @param {jQuery} $el Selector
		 * @param {Object} configGroups License input configuration groups
		 */
		createGroupedInputs: function ( $el, configGroups ) {
			var input = this;
			$.each( configGroups, function ( i, group ) {
				var $body, $head, $licensesDiv,
					$group = $( '<div>' ).addClass( 'mwe-upwiz-deed-license-group' );
				if ( group.head === undefined ) {
					// if there is no header, just append licenses to the group div.
					$body = $group;
				} else {
					// if there is a header, make a toggle-to-expand div and append inputs there.
					$head = $( '<a>' )
						.addClass( 'mwe-upwiz-deed-license-group-head mw-collapsible-toggle mw-collapsible-arrow' )
						.msg( group.head, input.count );
					$body = $( '<div>' ).addClass( 'mwe-upwiz-deed-license-group-body mw-collapsible-content' );
					$group.append( $head, $body ).makeCollapsible( { collapsed: true } );
				}
				if ( group.subhead !== undefined ) {
					$body.append( $( '<div>' ).addClass( 'mwe-upwiz-deed-license-group-subhead' ).msg( group.subhead, input.count ) );
				}
				$licensesDiv = $( '<div>' ).addClass( 'mwe-upwiz-deed-license' );
				input.createInputs( $licensesDiv, group );
				$body.append( $licensesDiv );
				input.$selector.append( $group );
			} );
		},

		/**
		 * append defined license inputs to element.
		 * SIDE EFFECT: also records licenses and inputs in the object
		 *
		 * Abstracts out simple lists of licenses, more complex groups with layout
		 *
		 * @param {jQuery} $el Selector to which to add inputs
		 * @param {Array} config License configuration, which must have a 'licenses' property, which is
		 *   an array of license names. It may also have:
		 *  * 'prependTemplates' or 'template', which alter the final wikitext value
		 *	* 'prependTemplates' will prepend Templates. If prependTemplates were [ 'pre', 'pended' ],
		 *    then [ 'fooLicense' ] -> "{{pre}}{{pended}}{{fooLicense}}"
		 *  * 'template' will filter Templates, as in "own work". If 'filterTemplate' was 'filter',
		 *    then  [ 'fooLicense', 'barLicense' ] -> {{filter|fooLicense|barLicense}}
		 */
		createInputs: function ( $el, config ) {
			var input = this;

			if ( config.licenses === undefined || typeof config.licenses !== 'object' ) {
				throw new Error( 'improper license config' );
			}

			$.each( config.licenses, function ( i, licenseName ) {
				var $customDiv, license, templates, $input, $label, customDefault;

				if ( mw.UploadWizard.config.licenses[ licenseName ] !== undefined ) {
					license = {
						name: licenseName,
						props: mw.UploadWizard.config.licenses[ licenseName ]
					};
					templates = license.props.templates === undefined ?
						[ license.name ] :
						license.props.templates.slice( 0 );
					$input = input.createInputElement( templates, config );
					$label = input.createInputElementLabel( license, $input );

					input.inputs.push( $input );
					$el.append( $input, $label, $( '<br/>' ) );
					// TODO add popup help?
					$input.addClass( 'mwe-upwiz-copyright-info-radio' );
					// this is so we can tell if a particular license ought to be set in setValues()
					$input.data( 'licenseName', licenseName );

					if ( config.special === 'custom' ) {
						customDefault = mw.UploadWizard.config.licenses[ licenseName ].defaultText;
						$customDiv = input.createCustomWikiTextInterface( $input, customDefault );
						$el.append( $customDiv );
						$input.data( 'textarea', $customDiv.find( 'textarea' ) );
					}
				}
			} );
		},

		/**
		 * License templates are these abstract ideas like cc-by-sa. In general they map directly to a license template.
		 * However, configuration for a particular option can add other templates or transform the templates,
		 * such as wrapping templates in an outer "self" template for own-work
		 *
		 * @param {Array} templates Array of license template names
		 * @param {Object} config License input configuration
		 * @return {string} of wikitext
		 */
		createInputValueFromTemplateConfig: function ( templates, config ) {
			var wikiTexts;
			if ( config.prependTemplates !== undefined ) {
				$.each( config.prependTemplates, function ( i, template ) {
					templates.unshift( template );
				} );
			}
			if ( config.template !== undefined ) {
				templates.unshift( config.template );
				templates = [ templates.join( '|' ) ];
			}
			wikiTexts = $.map( templates, function ( t ) { return '{{' + t + '}}'; } );
			return wikiTexts.join( '' );
		},

		/**
		 * Return a radio button or checkbox with appropriate values, depending on config
		 *
		 * @param {Array} templates Array of template strings
		 * @param {Object} config for this license input
		 * @return {jQuery} wrapped input
		 */
		createInputElement: function ( templates, config ) {
			var input = this,

				attrs = {
					id: this.name + '_' + this.inputs.length, // unique id
					name: this.name, // name of input, shared among all checkboxes or radio buttons.
					type: this.type, // kind of input
					value: this.createInputValueFromTemplateConfig( templates, config )
				};

			return $( '<input>' ).attr( attrs ).click( function () {
				input.emit( 'change' );
			} );
		},

		/**
		 * Get a label for the form element
		 *
		 * @param {Object} license License definition from global config. Will tell us the messages, and
		 *   maybe icons.
		 * @param {jQuery} $input Wrapped input
		 * @return {jQuery} wrapped label referring to that input, with appropriate HTML, decorations, etc.
		 */
		createInputElementLabel: function ( license, $input ) {
			var messageKey = license.props.msg === undefined ?
					'[missing msg for ' + license.name + ']' :
					license.props.msg,
				languageCode = mw.config.get( 'wgUserLanguage' ),

				// The URL is optional, but if the message includes it as $2, we surface the fact
				// that it's missing.
				licenseURL = license.props.url === undefined ? '#missing license URL' : license.props.url,

				licenseLink,

				$icons = $( '<span>' );

			if ( license.props.languageCodePrefix !== undefined ) {
				licenseURL += license.props.languageCodePrefix + languageCode;
			}
			licenseLink = $( '<a>' ).attr( { target: '_blank', href: licenseURL } );
			if ( license.props.icons !== undefined ) {
				$.each( license.props.icons, function ( i, icon ) {
					$icons.append( $( '<span>' ).addClass( 'mwe-upwiz-license-icon mwe-upwiz-' + icon + '-icon' ) );
				} );
			}
			return $( '<label />' )
				.attr( { 'for': $input.attr( 'id' ) } )
				.msg( messageKey, this.count || 0, licenseLink )
				.append( $icons ).addClass( 'mwe-upwiz-copyright-info' );
		},

		/**
		 * Given an input, return another textarea to be appended below.
		 * When text entered here, auto-selects the input.
		 *
		 * @param {jQuery} $input Wrapped input
		 * @param {string} [customDefault] Default custom license text
		 * @return {jQuery} Wrapped textarea
		 */
		createCustomWikiTextInterface: function ( $input, customDefault ) {
			var
				input = this,
				nameId = $input.attr( 'id' ) + '_custom',
				textarea, button;

			textarea = new OO.ui.MultilineTextInputWidget( {
				value: customDefault,
				name: nameId,
				autosize: true
			} );
			textarea.$input.attr( 'id', nameId );

			// Select this radio when the user clicks on the text field
			textarea.$input.focus( function () { input.setInput( $input, true ); } );
			// Update displayed errors as the user is typing
			textarea.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

			button = new OO.ui.ButtonWidget( {
				label: mw.message( 'mwe-upwiz-license-custom-preview' ).text(),
				flags: [ 'progressive' ]
			} ).on( 'click', function () {
				input.showPreview( textarea.getValue() );
			} );

			return $( '<div>' ).addClass( 'mwe-upwiz-license-custom' ).append(
				button.$element,
				textarea.$element
			);
		},

		/* ---- end creational stuff ----- */

		// Set the input value. If it is part of a group, and this is being turned on, pop open the group so we can see this input.
		setInput: function ( $input, val ) {
			var collapsible,
				oldVal = $input.is( ':checked' );
			if ( val ) {
				$input.prop( 'checked', true );
			} else {
				$input.prop( 'checked', false );
			}
			if ( val !== oldVal ) {
				this.emit( 'change' );
			}

			// pop open the 'toggle' group if is now on. Do nothing if it is now off.
			if ( val ) {
				collapsible = $input
					.closest( '.mwe-upwiz-deed-license-group' )
					.data( 'mw-collapsible' );
				if ( collapsible ) {
					collapsible.expand();
				}
			}
		},

		// this works fine for blanking all of a radio input, or for checking/unchecking individual checkboxes
		setInputsIndividually: function ( values ) {
			var input = this;
			$.each( this.inputs, function ( i, $input ) {
				var licenseName = $input.data( 'licenseName' ),
					value = licenseName in values && values[ licenseName ] !== false;

				input.setInput( $input, value );

				// if value was a string, it doesn't just mean that we should
				// select the checkbox, but also fill out the textarea it comes with
				if ( value && typeof values[ licenseName ] === 'string' ) {
					$input.data( 'textarea' ).val( values[ licenseName ] );
				}
			} );
		},

		/**
		 * Sets the value(s) of a license input. This is a little bit klugey because it relies on an inverted dict, and in some
		 * cases we are now letting license inputs create multiple templates.
		 *
		 * @param {Object} values License-key to boolean values, e.g. { 'cc_by_sa_30': true, gfdl: true, 'flickrreview|cc_by_sa_30': false }
		 */
		setValues: function ( values ) {
			var trueCount, trueLicenseName,
				input = this;
			// ugly division between radio and checkbox, because in jquery 1.6.4 if you set any element of a radio input to false, every element
			// is set to false!Unfortunately the incoming data structure is a key-val object so we have to make extra sure it makes sense for
			// a radio button input.

			if ( this.type === 'radio' ) {
				// check if how many license names are set to true in the values requested. Should be 0 or 1
				trueCount = 0;

				$.each( values, function ( licenseName, val ) {
					if ( val === true ) {
						trueCount++;
						trueLicenseName = licenseName;
					}
				} );

				if ( trueCount === 0 ) {
					this.setInputsIndividually( values );
				} else if ( trueCount === 1 ) {
					// set just one of the radio inputs and don't touch anything else
					$.each( this.inputs, function ( i, $input ) {
						var licenseName = $input.data( 'licenseName' );
						if ( licenseName === trueLicenseName ) {
							input.setInput( $input, true );
						}
					} );
				} else {
					mw.log.warn( 'Too many true values for a radio button!' );
				}

			} else if ( this.type === 'checkbox' ) {
				this.setInputsIndividually( values );
			} else {
				mw.log.warn( 'Impossible? UploadWizardLicenseInput type neither radio nor checkbox' );
			}
			// we use the selector because events can't be unbound unless they're in the DOM.
			this.emit( 'change' );
		},

		/**
		 * Set the default configured licenses
		 */
		setDefaultValues: function () {
			var values = {};
			values[ this.defaults ] = true;
			this.setValues( values );
		},

		/**
		 * Gets the selected license(s). The returned value will be a license
		 * key => license props map, as defined in UploadWizard.config.php.
		 *
		 * @return {Object}
		 */
		getLicenses: function () {
			var licenses = {};

			this.getSelectedInputs().each( function () {
				var licenseName = this.data( 'licenseName' );
				licenses[ licenseName ] = mw.UploadWizard.config.licenses[ licenseName ] || {};
			} );

			return licenses;
		},

		/**
		 * Gets the wikitext associated with all selected inputs. Some inputs also have associated textareas so we append their contents too.
		 *
		 * @return {string} of wikitext (empty string if no inputs set)
		 */
		getWikiText: function () {
			var input = this,
				wikiTexts = this.getSelectedInputs().map(
					function () {
						return input.getInputWikiText( this );
					}
				);

			// need to use makeArray because a jQuery-returned set of things won't have .join
			return $.makeArray( wikiTexts ).join( '' ).trim();
		},

		/**
		 * Get the value of a particular input
		 *
		 * @param {jQuery} $input
		 * @return {string}
		 */
		getInputWikiText: function ( $input ) {
			return $input.val() + '\n' + this.getInputTextAreaVal( $input );
		},

		/**
		 * Get the value of the associated textarea, if any
		 *
		 * @param {jQuery} $input
		 * @return {string}
		 */
		getInputTextAreaVal: function ( $input ) {
			var extra = '';
			if ( $input.data( 'textarea' ) ) {
				extra = $input.data( 'textarea' ).val().trim();
			}
			return extra;
		},

		/**
		 * Gets which inputs have user-entered values
		 *
		 * @return {jQuery} Array of inputs
		 */
		getSelectedInputs: function () {
			// not sure why filter(':checked') doesn't work
			return $( this.inputs ).filter( function ( i, $x ) { return $x.is( ':checked' ); } );
		},

		/**
		 * Returns a list of templates used & transcluded in given wikitext
		 *
		 * @param {string} wikitext
		 * @return {$.Promise} Promise that resolves with an array of template names
		 */
		getUsedTemplates: function ( wikitext ) {
			var input = this;

			if ( wikitext in this.templateCache ) {
				return $.Deferred().resolve( this.templateCache[ wikitext ] ).promise();
			}

			return this.api.get( {
				action: 'parse',
				pst: true,
				prop: 'templates',
				title: 'File:UploadWizard license verification.png',
				text: wikitext
			} ).then( function ( result ) {
				var templates = [],
					template, title, i;

				for ( i = 0; i < result.parse.templates.length; i++ ) {
					template = result.parse.templates[ i ];

					// normalize templates to mw.Title.getPrefixedDb() format
					title = new mw.Title( template.title, template.ns );
					templates.push( title.getPrefixedDb() );
				}

				// cache result so we won't have to fire another API request
				// for the same content
				input.templateCache[ wikitext ] = templates;

				return templates;
			} );
		},

		/**
		 * See uw.DetailsWidget
		 *
		 * @return {jQuery.Promise}
		 */
		getErrors: function () {
			var input = this,
				errors = $.Deferred().resolve( [] ).promise(),
				addError = function ( message ) {
					errors = errors.then( function ( errors ) {
						errors.push( mw.message( message ) );
						return errors;
					} );
				},
				selectedInputs = this.getSelectedInputs();

			if ( selectedInputs.length === 0 ) {
				addError( 'mwe-upwiz-deeds-need-license' );
			} else {
				// It's pretty hard to screw up a radio button, so if even one of them is selected it's okay.
				// But also check that associated textareas are filled for if the input is selected, and that
				// they are the appropriate size.
				$.each( selectedInputs, function ( i, $input ) {
					var wikitext;

					if ( !$input.data( 'textarea' ) ) {
						return;
					}

					wikitext = input.getInputTextAreaVal( $input );

					if ( wikitext === '' ) {
						addError( 'mwe-upwiz-error-license-wikitext-missing' );
					} else if ( wikitext.length < mw.UploadWizard.config.minCustomLicenseLength ) {
						addError( 'mwe-upwiz-error-license-wikitext-too-short' );
					} else if ( wikitext.length > mw.UploadWizard.config.maxCustomLicenseLength ) {
						addError( 'mwe-upwiz-error-license-wikitext-too-long' );
					} else if ( wikitext.match( /\{\{(.+?)\}\}/g ) === null ) {
						// if text doesn't contain a template, we don't even
						// need to validate it any further...
						addError( 'mwe-upwiz-error-license-wikitext-missing-template' );
					} else if ( mw.UploadWizard.config.customLicenseTemplate !== false ) {
						// now do a thorough test to see if the text actually
						// includes a license template
						errors = $.when(
							errors, // array of existing errors
							input.getUsedTemplates( wikitext )
						).then( function ( errors, usedTemplates ) {
							if ( usedTemplates.indexOf( mw.UploadWizard.config.customLicenseTemplate ) < 0 ) {
								// no license template found, add another error
								errors.push( mw.message( 'mwe-upwiz-error-license-wikitext-missing-template' ) );
							}

							return errors;
						} );
					}
				} );
			}

			return errors;
		},

		/**
		 * See uw.DetailsWidget
		 *
		 * @return {jQuery.Promise}
		 */
		getWarnings: function () {
			return $.Deferred().resolve( [] ).promise();
		},

		/**
		 * Preview wikitext in a popup window
		 *
		 * @param {string} wikiText
		 */
		showPreview: function ( wikiText ) {
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
		},

		/**
		 * @return {Object}
		 */
		getSerialized: function () {
			var i,
				values = {},
				$inputs = this.getSelectedInputs();

			for ( i = 0; i < $inputs.length; i++ ) {
				values[ $inputs[ i ].data( 'licenseName' ) ] = this.getInputTextAreaVal( $inputs[ i ] ) || true;
			}

			return values;
		},

		/**
		 * @param {Object} serialized
		 */
		setSerialized: function ( serialized ) {
			this.setValues( serialized );
		}

	} );

}( mediaWiki, mediaWiki.uploadWizard, jQuery, OO ) );
