/* eslint-disable no-underscore-dangle */
( function ( $, mw ) {
	'use strict';

	/**
	 * Renders a Card model and updates when it does.
	 *
	 * @class mw.cards.CardView
	 * @param {mw.cards.CardModel} model
	 */
	function CardView( model ) {
		/**
		 * @property {mw.cards.CardModel}
		 */
		this.model = model;

		// listen to model changes and re-render the view
		this.model.on( 'change', this.render.bind( this ) );

		/**
		 * @property {jQuery}
		 */
		this.$el = $( this._render() );
	}
	OO.initClass( CardView );

	/**
	 * @property {Object} compiled template
	 */
	CardView.prototype.template = mw.template.get( 'ext.relatedArticles.cards', 'card.muhogan' );

	/**
	 * Replace the html of this.$el with a newly rendered html using the model
	 * attributes.
	 */
	CardView.prototype.render = function () {
		this.$el.replaceWith( this._render() );
	};

	/**
	 * Renders the template using the model attributes.
	 *
	 * @ignore
	 * @return {string}
	 */
	CardView.prototype._render = function () {
		var attributes = $.extend( {}, this.model.attributes );
		attributes.thumbnailUrl = CSS.escape( attributes.thumbnailUrl );

		return this.template.render( attributes );
	};

	mw.cards.CardView = CardView;
}( jQuery, mediaWiki ) );
