( function ( mw, uw, $, OO ) {

	/**
	 * A descriptions field in UploadWizard's "Details" step form.
	 *
	 * @extends uw.DetailsWidget
	 * @mixins OO.ui.mixin.GroupElement
	 */
	uw.DescriptionsDetailsWidget = function UWDescriptionsDetailsWidget( config ) {
		config = $.extend( { required: true }, config );
		uw.DescriptionsDetailsWidget.parent.call( this );
		OO.ui.mixin.GroupElement.call( this );

		this.required = !!config.required;
		this.addDescriptionButton = new OO.ui.ButtonWidget( {
			classes: [ 'mwe-upwiz-details-descriptions-add' ],
			framed: false,
			flags: [ 'constructive' ],
			// Messages: mwe-upwiz-desc-add-0, mwe-upwiz-desc-add-n
			label: mw.msg( 'mwe-upwiz-desc-add-' + ( !this.required ? '0' : 'n' ) )
		} );
		this.addDescriptionButton.connect( this, { click: 'addDescription' } );

		this.$element.addClass( 'mwe-upwiz-descriptionsDetailsWidget' );
		this.$element.append(
			this.$group,
			this.addDescriptionButton.$element
		);

		if ( this.required ) {
			this.addItems( [ new uw.FieldLayout( new uw.DescriptionDetailsWidget() ) ] );
			// Hide the "Remove" button for first description if this field is required
			this.items[ 0 ].$element.next().hide();
		}
	};
	OO.inheritClass( uw.DescriptionsDetailsWidget, uw.DetailsWidget );
	OO.mixinClass( uw.DescriptionsDetailsWidget, OO.ui.mixin.GroupElement );

	/**
	 * Add a description in another language.
	 */
	uw.DescriptionsDetailsWidget.prototype.addDescription = function () {
		this.addItems( [ new uw.FieldLayout( new uw.DescriptionDetailsWidget() ) ] );
		this.recountDescriptions();
	};

	/**
	 * Update the button label after adding or removing descriptions.
	 */
	uw.DescriptionsDetailsWidget.prototype.recountDescriptions = function () {
		// Messages: mwe-upwiz-desc-add-0, mwe-upwiz-desc-add-n
		var label = mw.msg( 'mwe-upwiz-desc-add-' + ( this.items.length === 0 ? '0' : 'n' ) );
		this.addDescriptionButton.setLabel( label );
		this.emit( 'change' );
	};

	/**
	 * @inheritdoc
	 */
	uw.DescriptionsDetailsWidget.prototype.addItems = function ( items, index ) {
		// Mixin method
		OO.ui.mixin.GroupElement.prototype.addItems.call( this, items, index );
		items.forEach( function ( item ) {
			// Insert "Remove" button
			var removeButton = new OO.ui.ButtonWidget( {
				classes: [ 'mwe-upwiz-remove-ctrl', 'mwe-upwiz-descriptionsDetailsWidget-removeItem' ],
				icon: 'remove',
				framed: false,
				flags: [ 'destructive' ],
				title: mw.message( 'mwe-upwiz-remove-description' ).text()
			} );
			removeButton.on( 'click', function () {
				removeButton.$element.remove();
				this.removeItems( [ item ] );
				this.recountDescriptions();
			}.bind( this ) );
			item.$element.after( removeButton.$element );

			// Aggregate 'change' event
			item.fieldWidget.connect( this, { change: [ 'emit', 'change' ] } );
		}.bind( this ) );
		return this;
	};

	/**
	 * @inheritdoc
	 */
	uw.DescriptionsDetailsWidget.prototype.getErrors = function () {
		// Gather errors from each item
		var errorPromises = this.getItems().map( function ( item ) {
			return item.fieldWidget.getErrors();
		} );
		return $.when.apply( $, errorPromises ).then( function () {
			var i, errors;
			errors = [];
			// Fold all errors into a single one (they are displayed in the UI for each item, but we still
			// need to return an error here to prevent form submission).
			for ( i = 0; i < arguments.length; i++ ) {
				if ( arguments[ i ].length ) {
					// One of the items has errors
					errors.push( mw.message( 'mwe-upwiz-error-bad-descriptions' ) );
					break;
				}
			}
			// And add some more:
			if ( this.required && this.getWikiText() === '' ) {
				errors.push( mw.message( 'mwe-upwiz-error-blank' ) );
			}
			// TODO Check for duplicate languages
			return errors;
		}.bind( this ) );
	};

	/**
	 * @inheritdoc
	 */
	uw.DescriptionsDetailsWidget.prototype.getWikiText = function () {
		// Some code here and in mw.UploadWizardDetails relies on this function returning an empty
		// string when there are some descriptions, but all are empty.
		return this.getItems().map( function ( layout ) {
			return layout.fieldWidget.getWikiText();
		} ).filter( function ( wikiText ) {
			return !!wikiText;
		} ).join( '\n' );
	};

	/**
	 * @inheritdoc
	 * @return {Object} See #setSerialized
	 */
	uw.DescriptionsDetailsWidget.prototype.getSerialized = function () {
		var descriptions = this.getItems().map( function ( layout ) {
			return layout.fieldWidget.getSerialized();
		} );
		return {
			descriptions: descriptions
		};
	};

	/**
	 * @inheritdoc
	 * @param {Object} serialized
	 * @param {Object[]} serialized.descriptions Array of serialized descriptions,
	 *   see uw.DescriptionDetailsWidget#setSerialized
	 */
	uw.DescriptionsDetailsWidget.prototype.setSerialized = function ( serialized ) {
		var items = serialized.descriptions.map( function ( serialized ) {
			var layout = new uw.FieldLayout( new uw.DescriptionDetailsWidget() );
			layout.fieldWidget.setSerialized( serialized );
			return layout;
		}.bind( this ) );
		this.clearItems().addItems( items );
		if ( this.required ) {
			// Hide the "Remove" button for first description if this field is required
			this.items[ 0 ].$element.next().hide();
		}
		this.recountDescriptions();
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
