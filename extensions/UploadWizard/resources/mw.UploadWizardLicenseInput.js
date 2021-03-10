( function ( uw ) {

	/**
	 * Create a group of radio buttons for licenses. N.B. the licenses are named after the templates they invoke.
	 * Note that this is very anti-MVC. The values are held only in the actual form elements themselves.
	 *
	 * @extends OO.ui.Widget
	 * @param {Object} config Configuration. Must have following properties:
	 * @param {string} config.type Whether inclusive or exclusive license allowed ("and"|"or")
	 * @param {string[]} config.licenses Template string names (matching keys in mw.UploadWizard.config.licenses)
	 * @param {string[]} [config.licenseGroups] Groups of licenses, with more explanation
	 * @param {string} [config.special] Indicates, don't put licenses here, instead use a special widget
	 * @param {number} count Number of the things we are licensing (it matters to some texts)
	 * @param {mw.Api} api API object, used for wikitext previews
	 */
	mw.UploadWizardLicenseInput = function ( config, count, api ) {
		var self = this,
			groups = [],
			group;

		mw.UploadWizardLicenseInput.parent.call( this );
		OO.ui.mixin.GroupElement.call( this );

		this.count = count;

		this.api = api;

		if (
			config.type === undefined ||
			( config.licenses === undefined && config.licenseGroups === undefined )
		) {
			throw new Error( 'improper initialization' );
		}

		this.type = config.type === 'or' ? 'radio' : 'checkbox';

		this.defaults = [];
		if ( config.defaults ) {
			this.defaults = config.defaults instanceof Array ? config.defaults : [ config.defaults ];
		} else if ( config.licenses && config.licenses[ 0 ] ) {
			this.defaults = [ config.licenses[ 0 ] ];
		}

		// create inputs and licenses from config
		if ( config.licenseGroups === undefined ) {
			group = new uw.LicenseGroup( config, this.type, this.api, this.count );
			groups.push( group );
		} else {
			config.licenseGroups.forEach( function ( groupConfig ) {
				group = new uw.LicenseGroup( groupConfig, self.type, self.api, self.count );
				groups.push( group );

				// if we have multiple radio groups, it would be possible to select a radio
				// from each group; we obviously don't want that to happen!
				// upon selecting a new item in any group, iterate the other groups and make
				// sure they're updated accordingly, deselecting previously selected items
				if ( self.type === 'radio' ) {
					group.on( 'change', function ( currentGroup ) {
						var value = currentGroup.getValue(),
							group = currentGroup.getGroup();
						self.setValues( value, group );
					} );
				}
			} );
		}

		this.addItems( groups );
		this.aggregate( { change: 'change' } );
		this.$element.append( this.$group );

		// [wikitext => list of templates used in wikitext] map, used in
		// getUsedTemplates to reduce amount of API calls
		this.templateCache = {};
	};
	OO.inheritClass( mw.UploadWizardLicenseInput, OO.ui.Widget );
	OO.mixinClass( mw.UploadWizardLicenseInput, OO.ui.mixin.GroupElement );

	$.extend( mw.UploadWizardLicenseInput.prototype, {
		unload: function () {
			this.getItems().forEach( function ( group ) {
				group.unload();
			} );
		},

		/**
		 * Sets the value(s) of a license input. This is a little bit klugey because it relies on an inverted dict, and in some
		 * cases we are now letting license inputs create multiple templates.
		 *
		 * @param {Object} values License-key to boolean values, e.g. { 'cc_by_sa_30': true, gfdl: true, 'flickrreview|cc_by_sa_30': false }
		 * @param {string} [groupName] Name of group, when values are only relevant to this group
		 */
		setValues: function ( values, groupName ) {
			var self = this,
				selectedGroups = [];

			this.getItems().forEach( function ( group ) {
				if ( groupName === undefined || group.getGroup() === groupName ) {
					group.setValue( values );
					if ( Object.keys( group.getValue() ).length > 0 ) {
						selectedGroups.push( group );
					}
				} else if ( self.type === 'radio' ) {
					// when we're dealing with radio buttons and there are changes in another
					// group, then we'll need to clear out this group...
					group.setValue( {} );
				}
			} );

			if ( selectedGroups.length > 1 && this.type === 'radio' ) {
				// leave the last one alone - that one can remain selected
				selectedGroups.pop();

				// if we've selected things in multiple groups (= when the group was not defined,
				// which is basically only when dealing with defaults, from config or user
				// preferences), we need to make sure we're left with only 1 selected radio in
				// 1 group
				// in that case, we're only going to select the *last* occurrence, which is what
				// a browser would do when trying to find/select a radio that occurs twice
				selectedGroups.forEach( function ( group ) {
					group.setValue( {} );
				} );
			}
		},

		/**
		 * Set the default configured licenses
		 */
		setDefaultValues: function () {
			var values = {};
			this.defaults.forEach( function ( license ) {
				values[ license ] = true;
			} );
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

			this.getItems().forEach( function ( group ) {
				var licenseNames = Object.keys( group.getValue() );
				licenseNames.forEach( function ( name ) {
					licenses[ name ] = mw.UploadWizard.config.licenses[ name ] || {};
				} );
			} );

			return licenses;
		},

		/**
		 * Gets the wikitext associated with all selected inputs. Some inputs also have associated textareas so we append their contents too.
		 *
		 * @return {string} of wikitext (empty string if no inputs set)
		 */
		getWikiText: function () {
			return this.getItems().map( function ( group ) {
				return group.getWikiText();
			} ).join( '' ).trim();
		},

		/**
		 * Returns a list of templates used & transcluded in given wikitext
		 *
		 * @param {string} wikitext
		 * @return {jQuery.Promise} Promise that resolves with an array of template names
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
				selectedInputs = this.getSerialized();

			if ( Object.keys( selectedInputs ).length === 0 ) {
				addError( 'mwe-upwiz-deeds-need-license' );
			} else {
				// It's pretty hard to screw up a radio button, so if even one of them is selected it's okay.
				// But also check that associated textareas are filled for if the input is selected, and that
				// they are the appropriate size.
				Object.keys( selectedInputs ).forEach( function ( name ) {
					var wikitext,
						data = selectedInputs[ name ];

					if ( typeof data !== 'string' ) {
						return;
					}

					wikitext = data.trim();

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
		 * @return {Object}
		 */
		getSerialized: function () {
			var values = {};

			this.getItems().forEach( function ( group ) {
				var groupName = group.getGroup(),
					value = group.getValue();

				if ( Object.keys( value ).length > 0 ) {
					// $.extend just in case there are multiple groups with the same name...
					values[ groupName ] = $.extend( {}, values[ groupName ] || {}, value );
				}
			} );

			return values;
		},

		/**
		 * @param {Object} serialized
		 */
		setSerialized: function ( serialized ) {
			var self = this;

			Object.keys( serialized ).forEach( function ( group ) {
				self.setValues( serialized[ group ], group );
			} );
		}

	} );

}( mw.uploadWizard ) );
