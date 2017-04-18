<?php
/**
 * UploadWizard extension
 *
 * @file
 * @ingroup Extensions
 *
 * This file contains the include file for UploadWizard
 *
 * Usage: Include the following line in your LocalSettings.php
 * require_once( "$IP/extensions/UploadWizard/UploadWizard.php" );
 *
 * @author Neil Kandalgaonkar <neil@wikimedia.org>
 * @author Mark Holmquist <mtraceur@member.fsf.org>
 * @author Jeroen De Dauw <jeroendedauw@gmail.com>
 * @author Ryan Kaldari <rkaldari@wikimedia.org>
 * @author Michael Dale <mdale@wikimedia.org>
 * @author Ankur Anand <drecodeam@gmail.com>
 * @author Yuvi Panda <yuvipanda@gmail.com>
 * @license GPL v2 or later
 * @version 1.5
 */

/* Configuration */

// Credits
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Upload Wizard',
	'author' => array(
		'Neil Kandalgaonkar',
		'Jeroen De Dauw',
		'Mark Holmquist',
		'Ryan Kaldari',
		'Michael Dale',
		'Ankur Anand',
		'Nischay Nahata',
		'Yuvi Panda'
	),
	'version' => '1.5.0',
	'descriptionmsg' => 'uploadwizard-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:UploadWizard',
	'license-name' => 'GPL-2.0+'
);

