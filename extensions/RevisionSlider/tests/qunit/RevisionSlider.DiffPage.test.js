( function ( mw, $ ) {
	var DiffPage = mw.libs.revisionSlider.DiffPage,
		SliderView = mw.libs.revisionSlider.SliderView,
		Slider = mw.libs.revisionSlider.Slider,
		RevisionList = mw.libs.revisionSlider.RevisionList,
		Revision = mw.libs.revisionSlider.Revision;

	QUnit.module( 'ext.RevisionSlider.DiffPage' );

	QUnit.test( 'Initialize DiffPage', function ( assert ) {
		assert.ok( ( new DiffPage() ) );
	} );

	QUnit.test( 'Push state', function ( assert ) {
		var histLength,
			diffPage = new DiffPage(),
			sliderView = new SliderView( new Slider( new RevisionList( [
				new Revision( { revid: 1, comment: '' } ),
				new Revision( { revid: 3, comment: '' } ),
				new Revision( { revid: 37, comment: '' } )
			] ) )
			);

		mw.config.set( 'wgDiffOldId', 1 );
		mw.config.set( 'wgDiffNewId', 37 );
		sliderView.render( $( '<div>' ) );

		histLength = history.length;

		diffPage.pushState( 3, 37, sliderView );

		assert.equal( history.length, histLength + 1 );
		assert.propEqual(
			history.state,
			{
				diff: 3,
				oldid: 37,
				pointerOlderPos: 1,
				pointerNewerPos: 3,
				sliderPos: NaN
			}
		);
	} );

}( mediaWiki, jQuery ) );
