( function ( mw ) {
	mw.libs.revisionSlider = mw.libs.revisionSlider || {};

	// see http://stackoverflow.com/a/7419630/4782503
	mw.libs.revisionSlider.rainbow = function ( numOfSteps, step ) {
		// This function generates vibrant, "evenly spaced" colours (i.e. no clustering). This is ideal for creating easily distinguishable vibrant markers in Google Maps and other apps.
		// Adam Cole, 2011-Sept-14
		// HSV to RBG adapted from: http://mjijackson.com/2008/02/rgb-to-hsl-and-rgb-to-hsv-color-model-conversion-algorithms-in-javascript
		var r, g, b,
			c,
			h = step / numOfSteps,
			i = Math.floor( h * 6 ),
			f = h * 6 - i,
			q = 1 - f;

		switch ( i % 6 ) {
			case 0:
				r = 1;
				g = f;
				b = 0;
				break;
			case 1:
				r = q;
				g = 1;
				b = 0;
				break;
			case 2:
				r = 0;
				g = 1;
				b = f;
				break;
			case 3:
				r = 0;
				g = q;
				b = 1;
				break;
			case 4:
				r = f;
				g = 0;
				b = 1;
				break;
			case 5:
				r = 1;
				g = 0;
				b = q;
				break;
		}
		c = '#' + ( '00' + Math.floor( r * 255 ).toString( 16 ) ).slice( -2 ) + ( '00' + Math.floor( g * 255 ).toString( 16 ) ).slice( -2 ) + ( '00' + Math.floor( b * 255 ).toString( 16 ) ).slice( -2 );

		return c;
	};
}( mediaWiki ) );
