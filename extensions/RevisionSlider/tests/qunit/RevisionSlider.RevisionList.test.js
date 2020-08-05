( function ( mw ) {
	var Revision = mw.libs.revisionSlider.Revision,
		RevisionList = mw.libs.revisionSlider.RevisionList,
		makeRevisions = mw.libs.revisionSlider.makeRevisions;

	QUnit.module( 'ext.RevisionSlider.RevisionList' );

	QUnit.test( 'Find biggest Revision', function ( assert ) {
		var revs = new RevisionList( [
			new Revision( { revid: 1, size: 5 } ),
			new Revision( { revid: 2, size: 21 } ),
			new Revision( { revid: 3, size: 13 } )
		] );

		assert.equal( revs.getBiggestChangeSize(), 16 );
	} );

	QUnit.test( 'calculate relative size on init', function ( assert ) {
		var revs = new RevisionList( [
			new Revision( { revid: 1, size: 5 } ),
			new Revision( { revid: 2, size: 21 } ),
			new Revision( { revid: 3, size: 13 } )
		] );

		assert.equal( revs.getRevisions()[ 0 ].getRelativeSize(), 5 );
		assert.equal( revs.getRevisions()[ 1 ].getRelativeSize(), 16 );
		assert.equal( revs.getRevisions()[ 2 ].getRelativeSize(), -8 );
	} );

	QUnit.test( 'getUserGenders', function ( assert ) {
		var revs = new RevisionList( [
			new Revision( { revid: 1, user: 'User1', userGender: 'female' } ),
			new Revision( { revid: 2, user: 'User2' } ),
			new Revision( { revid: 3, user: 'User3', userGender: 'male' } )
		] );

		assert.deepEqual( revs.getUserGenders(), { User1: 'female', User2: '', User3: 'male' } );
	} );

	QUnit.test( 'Push appends revisions to the end of the list', function ( assert ) {
		var list = new RevisionList( [
				new Revision( { revid: 1, size: 5 } ),
				new Revision( { revid: 2, size: 21 } ),
				new Revision( { revid: 3, size: 13 } )
			] ),
			revisions;
		list.push( [
			new Revision( { revid: 6, size: 19 } ),
			new Revision( { revid: 8, size: 25 } )
		] );

		revisions = list.getRevisions();
		assert.equal( list.getLength(), 5 );
		assert.equal( revisions[ 0 ].getId(), 1 );
		assert.equal( revisions[ 0 ].getRelativeSize(), 5 );
		assert.equal( revisions[ 1 ].getId(), 2 );
		assert.equal( revisions[ 1 ].getRelativeSize(), 16 );
		assert.equal( revisions[ 2 ].getId(), 3 );
		assert.equal( revisions[ 2 ].getRelativeSize(), -8 );
		assert.equal( revisions[ 3 ].getId(), 6 );
		assert.equal( revisions[ 3 ].getRelativeSize(), 6 );
		assert.equal( revisions[ 4 ].getId(), 8 );
		assert.equal( revisions[ 4 ].getRelativeSize(), 6 );
	} );

	QUnit.test( 'Unshift prepends revisions to the beginning of the list', function ( assert ) {
		var list = new RevisionList( [
				new Revision( { revid: 5, size: 5 } ),
				new Revision( { revid: 6, size: 21 } ),
				new Revision( { revid: 7, size: 13 } )
			] ),
			revisions;
		list.unshift( [
			new Revision( { revid: 2, size: 19 } ),
			new Revision( { revid: 4, size: 25 } )
		] );

		revisions = list.getRevisions();
		assert.equal( list.getLength(), 5 );
		assert.equal( revisions[ 0 ].getId(), 2 );
		assert.equal( revisions[ 0 ].getRelativeSize(), 19 );
		assert.equal( revisions[ 1 ].getId(), 4 );
		assert.equal( revisions[ 1 ].getRelativeSize(), 6 );
		assert.equal( revisions[ 2 ].getId(), 5 );
		assert.equal( revisions[ 2 ].getRelativeSize(), -20 );
		assert.equal( revisions[ 3 ].getId(), 6 );
		assert.equal( revisions[ 3 ].getRelativeSize(), 16 );
		assert.equal( revisions[ 4 ].getId(), 7 );
		assert.equal( revisions[ 4 ].getRelativeSize(), -8 );
	} );

	QUnit.test( 'Unshift considers the size of the preceding revision if specified', function ( assert ) {
		var list = new RevisionList( [
				new Revision( { revid: 5, size: 5 } ),
				new Revision( { revid: 6, size: 21 } ),
				new Revision( { revid: 7, size: 13 } )
			] ),
			revisions;
		list.unshift(
			[
				new Revision( { revid: 2, size: 19 } ),
				new Revision( { revid: 4, size: 25 } )
			],
			12
		);

		revisions = list.getRevisions();
		assert.equal( list.getLength(), 5 );
		assert.equal( revisions[ 0 ].getId(), 2 );
		assert.equal( revisions[ 0 ].getRelativeSize(), 7 );
	} );

	QUnit.test( 'Slice returns a subset of the list', function ( assert ) {
		var list = new RevisionList( [
				new Revision( { revid: 1, size: 5 } ),
				new Revision( { revid: 2, size: 21 } ),
				new Revision( { revid: 3, size: 13 } ),
				new Revision( { revid: 6, size: 19 } ),
				new Revision( { revid: 8, size: 25 } )
			] ),
			slicedList = list.slice( 1, 3 ),
			revisions = slicedList.getRevisions();

		assert.equal( slicedList.getLength(), 2 );
		assert.equal( revisions[ 0 ].getId(), 2 );
		assert.equal( revisions[ 0 ].getRelativeSize(), 16 );
		assert.equal( revisions[ 1 ].getId(), 3 );
		assert.equal( revisions[ 1 ].getRelativeSize(), -8 );
	} );

	QUnit.test( 'Slice returns a subset of the list, end param omitted', function ( assert ) {
		var list = new RevisionList( [
				new Revision( { revid: 1, size: 5 } ),
				new Revision( { revid: 2, size: 21 } ),
				new Revision( { revid: 3, size: 13 } ),
				new Revision( { revid: 6, size: 19 } ),
				new Revision( { revid: 8, size: 25 } )
			] ),
			slicedList = list.slice( 1 ),
			revisions = slicedList.getRevisions();

		assert.equal( slicedList.getLength(), 4 );
		assert.equal( revisions[ 0 ].getId(), 2 );
		assert.equal( revisions[ 1 ].getId(), 3 );
		assert.equal( revisions[ 2 ].getId(), 6 );
		assert.equal( revisions[ 3 ].getId(), 8 );
	} );

	QUnit.test( 'makeRevisions converts revision data into list of Revision objects', function ( assert ) {
		var revs = [
				{ revid: 1, size: 5, userGender: 'female' },
				{ revid: 2, size: 21, userGender: 'unknown' },
				{ revid: 3, size: 13 }
			],
			revisions = makeRevisions( revs );

		assert.equal( revisions[ 0 ].getId(), 1 );
		assert.equal( revisions[ 0 ].getSize(), 5 );
		assert.equal( revisions[ 1 ].getId(), 2 );
		assert.equal( revisions[ 1 ].getSize(), 21 );
		assert.equal( revisions[ 2 ].getId(), 3 );
		assert.equal( revisions[ 2 ].getSize(), 13 );
	} );
}( mediaWiki ) );
