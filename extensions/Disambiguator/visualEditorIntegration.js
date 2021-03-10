/*!
 * VisualEditor DataModel MWDisambiguationMetaItem class.
 *
 * @copyright 2011-2014 VisualEditor Team
 * @license The MIT License (MIT); see COPYING
 */

/*global ve, OO, mw*/

/**
 * DataModel disambiguation meta item (for __DISAMBIG__).
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWDisambiguationMetaItem = function VeDmMWDisambiguationMetaItem( element ) {
	// Parent constructor
	ve.dm.MetaItem.call( this, element );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWDisambiguationMetaItem, ve.dm.MetaItem );

/* Static Properties */

ve.dm.MWDisambiguationMetaItem.static.name = 'mwDisambiguation';

ve.dm.MWDisambiguationMetaItem.static.group = 'mwDisambiguation';

ve.dm.MWDisambiguationMetaItem.static.matchTagNames = [ 'meta' ];

ve.dm.MWDisambiguationMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/disambiguation' ];

ve.dm.MWDisambiguationMetaItem.static.toDataElement = function () {
	return { 'type': this.name };
};

ve.dm.MWDisambiguationMetaItem.static.toDomElements = function ( dataElement, doc ) {
	var meta = doc.createElement( 'meta' );
	meta.setAttribute( 'property', 'mw:PageProp/disambiguation' );
	return [ meta ];
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWDisambiguationMetaItem );

ve.ui.MWSettingsPage.static.addMetaCheckbox(
	'mwDisambiguation',
	mw.msg( 'visualeditor-dialog-meta-settings-disambiguation-label' )
);
