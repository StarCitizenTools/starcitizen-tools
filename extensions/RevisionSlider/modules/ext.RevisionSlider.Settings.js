( function ( mw, $ ) {
	/**
	 * @constructor
	 */
	var Settings = function () {
		this.hideHelpDialogue = this.loadBoolean( 'hide-help-dialogue' );
		this.autoExpand = this.loadBoolean( 'autoexpand' );
	};

	$.extend( Settings.prototype, {
		/**
		 * @type {boolean}
		 */
		hideHelpDialogue: null,

		/**
		 * @type {boolean}
		 */
		autoExpand: null,

		/**
		 * @return {boolean}
		 */
		shouldHideHelpDialogue: function () {
			return this.hideHelpDialogue;
		},

		/**
		 * @return {boolean}
		 */
		shouldAutoExpand: function () {
			return this.autoExpand;
		},

		/**
		 * @param {boolean} newSetting
		 */
		setHideHelpDialogue: function ( newSetting ) {
			if ( newSetting !== this.hideHelpDialogue ) {
				this.saveBoolean( 'hide-help-dialogue', newSetting );
				this.hideHelpDialogue = newSetting;
			}
		},

		/**
		 * @param {boolean} newSetting
		 */
		setAutoExpand: function ( newSetting ) {
			if ( newSetting !== this.autoExpand ) {
				this.saveBoolean( 'autoexpand', newSetting );
				this.autoExpand = newSetting;
			}
		},

		/**
		 * @param {string} name
		 * @param {string} defaultValue
		 * @return {string|boolean}
		 */
		loadSetting: function ( name, defaultValue ) {
			var setting;
			if ( !mw.user.isAnon() ) {
				setting = mw.user.options.get( 'userjs-revslider-' + name );
			} else {
				setting = mw.storage.get( 'mw-revslider-' + name );
				if ( !setting ) {
					setting = mw.cookie.get( '-revslider-' + name );
				}
			}

			return setting !== null && setting !== false ? setting : defaultValue;
		},

		/**
		 * @param {string} name
		 * @param {boolean} [defaultValue]
		 * @return {boolean}
		 */
		loadBoolean: function ( name, defaultValue ) {
			return this.loadSetting( name, defaultValue ? '1' : '0' ) === '1';
		},

		/**
		 * @param {string} name
		 * @param {string} value
		 */
		saveSetting: function ( name, value ) {
			if ( !mw.user.isAnon() ) {
				( new mw.Api() ).saveOption( 'userjs-revslider-' + name, value );
			} else {
				if ( !mw.storage.set( 'mw-revslider-' + name, value ) ) {
					mw.cookie.set( '-revslider-' + name, value ); // use cookie when localStorage is not available
				}
			}
		},

		/**
		 * @param {string} name
		 * @param {boolean} value
		 */
		saveBoolean: function ( name, value ) {
			this.saveSetting( name, value ? '1' : '0' );
		}
	} );

	mw.libs.revisionSlider = mw.libs.revisionSlider || {};
	mw.libs.revisionSlider.Settings = Settings;
}( mediaWiki, jQuery ) );
