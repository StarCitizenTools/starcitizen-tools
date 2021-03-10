( function ( M, $ ) {
	var RelatedPagesGateway = mw.relatedPages.RelatedPagesGateway,
		lotsaRelatedPages = [ 'A', 'B', 'C', 'D', 'E', 'F' ],
		relatedPages = {
			query: {
				pages: [
					{
						pageid: 123,
						title: 'Oh noes',
						ns: 0,
						thumbnail: {
							source: 'http://placehold.it/200x100'
						}
					}
				]
			}
		},
		emptyRelatedPages = {
			query: {
				pages: []
			}
		};

	QUnit.module( 'ext.relatedArticles.gateway', {
		setup: function () {
			this.api = new mw.Api();
		}
	} );

	QUnit.test( 'Returns an array with the results when api responds', function ( assert ) {
		var gateway = new RelatedPagesGateway( this.api, 'Foo', null, true );
		this.sandbox.stub( this.api, 'get' ).returns( $.Deferred().resolve( relatedPages ) );

		return gateway.getForCurrentPage( 1 ).then( function ( results ) {
			assert.ok( $.isArray( results ), 'Results must be an array' );
			assert.strictEqual( results[ 0 ].title, 'Oh noes' );
		} );
	} );

	QUnit.test( 'Empty related pages is handled fine.', function ( assert ) {
		var gateway = new RelatedPagesGateway( this.api, 'Foo', null, true );
		this.sandbox.stub( this.api, 'get' ).returns( $.Deferred().resolve( emptyRelatedPages ) );

		return gateway.getForCurrentPage( 1 ).then( function ( results ) {
			assert.ok( $.isArray( results ), 'Results must be an array' );
			assert.strictEqual( results.length, 0 );
		} );
	} );

	QUnit.test( 'Empty related pages with no cirrus search is handled fine. No API request.', function ( assert ) {
		var gateway = new RelatedPagesGateway( this.api, 'Foo', [], false ),
			spy = this.sandbox.stub( this.api, 'get' ).returns( $.Deferred().resolve( relatedPages ) );

		return gateway.getForCurrentPage( 1 ).then( function ( results ) {
			assert.ok( $.isArray( results ), 'Results must be an array' );
			assert.ok( !spy.called, 'API is not invoked' );
			assert.strictEqual( results.length, 0 );
		} );
	} );

	QUnit.test( 'Related pages from editor curated content', function ( assert ) {
		var gateway = new RelatedPagesGateway( this.api, 'Foo', [ { title: 1 } ], false );
		this.sandbox.stub( this.api, 'get' ).returns( $.Deferred().resolve( relatedPages ) );

		return gateway.getForCurrentPage( 1 ).then( function ( results ) {
			assert.strictEqual( results.length, 1,
				'API still hit despite cirrus being disabled.' );
		} );
	} );

	QUnit.test( 'When limit is higher than number of cards, no limit is enforced.', function ( assert ) {
		var gateway = new RelatedPagesGateway( this.api, 'Foo', lotsaRelatedPages, true ),
			// needed to get page images etc..
			stub = this.sandbox.stub( this.api, 'get' )
				.returns( $.Deferred().resolve( relatedPages ) );

		return gateway.getForCurrentPage( 20 ).then( function () {
			assert.strictEqual( stub.args[ 0 ][ 0 ].titles.length, lotsaRelatedPages.length );
		} );
	} );

	QUnit.test( 'When limit is 2, results are restricted.', function ( assert ) {
		var gateway = new RelatedPagesGateway( this.api, 'Foo', lotsaRelatedPages, true ),
			// needed to get page images etc..
			stub = this.sandbox.stub( this.api, 'get' )
				.returns( $.Deferred().resolve( relatedPages ) );

		return gateway.getForCurrentPage( 2 ).then( function () {
			assert.strictEqual( stub.args[ 0 ][ 0 ].titles.length, 2 );
		} );
	} );

	QUnit.test( 'What if editor curated pages is undefined?', function ( assert ) {
		var gateway = new RelatedPagesGateway( this.api, 'Foo', undefined, true );
		// needed to get page images etc..
		this.sandbox.stub( this.api, 'get' )
			.returns( $.Deferred().resolve( relatedPages ) );

		return gateway.getForCurrentPage( 1 ).then( function ( results ) {
			assert.ok( $.isArray( results ), 'Results must be an array' );
			assert.strictEqual( results.length, 1, 'API is invoked to source articles.' );
		} );
	} );

	QUnit.test( 'Ignore related pages from editor curated content', function ( assert ) {
		var wgRelatedArticles = [
				'Bar',
				'Baz',
				'Qux'
			],
			gateway = new RelatedPagesGateway( this.api, 'Foo', wgRelatedArticles, true, true ),
			spy;

		spy = this.sandbox.stub( this.api, 'get' )
			.returns( $.Deferred().resolve( relatedPages ) );

		return gateway.getForCurrentPage( 1 ).then( function () {
			var parameters = spy.lastCall.args[ 0 ];

			assert.strictEqual(
				parameters.generator,
				'search',
				'it should hit the CirrusSearch API even though wgRelatedArticles is non-empty'
			);
		} );
	} );

}( mw.mobileFrontend, jQuery ) );
