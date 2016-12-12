( function ( mw ) {
	/**
	 * @class mw.UploadWizardEvent
	 * Represents an event to be logged. Used for EventLogging.
	 * @constructor
	 */
	mw.UploadWizardEvent = function () {
	};

	/**
	 * @member {string} schemaName
	 */
	mw.UploadWizardEvent.prototype.schemaName = 'UploadWizardAction';

	/**
	 * @member {string} schemaVersion
	 */
	mw.UploadWizardEvent.prototype.schemaVersion = 0;

	/**
	 * @member {Object} payload
	 */
	mw.UploadWizardEvent.prototype.payload = null;

	/**
	 * Run initialisation tasks.
	 */
	mw.UploadWizardEvent.prototype.init = function () {
		var thisUri;

		this.payload = {};

		this.payload.username = mw.user.getName();
		this.payload.language = mw.config.get( 'wgUserLanguage' );

		thisUri = new mw.Uri( window.location.href, { overrideKeys: true } );
		if ( thisUri.query.uselang ) {
			this.payload.language = thisUri.query.uselang;
		}
	};

	/**
	 * Fires the event to the server - can almost always be fired immediately
	 * after construction.
	 */
	mw.UploadWizardEvent.prototype.dispatch = function () {
		mw.track( 'event.' + this.schemaName, this.payload );
	};

	/**
	 * @class mw.UploadWizardTutorialEvent
	 * @extends mw.UploadWizardEvent
	 * Represents an action on the tutorial page for UploadWizard.
	 * @constructor
	 * @param {string} type Can be 'load', 'helpdesk-click', 'skip-check', 'skip-uncheck', 'continue'
	 */
	mw.UploadWizardTutorialEvent = function ( type ) {
		this.init();
		this.payload.action = type;
	};

	mw.UploadWizardTutorialEvent.prototype = new mw.UploadWizardEvent();

	mw.UploadWizardTutorialEvent.prototype.schemaName = 'UploadWizardTutorialActions';
	mw.UploadWizardTutorialEvent.prototype.schemaVersion = 5803466;
}( mediaWiki ) );
