'use strict';
const CARD_SELECTOR = '.ext-related-articles-card',
	Page = require( '../../../../../tests/selenium/pageobjects/page' ),
	READ_MORE_MODULE_NAME = 'ext.relatedArticles.readMore';

class ReadMorePage extends Page {

	get mobileView() { return browser.element( '#footer-places-mobileview' ); }

	openDesktop( name ) {
		super.open( name );
		this.resourceLoaderModuleStatus( READ_MORE_MODULE_NAME, 'registered' );
	}

	openMobile( name ) {
		super.open( name );
		this.mobileView.click();
		this.resourceLoaderModuleStatus( READ_MORE_MODULE_NAME, 'ready' );
	}

	get extCardsCard() {
		return browser.element( '.ext-related-articles-card' );
	}

	get readMore() {
		this.readMoreCodeIsLoaded();
		this.extCardsCard.waitForExist( 2000 );
		return this.extCardsCard;
	}

	isCardVisible() {
		return browser.isVisible( CARD_SELECTOR );
	}

	readMoreCodeIsLoaded() {
		browser.waitUntil( function () {
			return browser.execute( function ( status ) {
				return mw && mw.loader && mw.loader.getState( 'ext.relatedArticles.readMore' ) === status;
			}, 'ready' );
		}, 2000, 'Related pages did not load' );
	}

	resourceLoaderModuleStatus( moduleName, moduleStatus ) {
		return browser.waitUntil( function () {
			return browser.execute( function ( module ) {
				return mw && mw.loader && mw.loader.getState( module.name ) === module.status;
			}, { status: moduleStatus, name: moduleName } );
		}, 10000, 'Related pages did not load' );
	}

	seeReadMore() {
		browser.waitForExist( CARD_SELECTOR, 10000 );
		return true;
	}

}
module.exports = new ReadMorePage();
