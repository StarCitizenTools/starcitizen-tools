( function ( $, mw ) {

	// FIXME: Move into separate file as this module becomes larger.
	mw.relatedPages = {};

	/**
	 * @class RelatedPagesGateway
	 * @param {mw.Api} api
	 * @param {string} currentPage the page that the editorCuratedPages relate to
	 * @param {Array} editorCuratedPages a list of pages curated by editors for the current page
	 * @param {boolean} useCirrusSearch whether to hit the API when no editor-curated pages are available
	 * @param {boolean} [onlyUseCirrusSearch=false] whether to ignore the list of editor-curated pages
	 */
	function RelatedPagesGateway(
		api,
		currentPage,
		editorCuratedPages,
		useCirrusSearch,
		onlyUseCirrusSearch
	) {
		this.api = api;
		this.currentPage = currentPage;
		this.useCirrusSearch = useCirrusSearch;

		if ( onlyUseCirrusSearch ) {
			editorCuratedPages = [];
		}

		this.editorCuratedPages = editorCuratedPages || [];

	}
	OO.initClass( RelatedPagesGateway );

	/**
	 * @ignore
	 * @param {Object} result
	 * @return {Array}
	 */
	function getPages( result ) {
		return result && result.query && result.query.pages ? result.query.pages : [];
	}

	/**
	 * Gets the related pages for the current page.
	 *
	 * If there are related pages assigned to this page using the `related`
	 * parser function, then they are returned.
	 *
	 * If there aren't any related pages assigned to the page, then the
	 * CirrusSearch extension's {@link https://www.mediawiki.org/wiki/Help:CirrusSearch#morelike: "morelike:" feature}
	 * is used. If the CirrusSearch extension isn't installed, then the API
	 * call will fail gracefully and no related pages will be returned.
	 * Thus the dependency on the CirrusSearch extension is soft.
	 *
	 * Related pages will have the following information:
	 *
	 * * The ID of the page corresponding to the title
	 * * The thumbnail, if any
	 * * The Wikidata description, if any
	 *
	 * @method
	 * @param {number} limit of pages to get. Should be between 1-20.
	 * @return {jQuery.Promise}
	 */
	RelatedPagesGateway.prototype.getForCurrentPage = function ( limit ) {
		var parameters = {
				action: 'query',
				formatversion: 2,
				prop: 'pageimages|description',
				piprop: 'thumbnail',
				pithumbsize: 160 // FIXME: Revert to 80 once pithumbmode is implemented
			},
			// Enforce limit
			relatedPages = this.editorCuratedPages.slice( 0, limit );

		if ( relatedPages.length ) {
			parameters.pilimit = relatedPages.length;
			parameters.continue = '';

			parameters.titles = relatedPages;
		} else if ( this.useCirrusSearch ) {
			parameters.pilimit = limit;

			parameters.generator = 'search';
			parameters.gsrsearch = 'morelike:' + this.currentPage;
			parameters.gsrnamespace = '0';
			parameters.gsrlimit = limit;
			parameters.gsrqiprofile = 'classic_noboostlinks';

			// Currently, if you're logged in, then the API uses your language by default ard so responses
			// are always private i.e. they shouldn't be cached in a shared cache and can be cached by the
			// browser.
			//
			// By make the API use the language of the content rather than that of the user, the API
			// reponse is made public, i.e. they can be cached in a shared cache.
			//
			// See T97096 for more detail and discussion.
			parameters.uselang = 'content';

			// Instruct shared caches that the response will become stale in 24 hours.
			parameters.smaxage = 86400;

			// Instruct the browser that the response will become stale in 24 hours.
			parameters.maxage = 86400;
		} else {
			return $.Deferred().resolve( [] );
		}

		return this.api.get( parameters )
			.then( getPages );
	};

	mw.relatedPages.RelatedPagesGateway = RelatedPagesGateway;
}( jQuery, mediaWiki ) );
