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

		this.fieldWidget.connect( this, {
			change: 'checkValidity'
		} );

		this.setRequired( config.required );
	};
	OO.inheritClass( uw.FieldLayout, OO.ui.FieldLayout );

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

	/**
	 * Check the field's widget for errors and warnings and display them in the UI.
	 */
	uw.FieldLayout.prototype.checkValidity = function () {
		var layout = this;
		if ( !this.fieldWidget.getWarnings || !this.fieldWidget.getErrors ) {
			// Don't do anything for non-Details widgets
			return;
		}
		if ( this.fieldWidget.pushPending ) {
			this.fieldWidget.pushPending();
		}
		$.when(
			this.fieldWidget.getWarnings(),
			this.fieldWidget.getErrors()
		).done( function ( warnings, errors ) {
			// this.notices and this.errors are arrays of mw.Messages and not strings in this subclass
			layout.setNotices( warnings );
			layout.setErrors( errors );
		} ).always( function () {
			if ( layout.fieldWidget.popPending ) {
				layout.fieldWidget.popPending();
			}
		} );
	};

	/**
	 * @inheritdoc
	 */
	uw.FieldLayout.prototype.makeMessage = function ( kind, msg ) {
		var
			content = msg.parseDom(),
			$listItem = uw.FieldLayout.parent.prototype.makeMessage.call( this, kind, content );
		$listItem.addClass( 'mwe-upwiz-fieldLayout-' + kind + '-' + msg.key );
		return $listItem;
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
