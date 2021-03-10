( function ( mw ) {
	var Revision = mw.libs.revisionSlider.Revision;

	QUnit.module( 'ext.RevisionSlider.Revision' );

	QUnit.test( 'create Revision', function ( assert ) {
		var data = {
				size: 5,
				comment: 'hello',
				parsedcomment: '<b>hello</b>',
				timestamp: '2016-04-26T10:27:14Z', // 10:27, 26 Apr 2016
				user: 'meh',
				userGender: 'female'
			},
			rev = new Revision( data );

		mw.libs.revisionSlider.userOffset = 0;

		assert.equal( rev.getSize(), data.size );
		assert.equal( rev.getComment(), data.comment );
		assert.equal( rev.getParsedComment(), data.parsedcomment );
		assert.equal( rev.getUser(), data.user );
		assert.equal( rev.getUserGender(), 'female' );
		assert.equal( rev.isMinor(), false );

		if ( mw.config.get( 'wgUserLanguage' ) === 'en' ) {
			assert.equal( rev.getFormattedDate(), '26 April 2016 10:27 AM' );
		}
	} );

	QUnit.test( 'isMinor with minor empty string', function ( assert ) {
		var rev = new Revision( {
			minor: ''
		} );

		assert.equal( rev.isMinor(), true );
	} );

	QUnit.test( 'isMinor with minor true', function ( assert ) {
		var rev = new Revision( {
			minor: true
		} );

		assert.equal( rev.isMinor(), true );
	} );

	QUnit.test( 'get and set relative size', function ( assert ) {
		var size = 5,
			rev = new Revision( {} );
		rev.setRelativeSize( size );
		assert.equal( rev.getRelativeSize(), size );
	} );

	QUnit.revisionSlider.testOrSkip( 'getFormattedDate, offset: 0', function ( assert ) {
		var rev = new Revision( {
			timestamp: '2016-04-26T10:27:14Z' // 10:27, 26 Apr 2016
		} );

		mw.libs.revisionSlider.userOffset = 0;

		assert.equal( rev.getFormattedDate(), '26 April 2016 10:27 AM' );
	}, mw.config.get( 'wgUserLanguage' ) !== 'en' );

	QUnit.revisionSlider.testOrSkip( 'getFormattedDate, offset: 120 (treat as hours, +2h)', function ( assert ) {
		var rev = new Revision( {
			timestamp: '2016-04-26T10:27:14Z' // 10:27, 26 Apr 2016
		} );

		// Berlin = 120
		mw.libs.revisionSlider.userOffset = 120;

		assert.equal( rev.getFormattedDate(), '26 April 2016 12:27 PM' );
	}, mw.config.get( 'wgUserLanguage' ) !== 'en' );

	QUnit.revisionSlider.testOrSkip( 'getFormattedDate, negative offset: -420 (treat as hours, -7h)', function ( assert ) {
		var rev = new Revision( {
			timestamp: '2016-04-26T10:27:14Z' // 10:27, 26 Apr 2016
		} );

		// San Francisco = -420
		mw.libs.revisionSlider.userOffset = -420;

		assert.equal( rev.getFormattedDate(), '26 April 2016 3:27 AM' );
	}, mw.config.get( 'wgUserLanguage' ) !== 'en' );

	QUnit.test( 'hasEmptyComment comment with whitespaces', function ( assert ) {
		var rev = new Revision( {
			comment: '   '
		} );

		assert.ok( rev.hasEmptyComment() );
	} );

	QUnit.test( 'hasEmptyComment comment with chars', function ( assert ) {
		var rev = new Revision( {
			comment: ' comment '
		} );

		assert.notOk( rev.hasEmptyComment() );
	} );

	QUnit.test( 'hasEmptyComment comment with unicode chars', function ( assert ) {
		var rev = new Revision( {
			comment: 'ברוכים'
		} );

		assert.notOk( rev.hasEmptyComment() );
	} );

}( mediaWiki ) );
