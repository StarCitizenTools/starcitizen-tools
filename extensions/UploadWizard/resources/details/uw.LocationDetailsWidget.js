( function ( uw ) {

	/**
	 * A set of location fields in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {boolean} [showHeading=true] Whether to show the 'heading' field
	 */
	uw.LocationDetailsWidget = function UWLocationDetailsWidget( config ) {
		this.config = config || {};

		uw.LocationDetailsWidget.parent.call( this );

		this.$element.addClass( 'mwe-upwiz-locationDetailsWidget' );

		this.latitudeInput = new OO.ui.TextInputWidget();
		this.longitudeInput = new OO.ui.TextInputWidget();
		this.headingInput = new OO.ui.TextInputWidget();
		this.$map = $( '<div>' ).css( { width: 500, height: 300 } );
		this.mapButton = new OO.ui.PopupButtonWidget( {
			icon: 'mapPin',
			title: mw.message( 'mwe-upwiz-location-button' ).text(),
			popup: {
				$content: this.$map,
				width: 500,
				height: 300
			}
		} );

		this.$element.append(
			new OO.ui.FieldLayout( this.latitudeInput, {
				align: 'top',
				label: mw.message( 'mwe-upwiz-location-lat' ).text()
			} ).$element,
			new OO.ui.FieldLayout( this.longitudeInput, {
				align: 'top',
				label: mw.message( 'mwe-upwiz-location-lon' ).text()
			} ).$element
		);

		if ( this.config.showHeading ) {
			this.$element.append(
				new OO.ui.FieldLayout( this.headingInput, {
					align: 'top',
					label: mw.message( 'mwe-upwiz-location-heading' ).text()
				} ).$element
			);
		}

		this.mapButton.setDisabled( true );
		this.$element.append( this.mapButton.$element );

		// Aggregate 'change' events
		this.latitudeInput.connect( this, { change: [ 'emit', 'change' ] } );
		this.longitudeInput.connect( this, { change: [ 'emit', 'change' ] } );
		this.headingInput.connect( this, { change: [ 'emit', 'change' ] } );

		this.mapButton.connect( this, { click: 'onMapButtonClick' } );
		this.connect( this, { change: 'onChange' } );

		this.mapButton.toggle( false );
		mw.loader.using( [ 'ext.kartographer.box', 'ext.kartographer.editing' ] ).done( function () {
			// Kartographer is installed and we'll be able to show the map. Display the button.
			this.mapButton.toggle( true );
		}.bind( this ) );
	};

	OO.inheritClass( uw.LocationDetailsWidget, uw.DetailsWidget );

	/**
	 * @private
	 */
	uw.LocationDetailsWidget.prototype.onChange = function () {
		var widget = this;
		this.getErrors().done( function ( errors ) {
			widget.mapButton.setDisabled( !( errors.length === 0 && widget.getWikiText() !== '' ) );
		} );
	};

	/**
	 * @private
	 */
	uw.LocationDetailsWidget.prototype.onMapButtonClick = function () {
		var latitude = this.latitudeInput.getValue(),
			longitude = this.longitudeInput.getValue();

		// Disable clipping because it doesn't play nicely with the map
		this.mapButton.getPopup().toggleClipping( false );

		if ( !this.map ) {
			this.map = require( 'ext.kartographer.box' ).map( {
				container: this.$map[ 0 ]
			} );
		}
		require( 'ext.kartographer.editing' ).getKartographerLayer( this.map ).setGeoJSON( {
			type: 'Feature',
			properties: {},
			geometry: { type: 'Point', coordinates: [ longitude, latitude ] }
		} );
		this.map.setView( [ latitude, longitude ], 9 );
	};

	/**
	 * @inheritdoc
	 */
	uw.LocationDetailsWidget.prototype.getErrors = function () {
		var errors = [],
			latInput = this.latitudeInput.getValue(),
			lonInput = this.longitudeInput.getValue(),
			headInput = this.headingInput.getValue(),
			latNum = this.normalizeCoordinate( latInput ),
			lonNum = this.normalizeCoordinate( lonInput ),
			headNum = parseFloat( headInput );

		// input is invalid if the coordinates are out of bounds, or if the
		// coordinates that were derived from the input are 0, without a 0 even
		// being present in the input
		if ( latInput || lonInput ) {
			if ( latNum > 90 || latNum < -90 || ( latNum === 0 && latInput.indexOf( '0' ) < 0 ) || isNaN( latNum ) ) {
				errors.push( mw.message( 'mwe-upwiz-error-latitude' ) );
			}

			if ( lonNum > 180 || lonNum < -180 || ( lonNum === 0 && lonInput.indexOf( '0' ) < 0 ) || isNaN( lonNum ) ) {
				errors.push( mw.message( 'mwe-upwiz-error-longitude' ) );
			}
		}

		if ( headInput !== '' && ( headInput > 360 || headInput < 0 || isNaN( headNum ) ) ) {
			errors.push( mw.message( 'mwe-upwiz-error-heading' ) );
		}

		return $.Deferred().resolve( errors );
	};

	/**
	 * Set up the input fields.
	 *
	 * @param {string} [lat] Latitude value to set.
	 * @param {string} [lon] Longitude value to set.
	 * @param {string} [head] Heading value to set.
	 * @private
	 */
	uw.LocationDetailsWidget.prototype.setupInputs = function ( lat, lon, head ) {
		if ( lat !== undefined ) {
			this.latitudeInput.setValue( lat );
		}

		if ( lon !== undefined ) {
			this.longitudeInput.setValue( lon );
		}

		if ( head !== undefined ) {
			this.headingInput.setValue( head );
		}
	};

	/**
	 * @inheritdoc
	 */
	uw.LocationDetailsWidget.prototype.getWikiText = function () {
		var locationParts,
			latInput = this.latitudeInput.getValue(),
			lonInput = this.longitudeInput.getValue(),
			headInput = this.headingInput.getValue(),
			latNum = this.normalizeCoordinate( latInput ),
			lonNum = this.normalizeCoordinate( lonInput ),
			headNum = parseFloat( headInput );

		// input is invalid if the coordinates are out of bounds, or if the
		// coordinates that were derived from the input are 0, without a 0 even
		// being present in the input
		if ( latNum !== 0 || latInput.indexOf( '0' ) >= 0 || lonNum !== 0 || lonInput.indexOf( '0' ) >= 0 ) {
			locationParts = [ '{{Location', latNum, lonNum ];

			if ( !isNaN( headNum ) ) {
				locationParts.push( 'heading:' + headNum );
			}

			return locationParts.join( '|' ) + '}}';
		}

		return '';
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.LocationDetailsWidget.prototype.getSerialized = function () {
		return {
			latitude: this.latitudeInput.getValue(),
			longitude: this.longitudeInput.getValue(),
			heading: this.headingInput.getValue()
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {string} serialized.latitude Latitude value
	 * @param {string} serialized.longitude Longitude value
	 * @param {string} serialized.heading Heading value
	 */
	uw.LocationDetailsWidget.prototype.setSerialized = function ( serialized ) {
		this.setupInputs( serialized.latitude, serialized.longitude, serialized.heading );
	};

	/**
	 * Interprets a wide variety of coordinate input formats, it'll return the
	 * coordinate in decimal degrees.
	 *
	 * Formats understood include:
	 * * degrees minutes seconds: 40° 26' 46" S
	 * * degrees decimal minutes: 40° 26.767' S
	 * * decimal degrees: 40.446° S
	 * * decimal degrees exact value: -40.446
	 *
	 * @param {string} coordinate
	 * @return {number}
	 */
	uw.LocationDetailsWidget.prototype.normalizeCoordinate = function ( coordinate ) {
		var sign = coordinate.match( /[sw]/i ) ? -1 : 1,
			parts, value;

		// fix commonly used character alternatives
		coordinate = coordinate.replace( /\s*[,.]\s*/, '.' );

		// convert degrees, minutes, seconds (or degrees & decimal minutes) to
		// decimal degrees
		// there can be a lot of variation in the notation, so let's only
		// focus on "groups of digits" (and not whether e.g. ″ or " is used)
		parts = coordinate.match( /(-?[0-9.]+)[^0-9.]+([0-9.]+)(?:[^0-9.]+([0-9.]+))?/ );
		if ( parts ) {
			value = this.dmsToDecimal( parts[ 1 ], parts[ 2 ], parts[ 3 ] || 0 );
		} else {
			value = coordinate.replace( /[^\-0-9.]/g, '' ) * 1;
		}

		// round to 6 decimal places
		return Math.round( sign * value * 1000000 ) / 1000000;
	};

	/**
	 * Convert degrees, minutes & seconds to decimal.
	 *
	 * @param {number} degrees
	 * @param {number} minutes
	 * @param {number} seconds
	 * @return {number}
	 */
	uw.LocationDetailsWidget.prototype.dmsToDecimal = function ( degrees, minutes, seconds ) {
		return ( degrees * 1 ) + ( minutes / 60.0 ) + ( seconds / 3600.0 );
	};

}( mw.uploadWizard ) );
