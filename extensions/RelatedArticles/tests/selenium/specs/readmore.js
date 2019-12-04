'use strict';

var assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api' ),
	ReadMorePage = require( '../pageobjects/readmore.page' );

describe( 'ReadMore', function () {

	let name = 'Related Articles 1';

	before( function () {
		// Create page needed for the tests
		browser.call( function () {
			let content = '{{#related:related_articles_2}}';
			return Api.edit( name, content );
		} );
	} );

	it( 'ReadMore is not present on Vector', function () {
		ReadMorePage.openDesktop( name );
		assert( !ReadMorePage.isCardVisible(), 'No related pages cards are shown' );
	} );

	it( 'ReadMore is present in Minerva', function () {
		ReadMorePage.openMobile( name );
		assert( ReadMorePage.seeReadMore() );
	} );
} );
