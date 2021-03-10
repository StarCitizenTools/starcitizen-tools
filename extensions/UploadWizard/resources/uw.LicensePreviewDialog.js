( function ( uw ) {
	uw.LicensePreviewDialog = function UWLicensePreviewDialog( config ) {
		uw.LicensePreviewDialog.parent.call( this, config );
	};
	OO.inheritClass( uw.LicensePreviewDialog, OO.ui.Dialog );

	uw.LicensePreviewDialog.static.name = 'licensePreviewDialog';

	uw.LicensePreviewDialog.prototype.initialize = function () {
		var dialog = this;

		uw.LicensePreviewDialog.parent.prototype.initialize.call( this );

		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		this.$body.append( this.content.$element );
		this.$spinner = $.createSpinner( { size: 'large', type: 'block' } )
			.css( { width: 200, padding: 20, float: 'none', margin: '0 auto' } );

		$( document.body ).on( 'click', function ( e ) {
			if ( !$.contains( dialog.$body.get( 0 ), e.target ) ) {
				dialog.close();
			}
		} );
	};

	uw.LicensePreviewDialog.prototype.addCloseButton = function () {
		var dialog = this,
			closeButton = new OO.ui.ButtonWidget( {
				label: OO.ui.msg( 'ooui-dialog-process-dismiss' )
			} );

		closeButton.on( 'click', function () {
			dialog.close();
		} );

		this.content.$element.append( closeButton.$element );
	};

	uw.LicensePreviewDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	uw.LicensePreviewDialog.prototype.setLoading = function ( isLoading ) {
		if ( isLoading ) {
			this.content.$element.empty().append( this.$spinner );
			this.addCloseButton();
		} else {
			this.content.$element.empty();
		}

		this.updateSize();
	};

	uw.LicensePreviewDialog.prototype.setPreview = function ( html ) {
		this.content.$element.empty().append( html );
		this.addCloseButton();
		this.updateSize();
	};

}( mw.uploadWizard ) );
