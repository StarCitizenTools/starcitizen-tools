( function ( uw ) {
	'use strict';

	var fileNs, makeTitleInFileNSCases;
	fileNs = mw.config.get( 'wgFormattedNamespaces' )[ 6 ];
	makeTitleInFileNSCases = [ {
		filename: 'foo.png',
		prefixedText: fileNs + ':Foo.png',
		desc: 'filename without namespace starting with a lower case letter'
	}, {
		filename: 'foo_bar-baz.jpg',
		prefixedText: fileNs + ':Foo bar-baz.jpg',
		desc: 'filename without namespace with space in it'
	}, {
		filename: 'MediaWiki:foo_bar.jpg',
		prefixedText: null,
		desc: 'filename starting with MediaWiki: (colons are disallowed)'
	}, {
		filename: 'File:foo_bar.jpg',
		prefixedText: fileNs + ':Foo bar.jpg',
		desc: 'filename starting with File:'
	}, {
		filename: 'file:foo_bar.jpg',
		prefixedText: fileNs + ':Foo bar.jpg',
		desc: 'filename starting with file:'
	}, {
		filename: 'Foo part 1/2.jpg',
		prefixedText: null,
		desc: 'filename with characters disallowed in file names'
	} ];

	QUnit.module( 'uw.TitleDetailsWidget', QUnit.newMwEnvironment() );

	QUnit.test( '.static.makeTitleInFileNS()', function ( assert ) {
		var makeTitleInFileNS = uw.TitleDetailsWidget.static.makeTitleInFileNS;

		makeTitleInFileNSCases.forEach( function ( test ) {
			var title = makeTitleInFileNS( test.filename );
			assert.strictEqual(
				title ? title.getPrefixedText() : title,
				test.prefixedText,
				test.desc
			);
		} );
	} );
}( mw.uploadWizard ) );
