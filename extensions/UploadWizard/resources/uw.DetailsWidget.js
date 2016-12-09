( function ( mw, uw, $, OO ) {

	/**
	 * A single logical field in UploadWizard's "Details" step form.
	 *
	 * This can be composed of multiple smaller widgets, but represents a single unit (e.g. a
	 * "location" field could be composed of "latitude" and "longitude" inputs).
	 *
	 * @extends OO.ui.Widget
	 * @abstract
	 */
	uw.DetailsWidget = function UWDetailsWidget() {
		uw.DetailsWidget.parent.call( this );
	};
	OO.inheritClass( uw.DetailsWidget, OO.ui.Widget );

	/**
	 * A 'change' event is emitted when the state of this widget (and the serialized value) changes.
	 *
	 * @event change
	 */

	/**
	 * @inheritdoc OO.ui.mixin.PendingElement#pushPending
	 */
	uw.DetailsWidget.prototype.pushPending = function () {
		// Do nothing by default
	};

	/**
	 * @inheritdoc OO.ui.mixin.PendingElement#popPending
	 */
	uw.DetailsWidget.prototype.popPending = function () {
		// Do nothing by default
	};

	/**
	 * Get the list of errors about the current state of the widget.
	 *
	 * @return {jQuery.Promise} Promise resolved with an array of mw.Message objects
	 *   representing errors. (Checking for errors might require API queries, etc.)
	 */
	uw.DetailsWidget.prototype.getErrors = function () {
		return $.Deferred().resolve( [] ).promise();
	};

	/**
	 * Get the list of warnings about the current state of the widget.
	 *
	 * @return {jQuery.Promise} Promise resolved with an array of mw.Message objects
	 *   representing warnings. (Checking for warnings might require API queries, etc.)
	 */
	uw.DetailsWidget.prototype.getWarnings = function () {
		return $.Deferred().resolve( [] ).promise();
	};

	/**
	 * Get a wikitext snippet generated from current state of the widget.
	 *
	 * @return {string} Wikitext
	 */
	uw.DetailsWidget.prototype.getWikiText = function () {
		// To satisfy JSCS check for @return without upsetting JSHint check for unreachable code:
		if ( false ) {
			return '';
		}
		throw new Error( 'Not implemented' );
	};

	/**
	 * Get a machine-readable representation of the current state of the widget. It can be passed to
	 * #setSerialized to restore this state (or to set it for another instance of the same class).
	 *
	 * @return {Object}
	 */
	uw.DetailsWidget.prototype.getSerialized = function () {
		// To satisfy JSCS check for @return without upsetting JSHint check for unreachable code:
		if ( false ) {
			return {};
		}
		throw new Error( 'Not implemented' );
	};

	/**
	 * Set the state of this widget from machine-readable representation, as returned by
	 * #getSerialized.
	 *
	 * @param {Object} serialized
	 */
	uw.DetailsWidget.prototype.setSerialized = function () {
		throw new Error( 'Not implemented' );
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
