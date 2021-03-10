( function ( uw ) {

	/**
	 * Element that is able to display validation messages from itself or another widget.
	 *
	 * @abstract
	 * @class
	 *
	 * @constructor
	 * @param {Object} [config]
	 * @param {OO.ui.Widget} [config.validatedWidget] Widget to validate
	 */
	uw.ValidationMessageElement = function UWValidationMessageElement( config ) {
		config = config || {};

		this.validatedWidget = config.validatedWidget || this;
		this.$messages = $( '<ul>' );

		this.errors = [];
		this.warnings = [];
		this.successMessages = [];
		this.notices = [];

		this.validatedWidget.connect( this, {
			change: 'checkValidity'
		} );

		this.$messages.addClass( 'oo-ui-fieldLayout-messages' );
		this.$element.addClass( 'mwe-upwiz-validationMessageElement' );
	};

	// Hack: Steal methods from OO.ui.FieldLayout.
	// TODO: Upstream ValidationMessageElement to OOUI, make FieldLayout use it.
	uw.ValidationMessageElement.prototype.makeMessage = OO.ui.FieldLayout.prototype.makeMessage;
	uw.ValidationMessageElement.prototype.setErrors = OO.ui.FieldLayout.prototype.setErrors;
	uw.ValidationMessageElement.prototype.setNotices = OO.ui.FieldLayout.prototype.setNotices;
	uw.ValidationMessageElement.prototype.updateMessages = OO.ui.FieldLayout.prototype.updateMessages;

	/**
	 * Check the field's widget for errors and warnings and display them in the UI.
	 *
	 * @param {boolean} thorough True to perform a thorough validity check. Defaults to false for a fast on-change check.
	 * @return {jQuery.Promise}
	 */
	uw.ValidationMessageElement.prototype.checkValidity = function ( thorough ) {
		var element = this;
		thorough = thorough || false;

		if ( !this.validatedWidget.getWarnings || !this.validatedWidget.getErrors ) {
			// Don't do anything for non-Details widgets
			return;
		}
		if ( this.validatedWidget.pushPending ) {
			this.validatedWidget.pushPending();
		}

		return $.when(
			this.validatedWidget.getWarnings( thorough ),
			this.validatedWidget.getErrors( thorough )
		).then( function ( warnings, errors ) {
			// this.notices and this.errors are arrays of mw.Messages and not strings in this subclass
			element.setNotices( warnings );
			element.setErrors( errors );

			return $.Deferred().resolve( warnings, errors ).promise();
		} ).always( function () {
			if ( element.validatedWidget.popPending ) {
				element.validatedWidget.popPending();
			}
		} );
	};

	/**
	 * @protected
	 * @param {string} kind 'error' or 'notice'
	 * @param {mw.Message|Object} error Message, or an object in { key: ..., html: ... } format
	 * @return {jQuery}
	 */
	uw.ValidationMessageElement.prototype.makeMessage = function ( kind, error ) {
		var code, $content, $listItem;
		if ( error.parseDom ) {
			// mw.Message object
			code = error.key;
			$content = error.parseDom();
		} else {
			// { key: ..., html: ... } object (= formatted API error responses)
			code = error.code;
			$content = $( $.parseHTML( error.html ) );
		}
		$listItem = OO.ui.FieldLayout.prototype.makeMessage.call( this, kind, $content )
			.addClass( 'mwe-upwiz-fieldLayout-' + kind + '-' + code );
		return $listItem;
	};

}( mw.uploadWizard ) );
