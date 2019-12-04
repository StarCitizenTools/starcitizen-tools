( function ( $, mw ) {
	'use strict';

	/**
	 * View that renders multiple {@link mw.cards.CardView cards}
	 *
	 * @class mw.cards.CardListView
	 * @param {mw.cards.CardView[]} cardViews
	 */
	function CardListView( cardViews ) {
		var self = this;

		/**
		 * @property {mw.cards.CardView[]|Array}
		 */
		this.cardViews = cardViews || [];

		/**
		 * @property {jQuery}
		 */
		this.$el = $( this.template.render() );

		// We don't want to use template partials because we want to
		// preserve event handlers of each card view.
		$.each( this.cardViews, function ( i, cardView ) {
			self.$el.append( cardView.$el );
		} );
	}
	OO.initClass( CardListView );

	/**
	 * @property {Object} compiled template
	 */
	CardListView.prototype.template = mw.template.get( 'ext.relatedArticles.cards', 'cards.muhogan' );

	mw.cards.CardListView = CardListView;
}( jQuery, mediaWiki ) );
