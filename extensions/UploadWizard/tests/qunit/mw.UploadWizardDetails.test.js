( function ( mw, $ ) {
	'use strict';

	var makeTitleInFileNSCases = [ {
		filename: 'foo.png',
		prefixedText: 'File:Foo.png',
		desc: 'filename without namespace starting with a lower case letter'
	}, {
		filename: 'foo_bar-baz.jpg',
		prefixedText: 'File:Foo bar-baz.jpg',
		desc: 'filename without namespace with space in it'
	}, {
		filename: 'Media:foo_bar.jpg',
		prefixedText: 'File:Media:foo bar.jpg',
		desc: 'filename starting with Media:'
	}, {
		filename: 'MediaWiki:foo_bar.jpg',
		prefixedText: 'File:MediaWiki:foo bar.jpg',
		desc: 'filename starting with MediaWiki:'
	}, {
		filename: 'File:foo_bar.jpg',
		prefixedText: 'File:Foo bar.jpg',
		desc: 'filename starting with File:'
	}, {
		filename: 'file:foo_bar.jpg',
		prefixedText: 'File:Foo bar.jpg',
		desc: 'filename starting with file:'
	} ];

	QUnit.module( 'ext.uploadWizard/mw.UploadWizardDetails.test.js', QUnit.newMwEnvironment() );

	QUnit.test( 'makeTitleInFileNS()', makeTitleInFileNSCases.length, function () {
		var makeTitleInFileNS = mw.UploadWizardDetails.makeTitleInFileNS;

		$.each( makeTitleInFileNSCases, function ( i, test ) {
			QUnit.equal(
				makeTitleInFileNS( test.filename ).getPrefixedText(),
				test.prefixedText,
				test.desc
			);
		} );
	} );
}( mediaWiki, jQuery ) );
