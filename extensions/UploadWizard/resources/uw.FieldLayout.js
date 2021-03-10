( function ( mw, uw, $, OO ) {

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
		config = $.extend( { align: 'top', required: false }, config );
		uw.FieldLayout.parent.call( this, fieldWidget, config );
		uw.ValidationMessageElement.call( this, { validatedWidget: fieldWidget } );

		this.required = null;
		this.requiredMarker = new OO.ui.IndicatorWidget( {
			classes: [ 'mwe-upwiz-fieldLayout-indicator' ],
			indicator: 'required',
			title: mw.msg( 'mwe-upwiz-error-blank' )
		} );

		this.$element.addClass( 'mwe-upwiz-fieldLayout' );

		this.$element.addClass( 'mwe-upwiz-details-fieldname-input' );
		this.$label.addClass( 'mwe-upwiz-details-fieldname' );
		this.$field.addClass( 'mwe-upwiz-details-input' );

		this.setRequired( config.required );
	};
	OO.inheritClass( uw.FieldLayout, OO.ui.FieldLayout );
	OO.mixinClass( uw.FieldLayout, uw.ValidationMessageElement );

	/**
	 * @return {boolean} Whether this field is marked as required
	 */
	uw.FieldLayout.prototype.getRequired = function () {
		return this.required;
	};

	/**
	 * @param {boolean} required Whether to mark this field as required
	 */
	uw.FieldLayout.prototype.setRequired = function ( required ) {
		this.required = !!required;
		if ( this.required ) {
			this.$label.after( this.requiredMarker.$element );
		} else {
			this.requiredMarker.$element.remove();
		}
	};

}( mediaWiki, mediaWiki.uploadWizard, jQuery, OO ) );
