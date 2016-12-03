( function ( mw, uw, $, OO ) {

	/**
	 * A custom campaign-defined field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {string} wikitext Wikitext template to use for this field
	 * @cfg {boolean} [required=false] Whether to mark this field as required
	 * @cfg {string} [type='text'] Field type, 'text' or 'select'
	 * @cfg {number} [maxLength] Maximum allowed length of input
	 * @cfg {Object} [options] Map of select dropdown options to use when `type` is 'text'
	 */
	uw.CampaignDetailsWidget = function UWCampaignDetailsWidget( config ) {
		config = $.extend( { type: 'text' }, config );
		uw.CampaignDetailsWidget.parent.call( this );

		this.required = !!config.required;
		this.wikitext = config.wikitext;

		if ( config.type === 'text' ) {
			this.input = new OO.ui.TextInputWidget( {
				classes: [ 'mwe-idfield', 'mwe-upwiz-campaignDetailsWidget-input' ],
				maxLength: config.maxLength
			} );
		} else if ( config.type === 'select' ) {
			this.input = new OO.ui.DropdownInputWidget( {
				classes: [ 'mwe-idfield', 'mwe-upwiz-campaignDetailsWidget-input' ],
				options: Object.keys( config.options ).map( function ( key ) {
					return { data: key, label: config.options[ key ] };
				} )
			} );
		} else {
			throw new Error( 'Unknown campaign field type: ' + config.type );
		}

		// Aggregate 'change' event
		// (but do not flash warnings in the user's face while they're typing)
		this.input.on( 'change', OO.ui.debounce( this.emit.bind( this, 'change' ), 500 ) );

		this.$element.addClass( 'mwe-id-field mwe-upwiz-campaignDetailsWidget' );
		this.$element.append(
			this.input.$element
		);
	};
	OO.inheritClass( uw.CampaignDetailsWidget, uw.DetailsWidget );

	/**
	 * @inheritdoc
	 */
	uw.CampaignDetailsWidget.prototype.getErrors = function () {
		var errors = [];
		if ( this.required && this.input.getValue().trim() === '' ) {
			errors.push( mw.message( 'mwe-upwiz-error-blank' ) );
		}
		return $.Deferred().resolve( errors ).promise();
	};

	/**
	 * @inheritdoc
	 */
	uw.CampaignDetailsWidget.prototype.getWikiText = function () {
		var value = this.input.getValue().trim();
		if ( value ) {
			value = this.wikitext.replace( '$1', value );
		}
		return value;
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.CampaignDetailsWidget.prototype.getSerialized = function () {
		return {
			value: this.input.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {string} serialized.value Campaign informations text
	 */
	uw.CampaignDetailsWidget.prototype.setSerialized = function ( serialized ) {
		this.input.setValue( serialized.value );
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
