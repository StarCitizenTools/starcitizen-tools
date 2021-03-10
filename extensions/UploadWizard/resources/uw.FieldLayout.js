( function ( uw ) {

	/**
	 * FieldLayout with some UploadWizard-specific bonuses.
	 *
	 * @extends OO.ui.FieldLayout
	 *
	 * @constructor
	 * @inheritdoc
	 * @param {OO.ui.Widget} fieldWidget
	 * @param {Object} [config]
	 * @param {boolean} [config.required=false] Whether to mark this field as required
	 * @param {boolean} [config.align='top']
	 */
	uw.FieldLayout = function UWFieldLayout( fieldWidget, config ) {
		// FieldLayout will add an icon which, when clicked, reveals more information
		// about the input. We'll want to display that by default, so we're getting
		// rid of the "help" property here & will later append that after the header
		var help = config && config.help ? config.help : '';
		config = $.extend( { align: 'top', required: false }, config, { help: '' } );

		uw.FieldLayout.parent.call( this, fieldWidget, config );
		uw.ValidationMessageElement.call( this, { validatedWidget: fieldWidget } );

		this.required = null;
		this.optionalMarker = new OO.ui.LabelWidget( {
			classes: [ 'mwe-upwiz-fieldLayout-indicator' ],
			label: mw.msg( 'mwe-upwiz-label-optional' )
		} );

		this.$element.addClass( 'mwe-upwiz-fieldLayout' );

		this.$element.addClass( 'mwe-upwiz-details-fieldname-input' );
		this.$label.addClass( 'mwe-upwiz-details-fieldname' );
		this.$field.addClass( 'mwe-upwiz-details-input' );

		if ( help ) {
			this.help = new OO.ui.LabelWidget( { label: help } );
			this.$header.after( this.help.$element.addClass( 'mwe-upwiz-details-help' ) );
		}

		this.setRequired( config.required );
	};
	OO.inheritClass( uw.FieldLayout, OO.ui.FieldLayout );
	OO.mixinClass( uw.FieldLayout, uw.ValidationMessageElement );

	/**
	 * @param {boolean} required Whether to mark this field as required
	 */
	uw.FieldLayout.prototype.setRequired = function ( required ) {
		this.required = !!required;
		// only add 'optional' marker after the label if that label
		// has content...
		if ( !this.required && this.$label.text() !== '' ) {
			this.$header.after( this.optionalMarker.$element );
		} else {
			this.optionalMarker.$element.remove();
		}
	};

}( mw.uploadWizard ) );
