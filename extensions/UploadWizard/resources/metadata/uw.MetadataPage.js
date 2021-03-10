/**
 * @external StatementWidget
 */
( function ( uw ) {

	'use strict';

	/**
	 * @constructor
	 * @param {mw.UploadWizardUpload} upload
	 * @param {Object} [config] Configuration options
	 */
	uw.MetadataPage = function UWMetadataPage( upload, config ) {
		var self = this;

		uw.MetadataPage.parent.call( this, upload.getFilename(), config );

		this.upload = upload;
		this.metadataContent = config.content.filter( function ( content ) {
			return content instanceof uw.MetadataContent;
		} ).shift();
		this.$thumbnailDiv = $( '<div>' ).addClass( 'mwe-upwiz-metadata-page-thumbnail' );

		this.upload.getThumbnail( 30, 30 ).done( function ( thumb ) {
			mw.UploadWizard.placeThumbnail( self.$thumbnailDiv, thumb );
		} );
	};

	OO.inheritClass( uw.MetadataPage, OO.ui.PageLayout );

	/**
	 * @inheritdoc
	 */
	uw.MetadataPage.prototype.setupOutlineItem = function ( outlineItem ) {
		var filename = this.upload.details ?
			this.upload.details.getTitle().getMain() :
			this.upload.getFilename();

		uw.MetadataPage.parent.prototype.setupOutlineItem.call( this, outlineItem );

		this.outlineItem.$element.addClass( 'mwe-upwiz-metadata-page' );
		this.outlineItem.setLabel(
			this.$thumbnailDiv.append(
				$( '<span>' ).addClass( 'mwe-upwiz-thumbnail-filename' ).text( filename )
			)
		);
	};

	/**
	 * Get the statementWidgets within the page's MetadataContent child
	 * element.
	 *
	 * @return {StatementWidget[]} Statement widgets
	 */
	uw.MetadataPage.prototype.getStatements = function () {
		return this.metadataContent.getStatements();
	};

	uw.MetadataPage.prototype.applyCopiedStatements = function ( statements ) {
		this.metadataContent.applyCopiedStatements( statements );
	};

}( mw.uploadWizard ) );
