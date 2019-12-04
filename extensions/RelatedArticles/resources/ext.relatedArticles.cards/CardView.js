/* eslint-disable no-underscore-dangle */
( function () {
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
		var $listItem = $( '<li>' ),
			attributes = $.extend( {}, this.model.attributes );

		attributes.thumbnailUrl = CSS.escape( attributes.thumbnailUrl );

		$listItem.attr( {
			title: attributes.title,
			class: 'ext-related-articles-card'
		} );

		$listItem.append(
			$( '<div>' )
				.addClass( 'ext-related-articles-card-thumb' )
				.addClass( attributes.hasThumbnail ?
					'' :
					'ext-related-articles-card-thumb-placeholder'
				)
				.css( 'background-image', attributes.hasThumbnail ?
					'url(' + attributes.thumbnailUrl + ')' :
					null
				),
			$( '<a>' )
				.attr( {
					href: attributes.url,
					'aria-hidden': 'true',
					tabindex: -1
				} ),
			$( '<div>' )
				.attr( { class: 'ext-related-articles-card-detail' } )
				.append(
					$( '<h3>' ).append(
						$( '<a>' )
							.attr( { href: attributes.url } )
							.text( attributes.title )
					),
					$( '<p>' )
						.attr( { class: 'ext-related-articles-card-extract' } )
						.text( attributes.extract )
				)
		);

		return $listItem;
	};

	mw.cards.CardView = CardView;
}() );