$wgMessagesDirs['UploadWizard'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['UploadWizardAlias'] = __DIR__ . '/UploadWizard.alias.php';

# Require modules
$wgAutoloadClasses += array(
	// Hooks
	'UploadWizardHooks' => __DIR__ . '/UploadWizardHooks.php',

	// Includes
	'UploadWizardConfig' => __DIR__ . '/includes/UploadWizardConfig.php',
	'UploadWizardTutorial' => __DIR__ . '/includes/UploadWizardTutorial.php',
	'UploadWizardCampaign' => __DIR__ . '/includes/UploadWizardCampaign.php',
	'UploadWizardFlickrBlacklist' => __DIR__ . '/includes/UploadWizardFlickrBlacklist.php',

	// Campaign ContentHandler
	'CampaignContentHandler' => __DIR__ . '/includes/CampaignContentHandler.php',
	'CampaignContent' => __DIR__ . '/includes/CampaignContent.php',
	'CampaignPageFormatter' => __DIR__ . '/includes/CampaignPageFormatter.php',
	'CampaignHooks' => __DIR__ . '/includes/CampaignHooks.php',

	// Special Pages
	'SpecialUploadWizard' => __DIR__ . '/includes/specials/SpecialUploadWizard.php',
	'SpecialCampaigns' => __DIR__ . '/includes/specials/SpecialCampaigns.php',

	// API
	'ApiQueryAllCampaigns' => __DIR__ . '/includes/ApiQueryAllCampaigns.php',
	'ApiFlickrBlacklist' => __DIR__ . '/includes/ApiFlickrBlacklist.php',

	// Appease the tests
	'UploadWizardSimpleForm' => __DIR__ . '/includes/specials/SpecialUploadWizard.php',
);

$wgAPIListModules['allcampaigns'] = 'ApiQueryAllCampaigns';
// $wgAPIModules['titlecheck'] = 'ApiTitleCheck';
// $wgAPIListModules['titlecheck'] = 'ApiTitleCheck';
$wgAPIModules['flickrblacklist'] = 'ApiFlickrBlacklist';

# Let the special page be a special center of unique specialness
$wgSpecialPages['UploadWizard'] = 'SpecialUploadWizard';
$wgSpecialPages['Campaigns'] = 'SpecialCampaigns';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'UploadWizardHooks::onSchemaUpdate';
$wgHooks['GetPreferences'][] = 'UploadWizardHooks::onGetPreferences';
$wgHooks['IsUploadAllowedFromUrl'][] = 'UploadWizardHooks::onIsUploadAllowedFromUrl';
$wgHooks['ResourceLoaderTestModules'][] = 'UploadWizardHooks::onResourceLoaderTestModules';
$wgHooks['UnitTestsList'][] = 'UploadWizardHooks::onUnitTestsList';

$uploadWizardModuleInfo = array(
	'localBasePath' => __DIR__ . '/resources',
	'remoteExtPath' => 'UploadWizard/resources',
);

$wgResourceModules['ext.uploadWizard.formDataTransport'] = array(
	'scripts' => 'transports/mw.FormDataTransport.js',

	'dependencies' => array(
		'oojs',
	),
) + $uploadWizardModuleInfo;

$wgResourceModules['ext.uploadWizard.apiUploadPostHandler'] = array(
	'scripts' => 'handlers/mw.ApiUploadPostHandler.js',
	'dependencies' => 'uw.EventFlowLogger',
	'messages' => 'mwe-upwiz-transport-started',
) + $uploadWizardModuleInfo;

// upload using FormData, large files in chunks
$wgResourceModules['ext.uploadWizard.apiUploadFormDataHandler'] = array(
	'scripts' => 'handlers/mw.ApiUploadFormDataHandler.js',
	'dependencies' => [
		'uw.EventFlowLogger',
		'ext.uploadWizard.formDataTransport',
	],
	'messages' => 'mwe-upwiz-transport-started',
) + $uploadWizardModuleInfo;

$wgResourceModules['ext.uploadWizard.page'] = array(
	'scripts' => 'mw.UploadWizardPage.js',
	'dependencies' => 'ext.uploadWizard'
) + $uploadWizardModuleInfo;

$wgResourceModules['ext.uploadWizard.page.styles'] = array(
	'styles' => 'uploadWizard.noWizard.css',
) + $uploadWizardModuleInfo;

$wgResourceModules['ext.uploadWizard.uploadCampaign.display'] = array(
	'styles' => 'ext.uploadWizard.uploadCampaign.display.css',
	'position' => 'top',
	'dependencies' => 'mediawiki.ui.button'
) + $uploadWizardModuleInfo;

$wgResourceModules['ext.uploadWizard.uploadCampaign.list'] = array(
	'styles' => 'ext.uploadWizard.uploadCampaign.list.css',
	'position' => 'top'
) + $uploadWizardModuleInfo;

$wgResourceModules += array(
	'uw.EventFlowLogger' => array(
		'scripts' => array(
			'resources/uw.EventFlowLogger.js',
		),
		'dependencies' => array(
			'uw.base',
			'json',
			'es5-shim',
			'oojs',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'ext.uploadWizard' => array(
		'dependencies' => array(
			'jquery.arrowSteps',
			'jquery.checkboxShiftClick',
			'jquery.client',
			'jquery.spinner',
			'jquery.ui.selectable',
			'jquery.placeholder',
			'jquery.makeCollapsible',
			'mediawiki.icon',
			'mediawiki.Uri',
			'mediawiki.util',
			'mediawiki.libs.jpegmeta',
			'mediawiki.jqueryMsg',
			'mediawiki.api',
			'mediawiki.api.edit',
			'mediawiki.api.parse',
			'mediawiki.confirmCloseWindow',
			'mediawiki.Title',
			'mediawiki.user',
			'mediawiki.feedback',
			'moment',
			'uw.base',
			'ext.uploadWizard.apiUploadPostHandler',
			'ext.uploadWizard.apiUploadFormDataHandler',
			'ext.uploadWizard.events',

			'uw.EventFlowLogger',
			'uw.ui.Wizard',

			'uw.controller.Deed',
			'uw.controller.Details',
			'uw.controller.Thanks',
			'uw.controller.Tutorial',
			'uw.controller.Upload',

			'es5-shim',
			'oojs',
			'oojs-ui',
			'oojs-ui.styles.icons',
			'oojs-ui.styles.icons-editing-core',
			'oojs-ui.styles.icons-editing-advanced',
			'mediawiki.widgets.DateInputWidget',
			'mediawiki.widgets.CategorySelector',
		),
		'scripts' => array(
			// jquery interface helpers
			'resources/jquery/jquery.morphCrossfade.js',
			'resources/jquery/jquery.lazyload.js',

			// OOjs UI interface elements
			'resources/uw.DetailsWidget.js',
			'resources/uw.FieldLayout.js',
			'resources/details/uw.TitleDetailsWidget.js',
			'resources/details/uw.DateDetailsWidget.js',
			'resources/details/uw.CategoriesDetailsWidget.js',
			'resources/details/uw.DeedChooserDetailsWidget.js',
			'resources/details/uw.DescriptionDetailsWidget.js',
			'resources/details/uw.DescriptionsDetailsWidget.js',
			'resources/details/uw.LocationDetailsWidget.js',
			'resources/details/uw.OtherDetailsWidget.js',
			'resources/details/uw.CampaignDetailsWidget.js',

			// common utilities
			'resources/mw.fileApi.js',
			'resources/mw.units.js',
			'resources/mw.canvas.js',
			'resources/mw.errorDialog.js',

			// title validity checks
			'resources/mw.DestinationChecker.js',
			'resources/mw.QuickTitleChecker.js',

			// firefogg support libraries
			'resources/mw.Firefogg.js',
			'resources/handlers/mw.FirefoggHandler.js',
			'resources/transports/mw.FirefoggTransport.js',

			// flickr libraries
			'resources/mw.FlickrChecker.js',

			// interface libraries
			'resources/mw.GroupProgressBar.js',

			// UploadWizard specific abstractions
			'resources/mw.UploadWizardDeed.js',
			'resources/mw.UploadWizardDeedOwnWork.js',
			'resources/mw.UploadWizardDeedThirdParty.js',
			'resources/mw.UploadWizardDeedChooser.js',
			'resources/mw.UploadWizardLicenseInput.js',

			// main library
			'resources/mw.UploadWizard.js',

			// main library components:
			'resources/mw.UploadWizardUpload.js',
			'resources/mw.UploadWizardDetails.js',
			'resources/mw.UploadWizardUploadInterface.js',
		),
		'styles' => array(
			'resources/uploadWizard.css',
			// OOjs UI interface elements
			'resources/uw.FieldLayout.less',
			'resources/details/uw.DateDetailsWidget.less',
			'resources/details/uw.DescriptionDetailsWidget.less',
			'resources/details/uw.DescriptionsDetailsWidget.less',
			'resources/details/uw.LocationDetailsWidget.less',
		),
		'messages' => array(
			'comma-separator',
			'uploadwizard',
			'uploadwizard-desc',
			'mwe-upwiz-step-tutorial',
			'mwe-upwiz-step-file',
			'mwe-upwiz-step-deeds',
			'mwe-upwiz-step-details',
			'mwe-upwiz-step-thanks',
			'api-error-autoblocked',
			'api-error-blocked',
			'api-error-http',
			'api-error-ok-but-empty',
			'api-error-unknown-code',
			'api-error-uploaddisabled',
			'api-error-nomodule',
			'api-error-mustbeposted',
			'api-error-badaccess-groups',
			'api-error-ratelimited',
			'api-error-stashfailed',
			'api-error-missingresult',
			'api-error-missingparam',
			'api-error-invalid-file-key',
			'api-error-copyuploaddisabled',
			'api-error-mustbeloggedin',
			'api-error-empty-file',
			'api-error-file-too-large',
			'api-error-filetype-missing',
			'api-error-filetype-banned',
			'api-error-filetype-banned-type',
			'api-error-filename-tooshort',
			'api-error-illegal-filename',
			'api-error-verification-error',
			'api-error-hookaborted',
			'api-error-unknown-error',
			'api-error-internal-error',
			'api-error-overwrite',
			'api-error-badtoken',
			'api-error-fetchfileerror',
			'api-error-duplicate',
			'api-error-duplicate-archive',
			'api-error-unknown-warning',
			'api-error-timeout',
			'api-error-noimageinfo',
			'api-error-fileexists-shared-forbidden',
			'api-error-unclassified',
			'api-error-stasherror',
			'api-error-stashedfilenotfound',
			'api-error-stashpathinvalid',
			'api-error-stashfilestorage',
			'api-error-stashzerolength',
			'api-error-stashnotloggedin',
			'api-error-stashwrongowner',
			'api-error-stashnosuchfilekey',
			'mwe-upwiz-api-warning-was-deleted',
			'mwe-upwiz-api-warning-exists',
			'mwe-upwiz-tutorial-error-localized-file-missing',
			'mwe-upwiz-tutorial-error-file-missing',
			'mwe-upwiz-tutorial-error-cannot-transform',
			'mwe-upwiz-help-desk',
			'mwe-upwiz-multi-file-select',
			'mwe-upwiz-flickr-input-placeholder',
			'mwe-upwiz-add-flickr-or',
			'mwe-upwiz-add-flickr',
			'mwe-upwiz-select-flickr',
			'mwe-upwiz-flickr-disclaimer1',
			'mwe-upwiz-flickr-disclaimer2',
			'mwe-upwiz-encoding',
			'mwe-upwiz-uploading',
			'mwe-upwiz-queued',
			'mwe-upwiz-assembling',
			'mwe-upwiz-publish',
			'mwe-upwiz-transported',
			'mwe-upwiz-stashed-upload',
			'mwe-upwiz-getting-metadata',
			'mwe-upwiz-submitting-details',
			'mwe-upwiz-published',
			'mwe-upwiz-failed',
			'mwe-upwiz-remove',
			'mwe-upwiz-remove-upload',
			'mwe-upwiz-remove-description',
			'mwe-upwiz-deleted-duplicate-unknown-filename',
			'mwe-upwiz-upload',
			'mwe-upwiz-file-upload-notcapable',
			'mwe-upwiz-file-retry',
			'mwe-upwiz-next-file-despite-failures',
			'mwe-upwiz-upload-count',
			'mwe-upwiz-progressbar-uploading',
			'mwe-upwiz-almost-finished',
			'mwe-upwiz-finished',
			'mwe-upwiz-deeds-macro-prompt',
			'mwe-upwiz-source-ownwork',
			'mwe-upwiz-source-ownwork-assert-any-license',
			'mwe-upwiz-source-ownwork-assert',
			'mwe-upwiz-source-ownwork-assert-custom',
			'mwe-upwiz-source-ownwork-assert-note',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-4.0',
			'mwe-upwiz-source-ownwork-cc-by-sa-4.0-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-at',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-at-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-de',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-de-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-ee',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-ee-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-es',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-es-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-hr',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-hr-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-lu',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-lu-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-nl',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-nl-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-no',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-no-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-pl',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-pl-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-sa-3.0-ro',
			'mwe-upwiz-source-ownwork-cc-by-sa-3.0-ro-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-4.0',
			'mwe-upwiz-source-ownwork-cc-by-4.0-explain',
			'mwe-upwiz-source-ownwork-assert-cc-by-3.0',
			'mwe-upwiz-source-ownwork-cc-by-3.0-explain',
			'mwe-upwiz-source-ownwork-assert-cc-zero',
			'mwe-upwiz-source-ownwork-cc-zero-explain',
			'mwe-upwiz-source-ownwork-assert-generic',
			'mwe-upwiz-source-ownwork-generic-explain',
			'disclaimerpage',
			'mwe-upwiz-source-permission',
			'mwe-upwiz-source-thirdparty',
			'mwe-upwiz-source-thirdparty-intro',
			'mwe-upwiz-source-thirdparty-custom-multiple-intro',
			'mwe-upwiz-source-thirdparty-cases',
			'mwe-upwiz-source-thirdparty-accept',
			'mwe-upwiz-source-custom',
			'mwe-upwiz-more-options',
			'mwe-upwiz-copy-metadata',
			'mwe-upwiz-copy-metadata-button',
			'mwe-upwiz-copied-metadata-button',
			'mwe-upwiz-copy-title',
			'mwe-upwiz-copy-description',
			'mwe-upwiz-copy-date',
			'mwe-upwiz-copy-categories',
			'mwe-upwiz-copy-location',
			'mwe-upwiz-copy-other',
			'mwe-upwiz-desc',
			'mwe-upwiz-desc-placeholder',
			'mwe-upwiz-desc-add-n',
			'mwe-upwiz-desc-add-0',
			'mwe-upwiz-title',
			'mwe-upwiz-date-created',
			'mwe-upwiz-select-date',
			'mwe-upwiz-location',
			'mwe-upwiz-location-button',
			'mwe-upwiz-location-lat',
			'mwe-upwiz-location-lon',
			'mwe-upwiz-location-alt',
			'mwe-upwiz-location-heading',
			'mwe-upwiz-copyright-info',
			'mwe-upwiz-objref-pick-image',
			'mwe-upwiz-objref-notice-existing-image',
			'mwe-upwiz-author',
			'mwe-upwiz-autoconverted',
			'mwe-upwiz-other',
			'mwe-upwiz-source',
			'mwe-upwiz-upload-error-bad-extension-video-firefogg',
			'mwe-upwiz-upload-error-bad-filename-extension',
			'mwe-upwiz-upload-error-bad-filename-no-extension',
			'mwe-upwiz-upload-error-duplicate-filename-error',
			'mwe-upwiz-allowed-filename-extensions',
			'mwe-upwiz-upload-error-duplicate',
			'mwe-upwiz-upload-error-duplicate-archive',
			'mwe-upwiz-upload-error-stashed-anyway',
			'mwe-upwiz-ok',
			'mwe-upwiz-fileexists-replace-on-page',
			'mwe-upwiz-fileexists-replace-no-link',
			'mwe-upwiz-blacklisted-details',
			'mwe-upwiz-blacklisted-details-feedback',
			'mwe-upwiz-override',
			'mwe-upwiz-override-upload',
			'mwe-upwiz-next',
			'mwe-upwiz-next-file',
			'mwe-upwiz-next-deeds',
			'mwe-upwiz-next-details',
			'mwe-upwiz-home',
			'mwe-upwiz-upload-another',
			'mwe-prevent-close',
			'mwe-upwiz-prevent-close',
			'mwe-upwiz-prevent-close-wait',
			'mwe-upwiz-tooltip-author',
			'mwe-upwiz-tooltip-source',
			'mwe-upwiz-tooltip-sign',
			'mwe-upwiz-tooltip-title',
			'mwe-upwiz-tooltip-description',
			'mwe-upwiz-tooltip-date',
			'mwe-upwiz-tooltip-categories',
			'mwe-upwiz-tooltip-other',
			'mwe-upwiz-tooltip-location',
			'mwe-upwiz-tooltip-more-info',
			'mwe-upwiz-file-need-file',
			'mwe-upwiz-deeds-need-deed',
			'mwe-upwiz-deeds-need-license',
			'mwe-upwiz-license-show-all',
			'mwe-upwiz-license-show-recommended',
			'mwe-upwiz-error-signature-blank',
			'mwe-upwiz-error-latitude',
			'mwe-upwiz-error-longitude',
			'mwe-upwiz-error-altitude',
			'mwe-upwiz-error-heading',
			'mwe-upwiz-error-signature-too-long',
			'mwe-upwiz-error-signature-too-short',
			'mwe-upwiz-error-signature-bad-chars',
			'mwe-upwiz-error-blank',
			'mwe-upwiz-error-too-long',
			'mwe-upwiz-error-too-short',
			'mwe-upwiz-error-bad-descriptions',
			'mwe-upwiz-error-bad-chars',
			'mwe-upwiz-error-title-blacklisted',
			'mwe-upwiz-error-title-badchars',
			'mwe-upwiz-error-title-senselessimagename',
			'mwe-upwiz-error-title-hosting',
			'mwe-upwiz-error-title-thumbnail',
			'mwe-upwiz-error-title-fileexists-shared-forbidden',
			'mwe-upwiz-error-title-double-apostrophe',
			'mwe-upwiz-error-title-extension',
			'mwe-upwiz-error-title-protected',
			'mwe-upwiz-error-title-duplicate',
			'mwe-upwiz-error-license-wikitext-missing',
			'mwe-upwiz-error-license-wikitext-too-short',
			'mwe-upwiz-error-license-wikitext-too-long',
			'mwe-upwiz-warning-categories-missing',
			'mwe-upwiz-warning-postdate',
			'mwe-upwiz-details-error-count',
			'mwe-upwiz-details-warning-count',
			'mwe-upwiz-license-cc-by-sa-4.0',
			'mwe-upwiz-license-cc-by-sa-3.0',
			'mwe-upwiz-license-cc-by-sa-3.0-at',
			'mwe-upwiz-license-cc-by-sa-3.0-de',
			'mwe-upwiz-license-cc-by-sa-3.0-ee',
			'mwe-upwiz-license-cc-by-sa-3.0-es',
			'mwe-upwiz-license-cc-by-sa-3.0-hr',
			'mwe-upwiz-license-cc-by-sa-3.0-lu',
			'mwe-upwiz-license-cc-by-sa-3.0-nl',
			'mwe-upwiz-license-cc-by-sa-3.0-no',
			'mwe-upwiz-license-cc-by-sa-3.0-pl',
			'mwe-upwiz-license-cc-by-sa-3.0-ro',
			'mwe-upwiz-license-cc-by-4.0',
			'mwe-upwiz-license-cc-by-3.0',
			'mwe-upwiz-license-cc-zero',
			'mwe-upwiz-license-cc-by-sa-2.5',
			'mwe-upwiz-license-cc-by-2.5',
			'mwe-upwiz-license-cc-by-sa-2.0',
			'mwe-upwiz-license-cc-by-2.0',
			'mwe-upwiz-license-cc-by-nc-2.0',
			'mwe-upwiz-license-cc-by-nc-sa-2.0',
			'mwe-upwiz-license-custom',
			'mwe-upwiz-license-fal',
			'mwe-upwiz-license-own-pd',
			'mwe-upwiz-license-pd-old-100',
			'mwe-upwiz-license-pd-old-70-1923',
			'mwe-upwiz-license-pd-old',
			'mwe-upwiz-license-pd-art',
			'mwe-upwiz-license-pd-us',
			'mwe-upwiz-license-pd-usgov',
			'mwe-upwiz-license-pd-usgov-nasa',
			'mwe-upwiz-license-pd-usgov-military-navy',
			'mwe-upwiz-license-pd-ineligible',
			'mwe-upwiz-license-pd-ineligible-help',
			'mwe-upwiz-license-pd-textlogo',
			'mwe-upwiz-license-copyrighted-free-use',
			'mwe-upwiz-license-attribution',
			'mwe-upwiz-license-gfdl',
			'mwe-upwiz-license-cc-by-sa-3.0-gfdl',
			'mwe-upwiz-license-cc-head',
			'mwe-upwiz-license-cc-subhead',
			'mwe-upwiz-license-flickr-head',
			'mwe-upwiz-license-flickr-subhead',
			'mwe-upwiz-license-public-domain-usa-head',
			'mwe-upwiz-license-public-domain-usa-subhead',
			'mwe-upwiz-license-usgov-head',
			'mwe-upwiz-license-misc',
			'mwe-upwiz-license-custom-head',
			'mwe-upwiz-license-custom-preview',
			'mwe-upwiz-license-none',
			'mwe-upwiz-license-none-head',
			'mwe-upwiz-license-generic',
			'mwe-upwiz-license-generic-head',
			'mwe-upwiz-license-confirm-remove',
			'mwe-upwiz-license-confirm-remove-title',
			'mwe-upwiz-license-external',
			'mwe-upwiz-license-external-invalid',
			'mwe-upwiz-license-photoset-invalid',
			'mwe-upwiz-url-invalid',
			'mwe-upwiz-user-blacklisted',
			'mwe-upwiz-categories',
			'mwe-upwiz-categories-missing',
			'mwe-upwiz-thumbnail-failed',
			'mwe-upwiz-unparseable-filename',
			'mwe-upwiz-unparseable-title',
			'mwe-upwiz-subhead-bugs',
			'mwe-upwiz-subhead-alt-upload',
			'mwe-upwiz-subhead-alternatives',
			'mwe-upwiz-feedback-prompt',
			'mwe-upwiz-feedback-title',
			'mwe-upwiz-feedback-blacklist-line-intro',
			'mwe-upwiz-feedback-blacklist-subject',
			'mwe-upwiz-errordialog-title',
			'mwe-upwiz-errordialog-ok',
			'mwe-upwiz-calendar-date',
			'mwe-upwiz-custom-date',
			'size-gigabytes',
			'size-megabytes',
			'size-kilobytes',
			'size-bytes',
			'wm-license-cc-by-sa-3.0-at-text',
			'wm-license-cc-by-sa-3.0-de-text',
			'wm-license-cc-by-sa-3.0-ee-text',
			'wm-license-cc-by-sa-3.0-es-text',
			'wm-license-cc-by-sa-3.0-hr-text',
			'wm-license-cc-by-sa-3.0-lu-text',
			'wm-license-cc-by-sa-3.0-nl-text',
			'wm-license-cc-by-sa-3.0-no-text',
			'wm-license-cc-by-sa-3.0-pl-text',
			'wm-license-cc-by-sa-3.0-ro-text',
			'mwe-upwiz-too-many-files-text',
			'mwe-upwiz-too-many-files',
			'mwe-upwiz-file-too-large-text',
			'mwe-upwiz-file-too-large',
			'mwe-upwiz-dialog-warning',
			'mwe-upwiz-dialog-yes',
			'mwe-upwiz-dialog-no',
			'mwe-upwiz-dialog-title',
			'colon-separator',
			'mwe-upwiz-license-sc-head',
			'mwe-upwiz-license-rsi',
			'mwe-upwiz-license-thedamnshames',
			'mwe-upwiz-license-hasgaha',
			'mwe-upwiz-license-aelannatesla',
			'mwe-upwiz-error-not-url-or-template',
		),
		'group' => 'ext.uploadWizard',
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'ext.uploadWizard.events' => array(
		'scripts' => array(
			'resources/ext.UploadWizardEvent.js',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.base' => array(
		'scripts' => array(
			'resources/uw/uw.base.js',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.controller.base' => array(
		'scripts' => array(
			'resources/controller/uw.controller.base.js',
		),
		'dependencies' => array(
			'uw.base',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.controller.Deed' => array(
		'scripts' => array(
			'resources/controller/uw.controller.Deed.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.controller.Step',
			'uw.controller.base',
			'uw.ui.Deed',
			'uw.ui.DeedPreview',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.controller.Details' => array(
		'scripts' => array(
			'resources/controller/uw.controller.Details.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.controller.base',
			'uw.controller.Step',
			'uw.ui.Details',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.controller.Step' => array(
		'scripts' => array(
			'resources/uw.ConcurrentQueue.js',
			'resources/controller/uw.controller.Step.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.controller.base',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.controller.Thanks' => array(
		'scripts' => array(
			'resources/controller/uw.controller.Thanks.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.controller.Step',
			'uw.controller.base',
			'uw.ui.Thanks',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.controller.Tutorial' => array(
		'scripts' => array(
			'resources/controller/uw.controller.Tutorial.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.controller.Step',
			'uw.controller.base',
			'uw.ui.Tutorial',
		),
		'messages' => array(
			'mwe-upwiz-prevent-close-wait',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.controller.Upload' => array(
		'scripts' => array(
			'resources/controller/uw.controller.Upload.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.controller.Step',
			'uw.ui.Upload',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.base' => array(
		'scripts' => array(
			'resources/ui/uw.ui.base.js',
		),
		'dependencies' => array(
			'uw.base',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.Wizard' => array(
		'scripts' => array(
			'resources/ui/uw.ui.Wizard.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.ui.base',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.Step' => array(
		'scripts' => array(
			'resources/ui/uw.ui.Step.js',
		),
		'dependencies' => array(
			'oojs',
			'oojs-ui',
			'uw.ui.base',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.Deed' => array(
		'scripts' => array(
			'resources/ui/steps/uw.ui.Deed.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.ui.Step',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.DeedPreview' => array(
		'scripts' => array(
			'resources/ui/uw.ui.DeedPreview.js',
		),
		'dependencies' => array(
			'uw.ui.base',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.Details' => array(
		'scripts' => array(
			'resources/ui/steps/uw.ui.Details.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.ui.Step',
		),
		'messages' => array(
			'mwe-upwiz-file-some-failed',
			'mwe-upwiz-file-all-failed',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.Thanks' => array(
		'scripts' => array(
			'resources/ui/steps/uw.ui.Thanks.js',
		),
		'styles' => array(
			'resources/ui/steps/uw.ui.Thanks.less',
		),
		'dependencies' => array(
			'oojs',
			'uw.ui.base',
			'uw.ui.Step',
		),
		'messages' => array(
			'mwe-upwiz-thanks-intro',
			'mwe-upwiz-thanks-explain',
			'mwe-upwiz-thanks-wikitext',
			'mwe-upwiz-objref-notice-update-delay',
			'mwe-upwiz-thanks-url',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.Tutorial' => array(
		'scripts' => array(
			'resources/ui/steps/uw.ui.Tutorial.js',
		),
		'styles' => array(
			'resources/ui/steps/uw.ui.Tutorial.css',
		),
		'dependencies' => array(
			'oojs',
			'uw.ui.base',
			'uw.ui.Step',
		),
		'messages' => array(
			'mwe-upwiz-skip-tutorial-future',
			'mwe-upwiz-tooltip-skiptutorial',
			'prefs-uploads',
			'prefs-upwiz-interface',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
	'uw.ui.Upload' => array(
		'scripts' => array(
			'resources/ui/steps/uw.ui.Upload.js',
		),
		'dependencies' => array(
			'oojs',
			'uw.ui.Step',
		),
		'messages' => array(
			'mwe-upwiz-add-file-n',
			'mwe-upwiz-add-file-0-free',
			'mwe-upwiz-add-file-flickr-n',
			'mwe-upwiz-add-file-flickr',
			'mwe-upwiz-file-all-ok',
			'mwe-upwiz-file-some-failed',
			'mwe-upwiz-file-all-failed',
		),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'UploadWizard',
	),
);

$wgEventLoggingSchemas[ 'UploadWizardTutorialActions' ] = 5803466;
$wgEventLoggingSchemas[ 'UploadWizardUploadActions' ] = 5811620;
$wgEventLoggingSchemas[ 'UploadWizardStep' ] = 11772724;
$wgEventLoggingSchemas[ 'UploadWizardFlowEvent' ] = 11772723;
$wgEventLoggingSchemas[ 'UploadWizardErrorFlowEvent' ] = 11772725;
$wgEventLoggingSchemas[ 'UploadWizardUploadFlowEvent' ] = 11772717;

// Campaign hook handlers
$wgHooks[ 'EditFilterMerged' ][] = 'CampaignHooks::onEditFilterMerged';
$wgHooks[ 'CodeEditorGetPageLanguage' ][] = 'CampaignHooks::onCodeEditorGetPageLanguage';
$wgHooks[ 'PageContentSaveComplete' ][] = 'CampaignHooks::onPageContentSaveComplete';
$wgHooks[ 'ArticleDeleteComplete' ][] = 'CampaignHooks::onArticleDeleteComplete';
$wgHooks[ 'TitleMoveComplete' ][] = 'CampaignHooks::onTitleMoveComplete';
$wgHooks[ 'LinksUpdateComplete' ][] = 'CampaignHooks::onLinksUpdateComplete';

$wgAvailableRights[] = 'upwizcampaigns';

# Users that can modify the upload campaigns (ie via Special:UploadCampaigns)
$wgGroupPermissions['*'               ]['upwizcampaigns'] = false;
$wgGroupPermissions['user'            ]['upwizcampaigns'] = false;
$wgGroupPermissions['autoconfirmed'   ]['upwizcampaigns'] = false;
$wgGroupPermissions['bot'             ]['upwizcampaigns'] = false;
$wgGroupPermissions['sysop'           ]['upwizcampaigns'] = true;
$wgGroupPermissions['upwizcampeditors']['upwizcampaigns'] = true;

$wgAddGroups['sysop'][] = 'upwizcampeditors';
$wgRemoveGroups['sysop'][] = 'upwizcampeditors';

$wgDefaultUserOptions['upwiz_deflicense'] = 'default';
$wgDefaultUserOptions['upwiz_maxsimultaneous'] = 'default';

// Init the upload wizard config array
// UploadWizard.config.php includes default configuration
$wgUploadWizardConfig = array();

/* Define and configure default namespaces, as defined on Mediawiki.org
 * https://www.mediawiki.org/wiki/Extension_default_namespaces#UploadWizard
 */
define( 'NS_CAMPAIGN', 460 );
define( 'NS_CAMPAIGN_TALK', 461 );
$wgExtraNamespaces[ NS_CAMPAIGN ] = 'Campaign';
$wgExtraNamespaces[ NS_CAMPAIGN_TALK ] = 'Campaign_talk';

$wgNamespaceProtection[ NS_CAMPAIGN ] = array( 'upwizcampaigns' );

$wgNamespaceContentModels[ NS_CAMPAIGN ] = 'Campaign';
$wgContentHandlers[ 'Campaign' ] = 'CampaignContentHandler';

$wgCapitalLinkOverrides[ NS_CAMPAIGN ] = false;
$wgCapitalLinkOverrides[ NS_CAMPAIGN_TALK ] = false;
