( function ( mw, uw, $, OO ) {

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

		// Aggregate 'change' events
		this.latitudeInput.connect( this, { change: [ 'emit', 'change' ] } );
		this.longitudeInput.connect( this, { change: [ 'emit', 'change' ] } );
		this.headingInput.connect( this, { change: [ 'emit', 'change' ] } );
	};

	OO.inheritClass( uw.LocationDetailsWidget, uw.DetailsWidget );

	/**
	 * @inheritdoc
	 */
	uw.LocationDetailsWidget.prototype.getErrors = function () {
		var errors = [],
			lat = this.latitudeInput.getValue(),
			lon = this.longitudeInput.getValue(),
			head = this.headingInput.getValue(),
			latNum = parseInt( lat, 10 ),
			lonNum = parseInt( lon, 10 ),
			headNum = parseInt( head, 10 );

		if ( lat !== '' && ( lat > 90 || lat < -90 || isNaN( latNum ) ) ) {
			errors.push( mw.message( 'mwe-upwiz-error-latitude' ) );
		}

		if ( lon !== '' && ( lon > 180 || lon < -180 || isNaN( lonNum ) ) ) {
			errors.push( mw.message( 'mwe-upwiz-error-longitude' ) );
		}

		if ( head !== '' && ( head > 360 || head < 0 || isNaN( headNum ) ) ) {
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
			latitude = this.latitudeInput.getValue(),
			longitude = this.longitudeInput.getValue(),
			heading = this.headingInput.getValue(),
			latnum = parseFloat( latitude ),
			longnum = parseFloat( longitude ),
			headnum = parseFloat( heading );

		if ( !isNaN( latnum ) && !isNaN( longnum ) ) {
			locationParts = [ '{{Location', latitude, longitude ];

			if ( !isNaN( headnum ) ) {
				locationParts.push( 'heading:' + heading );
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

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
