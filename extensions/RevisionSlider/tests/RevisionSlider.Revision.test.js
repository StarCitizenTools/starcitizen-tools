( function ( mw ) {
	QUnit.module( 'ext.RevisionSlider.Revision' );

	QUnit.test( 'create Revision', function ( assert ) {
		var data = {
				size: 5,
				comment: 'hello',
				parsedcomment: '<b>hello</b>',
				timestamp: '2016-04-26T10:27:14Z', // 10:27, 26 Apr 2016
				user: 'meh'
			},
			rev = new mw.libs.revisionSlider.Revision( data );

		assert.equal( rev.getSize(), data.size );
		assert.equal( rev.getComment(), data.comment );
		assert.equal( rev.getParsedComment(), data.parsedcomment );
		assert.equal( rev.getFormattedDate(), '10:27, 26 Apr 2016' );
		assert.equal( rev.getUser(), data.user );
	} );

	QUnit.test( 'get Revision with section', function ( assert ) {
		var data = {
				comment: '/* section */ comment'
			},
			rev = new mw.libs.revisionSlider.Revision( data );

		assert.equal( rev.getSection(), 'section' );
	} );

	QUnit.test( 'get Revision without section', function ( assert ) {
		var data = {
				comment: 'no section comment'
			},
			rev = new mw.libs.revisionSlider.Revision( data );

		assert.equal( rev.getSection(), '' );
	} );
} )( mediaWiki );

