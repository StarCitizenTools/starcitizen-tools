( function () {
	/*!
	 * TemplateData Generator button fixture
	 * The button will appear on Template namespaces only, above the edit textbox
	 *
	 * @author Moriel Schottlender
	 */
	'use strict';

	/* global ve */

	$( function () {
		// Check if we're in the proper namespace
		if ( mw.config.get( 'wgCanonicalNamespace' ) !== 'Template' ) {
			return;
		}

		new mw.Api().loadMessages( 'templatedata-doc-subpage', { amlang: mw.config.get( 'wgContentLanguage' ) } ).then( function () {
			var pieces, isDocPage, target,
				pageName = mw.config.get( 'wgPageName' ),
				docSubpage = mw.msg( 'templatedata-doc-subpage' ),
				config = {
					pageName: pageName,
					isPageSubLevel: false
				},
				$textbox = $( '#wpTextbox1' );

			pieces = pageName.split( '/' );
			isDocPage = pieces.length > 1 && pieces[ pieces.length - 1 ] === docSubpage;

			config = {
				pageName: pageName,
				isPageSubLevel: pieces.length > 1,
				parentPage: pageName,
				isDocPage: isDocPage,
				docSubpage: docSubpage
			};

			// Only if we are in a doc page do we set the parent page to
			// the one above. Otherwise, all parent pages are current pages
			if ( isDocPage ) {
				pieces.pop();
				config.parentPage = pieces.join( '/' );
			}

			// Textbox wikitext editor
			if ( $textbox.length ) {
				// Prepare the editor
				target = new mw.TemplateData.TextareaTarget( $textbox, config );
				$( '#mw-content-text' ).prepend( target.$element );
			}
			// Visual editor source mode
			mw.hook( 've.activationComplete' ).add( function () {
				var surface = ve.init.target.getSurface();
				if ( surface.getMode() === 'source' ) {
					target = new mw.TemplateData.VETarget( surface, config );
					// Use the same font size as main content text
					target.$element.addClass( 'mw-body-content' );
					$( '.ve-init-mw-desktopArticleTarget-originalContent' ).prepend( target.$element );
				}
			} );
			mw.hook( 've.deactivate' ).add( function () {
				if ( target ) {
					target.destroy();
					target = null;
				}
			} );
		} );
	} );

}() );
