( function ( mw ) {
	'use strict';

	/**
	 * Model for an article
	 * It is the single source of truth about a Card, which is a representation
	 * of a wiki article. It emits a 'change' event when its attribute changes.
	 * A View can listen to this event and update the UI accordingly.
	 *
	 * @class mw.cards.CardModel
	 * @extends OO.EventEmitter
	 * @param {Object} attributes article data, such as title, url, etc. about
	 *  an article
	 */
	function CardModel( attributes ) {
		CardModel.super.apply( this, arguments );
		/**
		 * @property {Object} attributes of the model
		 */
		this.attributes = attributes;
	}
	OO.inheritClass( CardModel, OO.EventEmitter );

	/**
	 * Set a model attribute.
	 * Emits a 'change' event with the object whose key is the attribute
	 * that's being updated and value is the value that's being set. The event
	 * can also be silenced.
	 *
	 * @param {string} key attribute that's being set
	 * @param {Mixed} value the value of the key param
	 * @param {boolean} [silent] whether to emit the 'change' event. By default
	 *  the 'change' event will be emitted.
	 */
	CardModel.prototype.set = function ( key, value, silent ) {
		var event = {};

		this.attributes[ key ] = value;
		if ( !silent ) {
			event[ key ] = value;
			this.emit( 'change', event );
		}
	};

	/**
	 * Get the model attribute's value.
	 *
	 * @param {string} key attribute that's being looked up
	 * @return {Mixed}
	 */
	CardModel.prototype.get = function ( key ) {
		return this.attributes[ key ];
	};

	mw.cards.CardModel = CardModel;
}( mediaWiki ) );
