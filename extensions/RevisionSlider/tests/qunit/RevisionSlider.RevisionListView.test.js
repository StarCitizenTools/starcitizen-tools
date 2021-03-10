( function ( mw, $ ) {
	var RevisionListView = mw.libs.revisionSlider.RevisionListView,
		RevisionList = mw.libs.revisionSlider.RevisionList,
		Revision = mw.libs.revisionSlider.Revision;

	QUnit.module( 'ext.RevisionSlider.RevisionListView' );

	QUnit.test( 'render adds revisions', function ( assert ) {
		var revisionListView = new RevisionListView( new RevisionList( [
				new Revision( { revid: 1, size: 5, comment: '' } ),
				new Revision( { revid: 3, size: 213, comment: '' } ),
				new Revision( { revid: 37, size: 100, comment: '' } )
			] ) ),
			$resultHtml, $revisionWrapperDivs, $revisionDivs;

		$resultHtml = revisionListView.render( 11 );
		$revisionWrapperDivs = $resultHtml.find( '.mw-revslider-revision-wrapper' );
		$revisionDivs = $resultHtml.find( '.mw-revslider-revision' );

		assert.equal( $revisionWrapperDivs.length, 3 );
		assert.equal( $( $revisionDivs[ 0 ] ).attr( 'data-revid' ), 1 );
		assert.equal( $( $revisionDivs[ 2 ] ).attr( 'data-revid' ), 37 );
		assert.equal( $( $revisionDivs[ 1 ] ).css( 'width' ), '11px' );
		assert.equal( $( $revisionDivs[ 1 ] ).css( 'height' ), '66px' ); // max relative size
		assert.ok( $( $revisionDivs[ 1 ] ).hasClass( 'mw-revslider-revision-up' ) );
		assert.ok( $( $revisionDivs[ 2 ] ).hasClass( 'mw-revslider-revision-down' ) );
	} );

	QUnit.test( 'tooltip is composed correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			revision = new Revision( {
				revid: 1,
				size: 230,
				comment: 'Hello',
				parsedcomment: '<strong>Hello</strong>',
				timestamp: '2016-04-26T10:27:14Z', // 10:27, 26 Apr 2016
				user: 'User1',
				minor: true
			} ),
			tooltip,
			tooltipHtml;

		revision.setRelativeSize( 210 );

		mw.libs.revisionSlider.userOffset = 0;

		tooltip = revisionListView.makeTooltip( revision, {} );
		tooltipHtml = tooltip.$element.html();

		assert.ok( tooltipHtml.match( /User1/ ), 'Test the user.' );
		assert.ok( tooltipHtml.match( /Hello/ ), 'Test the comment.' );
		assert.ok( tooltipHtml.match( /230/ ), 'Test the page size.' );
		assert.ok( tooltipHtml.match( /\+210/ ), 'Test the change size.' );
	} );

	QUnit.revisionSlider.testOrSkip( 'tooltip is composed correctly with en lang', function ( assert ) {
		var revisionListView = new RevisionListView(),
			revision = new Revision( {
				revid: 1,
				size: 2300,
				comment: 'Hello',
				parsedcomment: '<strong>Hello</strong>',
				timestamp: '2016-04-26T10:27:14Z', // 10:27, 26 Apr 2016
				user: 'User1',
				minor: true
			} ),
			tooltip,
			tooltipHtml;

		revision.setRelativeSize( 2100 );

		mw.libs.revisionSlider.userOffset = 0;

		tooltip = revisionListView.makeTooltip( revision, {} );
		tooltipHtml = tooltip.$element.html();

		assert.ok( tooltipHtml.match( /User1/ ), 'Test the user.' );
		assert.ok( tooltipHtml.match( /Hello/ ), 'Test the comment.' );
		assert.ok( tooltipHtml.match( /2,300/ ), 'Test the page size.' );
		assert.ok( tooltipHtml.match( /\+2,100/ ), 'Test the change size.' );
		assert.ok( tooltipHtml.match( /26 April 2016 10:27 AM/ ), 'Test the date.' );
		assert.ok( tooltipHtml.match( /minor/ ), 'Test minor.' );
	}, mw.config.get( 'wgUserLanguage' ) !== 'en' );

	QUnit.test( 'empty user leads to no user line', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$userLineHtml;

		$userLineHtml = revisionListView.makeUserLine( null );

		assert.equal( $userLineHtml, '' );
	} );

	QUnit.test( 'user line is composed correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$userLineHtml;

		$userLineHtml = revisionListView.makeUserLine( 'User1' );

		assert.equal( $userLineHtml.find( 'a' ).text(), 'User1' );
		assert.ok( $userLineHtml.find( 'a' ).attr( 'href' ).match( /User:User1/ ) );
	} );

	QUnit.test( 'IP user line is composed correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$userLineHtml;

		$userLineHtml = revisionListView.makeUserLine( '127.0.0.1' );

		assert.equal( $userLineHtml.find( 'a' ).text(), '127.0.0.1' );
		assert.ok( $userLineHtml.find( 'a' ).attr( 'href' ).match( /Special:Contributions\/127.0.0.1/ ) );
	} );

	QUnit.test( 'empty comment leads to no comment line', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$commentHtml;

		$commentHtml = revisionListView.makeCommentLine( new Revision( {
			comment: '   ',
			parsedcomment: '   '
		} ) );

		assert.equal( $commentHtml, '' );
	} );

	QUnit.test( 'comment line is composed correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$commentLineHtml;

		$commentLineHtml = revisionListView.makeCommentLine( new Revision( {
			comment: 'Hello',
			parsedcomment: '<strong>Hello</strong>'
		} ) );

		assert.equal( $commentLineHtml.find( 'strong' ).length, 2 );
	} );

	QUnit.test( 'positive change is composed correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$changeSizeLineHtml;

		$changeSizeLineHtml = revisionListView.makeChangeSizeLine( 9 );

		assert.equal( $changeSizeLineHtml.find( '.mw-revslider-change-positive' ).length, 1 );
		assert.equal( $changeSizeLineHtml.find( '.mw-revslider-change-positive' ).text(), '+9' );
	} );

	QUnit.test( 'negative change is composed correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$changeSizeLineHtml;

		$changeSizeLineHtml = revisionListView.makeChangeSizeLine( -9 );

		assert.equal( $changeSizeLineHtml.find( '.mw-revslider-change-negative' ).length, 1 );
		assert.equal( $changeSizeLineHtml.find( '.mw-revslider-change-negative' ).text(), '-9' );
	} );

	QUnit.test( 'neutral change is composed correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$changeSizeLineHtml;

		$changeSizeLineHtml = revisionListView.makeChangeSizeLine( 0 );

		assert.equal( $changeSizeLineHtml.find( '.mw-revslider-change-none' ).length, 1 );
		assert.equal( $changeSizeLineHtml.find( '.mw-revslider-change-none' ).text(), '0' );
	} );

	QUnit.test( 'big change number is formatted correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$changeSizeLineHtml;

		$changeSizeLineHtml = revisionListView.makeChangeSizeLine( 1000 );

		assert.equal( $changeSizeLineHtml.find( '.mw-revslider-change-positive' ).text(), '+1,000' );
	} );

	QUnit.test( 'page size is formatted correctly', function ( assert ) {
		var revisionListView = new RevisionListView(),
			$pageSizeLineHtml;

		$pageSizeLineHtml = revisionListView.makePageSizeLine( 1337 );

		assert.ok( $pageSizeLineHtml.text().match( /1,337/ ) );
	} );

}( mediaWiki, jQuery ) );
