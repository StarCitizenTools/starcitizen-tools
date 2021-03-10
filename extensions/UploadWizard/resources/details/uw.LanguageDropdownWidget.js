( function ( uw ) {

	/**
	 * A language dropdown within a description field in UploadWizard's "Details" step form.
	 *
	 * @constructor
	 * @param {Object} [config]
	 */
	uw.LanguageDropdownWidget = function UWLanguageDropdownWidget( config ) {
		config = config || {};

		uw.LanguageDropdownWidget.parent.call( this );

		this.languageDropdown = new OO.ui.DropdownWidget( {
			menu: { items: this.getLanguageMenuOptionWidgets( config.languages ) },
			classes: config.classes
		} );
		this.languageDropdown.getMenu().connect( this, { select: [ 'emit', 'select' ] } );
	};
	OO.inheritClass( uw.LanguageDropdownWidget, OO.ui.Widget );
	OO.mixinClass( uw.LanguageDropdownWidget, OO.EventEmitter );

	/**
	 * @param {Object} languages
	 */
	uw.LanguageDropdownWidget.prototype.updateLanguages = function ( languages ) {
		var menu = this.languageDropdown.getMenu(),
			currentMenuItems = menu.getItems(),
			currentValue = this.getValue();

		// remove all items except the one currently selected (don't want
		// to trigger another select by removing it)
		menu.removeItems( currentMenuItems.filter( function ( item ) {
			return !item.isSelected();
		} ) );

		// and add the rest of the languages back in there
		delete languages[ currentValue ];
		menu.addItems( this.getLanguageMenuOptionWidgets( languages ) );
	};

	/**
	 * @param {string} value
	 */
	uw.LanguageDropdownWidget.prototype.setValue = function ( value ) {
		this.languageDropdown.getMenu().selectItemByData( value );
	};

	/**
	 * @return {string}
	 */
	uw.LanguageDropdownWidget.prototype.getValue = function () {
		return this.languageDropdown.getMenu().findSelectedItem().getData();
	};

	/**
	 * @return {OO.ui.DropdownWidget}
	 */
	uw.LanguageDropdownWidget.prototype.getElement = function () {
		return this.languageDropdown.$element;
	};

	/**
	 * Get options for the dropdown list of all allowed languages.
	 *
	 * @private
	 * @param {Object} languages
	 * @return {OO.ui.MenuOptionWidget[]}
	 */
	uw.LanguageDropdownWidget.prototype.getLanguageMenuOptionWidgets = function ( languages ) {
		return Object.keys( languages ).map( function ( code ) {
			return new OO.ui.MenuOptionWidget( {
				data: code,
				label: languages[ code ]
			} );
		} );
	};

}( mw.uploadWizard ) );
