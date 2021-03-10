/**
 * @external wikibaseDatamodel
 * @external StatementWidget
 */
( function ( uw ) {
	'use strict';

	/*
	 * External dependencies (WikibaseMediaInfo, Wikibase datamodel & config vars) are a
	 * soft dependency and may not be available here. We'll attempt to lazy-load them from
	 * uw.MetadataContent constructor and will then populate these vars on success.
	 */
	var AddPropertyWidget,
		StatementWidget,
		dataTypesMap,
		propertyDataValuesTypes,
		defaultProperties,
		propertyTypes,
		wikibaseDatamodel;

	/**
	 * @constructor
	 * @param {mw.UploadWizardUpload} upload
	 * @param {Object} [config] Configuration options
	 */
	uw.MetadataContent = function UWMetadataContent( upload, config ) {
		var $titleDiv,
			$filenameDiv,
			$thumbnailDiv,
			self = this;

		uw.MetadataContent.parent.call(
			this,
			$.extend( { classes: [ 'mwe-upwiz-metadata-content' ] }, config )
		);

		// Set up widget data
		this.upload = upload;
		this.entityId = undefined;
		this.statementWidgets = {};

		// Build the UI
		$titleDiv = $( '<h2>' )
			.addClass( 'mwe-upwiz-metadata-content-caption' )
			.text( upload.details.getThumbnailCaption() );
		$filenameDiv = $( '<p>' )
			.addClass( 'mwe-upwiz-metadata-content-filename' )
			.text( upload.details.getTitle().getMain() );
		$thumbnailDiv = $( '<div>' )
			.addClass( 'mwe-upwiz-metadata-content-thumbnail' );
		this.$statementsDiv = $( '<div>' )
			.addClass( 'mwe-upwiz-metadata-content-statements' );

		// Copy all button
		this.copyAllStatementsButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-copy-statements-button' ).text(),
			disabled: true,
			classes: [ 'mwe-upwiz-metadata-content-copy-all' ]
		} );

		this.copyAllStatementsButton.connect( this, { click: 'copyStatementsToAllFiles' } );

		// Load thumbnail and append elements to page once ready
		upload.getThumbnail( 630, 360 ).then( function ( thumb ) {
			mw.UploadWizard.placeThumbnail( $thumbnailDiv, thumb );
			self.$element.prepend(
				$titleDiv,
				$filenameDiv,
				$thumbnailDiv
			);
		} );

		// Call the setup method and append statementWidgets once ready
		this.$element.append( this.$statementsDiv );
		mw.loader.using( [
			'wikibase.mediainfo.statements',
			'wikibase.datamodel'
		] ).then( function ( require ) {
			AddPropertyWidget = require( 'wikibase.mediainfo.statements' ).AddPropertyWidget;
			StatementWidget = require( 'wikibase.mediainfo.statements' ).StatementWidget;
			wikibaseDatamodel = require( 'wikibase.datamodel' );
			dataTypesMap = mw.config.get( 'wbDataTypes' );
			defaultProperties = mw.config.get( 'wbmiDefaultProperties' ) || [];
			propertyTypes = mw.config.get( 'wbmiPropertyTypes' ) || {};
			propertyDataValuesTypes = [];

			Object.keys( propertyTypes ).forEach( function ( propertyId ) {
				propertyDataValuesTypes[ propertyId ] = dataTypesMap[ propertyTypes[ propertyId ] ].dataValueType;
			} );

			( mw.UploadWizard.config.defaults.statements || [] ).forEach( function ( data ) {
				if ( defaultProperties.indexOf( data.propertyId ) < 0 ) {
					defaultProperties.push( data.propertyId );
				}
				propertyDataValuesTypes[ data.propertyId ] = data.dataType;
			} );

			Object.freeze( defaultProperties );
		} ).then( self.setup() ).then( function () {
			Object.keys( self.statementWidgets ).forEach( function ( propertyId ) {
				var statementWidget = self.statementWidgets[ propertyId ];
				self.$statementsDiv.append( statementWidget.$element );
			} );

			self.createAddPropertyWidgetIfNecessary();

			if ( config.allowCopy ) {
				self.$element.append( self.copyAllStatementsButton.$element );
			}
		} );
	};

	OO.inheritClass( uw.MetadataContent, OO.ui.Widget );

	/**
	 * Setup method. Fetch the mediainfo ID of the relevant file and create
	 * widgets for default statements. If default statements are added,
	 * a "change" event is emitted so that the user can immediately publish
	 * using the suggested data.
	 *
	 * @return {jQuery.Promise}
	 * @fires change
	 */
	uw.MetadataContent.prototype.setup = function () {
		var self = this;

		return this.upload.details.getMediaInfoEntityId().then( function ( entityId ) {
			self.entityId = entityId;

			// Create a statement widget for each default property and set its datatype
			defaultProperties.forEach( function ( propertyId ) {
				var defaultData = self.getDefaultDataForProperty( propertyId ),
					widget = self.createStatementWidget(
						propertyId,
						propertyDataValuesTypes[ propertyId ],
						defaultData
					);

				self.$statementsDiv.append( widget.$element );

				// pre-populate statements with data if necessary (campaigns only);
				// default values are still considered "changes" to be published
				if ( !defaultData.isEmpty() ) {
					self.emit( 'change' );
				}
			} );
		} );
	};

	/**
	 * @param {string} propertyId
	 * @return {wikibaseDatamodel.StatementList}
	 */
	uw.MetadataContent.prototype.getDefaultDataForProperty = function ( propertyId ) {
		var defaultStatements = mw.UploadWizard.config.defaults.statements,
			defaultData;

		if ( !defaultStatements ) {
			return new wikibaseDatamodel.StatementList();
		}

		defaultData = defaultStatements.filter( function ( statement ) {
			return statement.propertyId === propertyId && statement.values;
		} )[ 0 ];

		if ( !defaultData || !defaultData.values ) {
			return new wikibaseDatamodel.StatementList();
		}

		return new wikibaseDatamodel.StatementList(
			defaultData.values.map( function ( itemId ) {
				return new wikibaseDatamodel.Statement(
					new wikibaseDatamodel.Claim(
						new wikibaseDatamodel.PropertyValueSnak(
							propertyId,
							new wikibaseDatamodel.EntityId( itemId )
						),
						null,
						null
					)
				);
			} )
		);
	};

	/**
	 * Creates and sets up the AddPropertyWidget if other statements are enabled.
	 */
	uw.MetadataContent.prototype.createAddPropertyWidgetIfNecessary = function () {
		// let's always create the widget, that way we don't have to check for its
		// existence everywhere - but we won't add it to DOM if it's not wanted
		this.addPropertyWidget = new AddPropertyWidget( {
			propertyIds: Object.keys( this.statementWidgets )
		} );

		this.addPropertyWidget.connect( this, { choose: 'onPropertyAdded' } );

		if ( mw.UploadWizard.config.wikibase.nonDefaultStatements !== false ) {
			this.$element.append( this.addPropertyWidget.$element );
		}
	};

	/**
	 * Creates and returns a new StatementWidget, with appropriate event handlers
	 * attached. Also stashes the statement's property ID.
	 *
	 * @param {string} propId P123, etc.
	 * @param {string} valueType 'wikibase-entityid', 'string', etc.
	 * @param {wikibaseDatamodel.Statement} [data]
	 * @return {Object} StatementWidget
	 */
	uw.MetadataContent.prototype.createStatementWidget = function ( propId, valueType, data ) {
		var self = this,
			widget;

		data = data || new wikibaseDatamodel.StatementList();

		widget = new StatementWidget( {
			editing: true,
			entityId: this.entityId,
			propertyId: propId,
			valueType: valueType,
			isDefaultProperty: defaultProperties.indexOf( propId ) >= 0,
			helpUrls: mw.config.get( 'wbmiHelpUrls' ) || {}
		} );

		// don't start subscribing to events until statementwidget has been
		// pre-populated with initial data
		widget.setData( data ).then( function () {
			widget.connect( self, { widgetRemoved: 'onStatementWidgetRemoved' } );
			widget.connect( self, { change: 'enableCopyAllButton' } );
			widget.connect( self, { change: [ 'emit', 'change' ] } );
		} );

		this.statementWidgets[ propId ] = widget;
		return widget;
	};

	/**
	 * @param {Object.<StatementWidget>} statementWidgets Map of { property id: StatementWidget }
	 */
	uw.MetadataContent.prototype.applyCopiedStatements = function ( statementWidgets ) {
		var self = this;

		// 1. remove all existing statementWidgets
		Object.keys( this.statementWidgets ).forEach( function ( propId ) {
			self.onStatementWidgetRemoved( propId );
		} );

		// 2. re-create statement widgets for each PID in the statementWidgets array
		// NOTE: this currently loses "default-ness"
		Object.keys( statementWidgets ).forEach( function ( propertyId ) {
			var statementWidget = statementWidgets[ propertyId ],
				sourceData = statementWidget.getData(),
				targetData,
				valueType;

			if ( sourceData.isEmpty() ) {
				// skip empty widgets
				return;
			}

			// construct a new StatementList which is a copy of the existing list,
			// just a new instance (like, different GUID)
			targetData = new wikibaseDatamodel.StatementList(
				sourceData.toArray().map( function ( statement ) {
					return new wikibaseDatamodel.Statement(
						new wikibaseDatamodel.Claim(
							statement.getClaim().getMainSnak(),
							statement.getClaim().getQualifiers(),
							null
						),
						statement.getReferences(),
						statement.getRank()
					);
				} )
			);

			valueType = sourceData.toArray()[ 0 ].getClaim().getMainSnak().getValue().getType();

			self.createStatementWidget( propertyId, valueType, targetData );
		} );

		// 3. Append newly-copied statementWidgets to the page
		Object.keys( this.statementWidgets ).forEach( function ( propertyId ) {
			var statementWidget = self.statementWidgets[ propertyId ];
			self.$statementsDiv.append( statementWidget.$element );
		} );
	};

	/**
	 * Handles the selection by user of a new property to add to page. Creates a
	 * new appropriate StatementWidget, updates internal datatype map, and
	 * appends widget to DOM.
	 *
	 * @param {Object} item
	 * @param {Object} data
	 */
	uw.MetadataContent.prototype.onPropertyAdded = function ( item, data ) {
		var propertyId = data.id,
			statementWidget;

		statementWidget = this.createStatementWidget( propertyId, dataTypesMap[ data.datatype ].dataValueType );
		this.addPropertyWidget.$element.before( statementWidget.$element );
	};

	/**
	 * Handles the removal of StatementWidgets.
	 * Also cleans up the propertyID and statementWidget arrays.
	 *
	 * @param {string} propertyId
	 * @throws {Error} Raises error if any item to remove is not found
	 */
	uw.MetadataContent.prototype.onStatementWidgetRemoved = function ( propertyId ) {
		var removedWidget;

		if ( !( propertyId in this.statementWidgets ) ) {
			throw new Error( 'Statement widget for property ' + propertyId + ' not found' );
		}
		removedWidget = this.statementWidgets[ propertyId ];

		removedWidget.$element.remove();
		delete this.statementWidgets[ propertyId ];
		this.addPropertyWidget.onStatementPanelRemoved( propertyId );
	};

	uw.MetadataContent.prototype.copyStatementsToAllFiles = function () {
		var self = this;

		OO.ui.confirm(
			mw.msg( 'mwe-upwiz-copy-statements-dialog' ),
			{
				actions: [
					{
						action: 'accept',
						label: mw.msg( 'mwe-upwiz-copy-statements-dialog-accept' ),
						flags: [ 'primary', 'progressive' ]
					},
					{
						action: 'reject',
						label: mw.msg( 'ooui-dialog-message-reject' ),
						flags: 'safe'
					}
				]
			}
		).then( function ( confirmed ) {
			var statements = self.getStatements();
			if ( confirmed ) {
				self.emit( 'copyToAll', statements, self.upload.file.name );
				self.disableCopyAllButton();
			}
		} );
	};

	uw.MetadataContent.prototype.enableCopyAllButton = function () {
		this.copyAllStatementsButton.setLabel( mw.message( 'mwe-upwiz-copy-statements-button' ).text() );
		this.copyAllStatementsButton.setDisabled( false );
	};

	uw.MetadataContent.prototype.disableCopyAllButton = function () {
		this.copyAllStatementsButton.setLabel( mw.message( 'mwe-upwiz-copy-statements-button-done' ).text() );
		this.copyAllStatementsButton.setDisabled( true );
	};

	/**
	 * @return {Object.<StatementWidget>} Map of { property id: StatementWidget }
	 */
	uw.MetadataContent.prototype.getStatements = function () {
		return this.statementWidgets;
	};

}( mw.uploadWizard ) );
