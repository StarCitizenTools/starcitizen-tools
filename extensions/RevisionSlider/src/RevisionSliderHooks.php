<?php
use MediaWiki\MediaWikiServices;

/**
 * RevisionSlider extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */
class RevisionSliderHooks {

	/**
	 * @var Config
	 */
	private static $config;

	/**
	 * Returns the RevisionSlider extensions config.
	 *
	 * @return Config
	 */
	private static function getConfig() {
		if ( self::$config === null ) {
			self::$config = MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'revisionslider' );
		}
		return self::$config;
	}

	/**
	 * @param DifferenceEngine $diff
	 * @param Revision|null $oldRev
	 * @param Revision|null $newRev
	 * @return bool
	 */
	public static function onDiffViewHeader(
		DifferenceEngine $diff,
		Revision $oldRev = null,
		Revision $newRev = null
	) {
		// sometimes $oldRev can be null (e.g. missing rev), and perhaps also $newRev (T167359)
		if ( !( $oldRev instanceof Revision ) || !( $newRev instanceof Revision ) ) {
			return true;
		}

		// do not show on MobileDiff page
		if ( $diff->getTitle()->isSpecial( 'MobileDiff' ) ) {
			return true;
		}

		$config = self::getConfig();

		/**
		 * If the user is logged in and has explictly requested to disable the extension don't load.
		 */
		$user = $diff->getUser();
		if ( !$user->isAnon() && $user->getBoolOption( 'revisionslider-disable' ) ) {
			return true;
		}

		/**
		 * Do not show the RevisionSlider when revisions from two different pages are being compared
		 */
		if ( !$oldRev->getTitle()->equals( $newRev->getTitle() ) ) {
			return true;
		}

		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$stats->increment( 'RevisionSlider.event.hookinit' );

		$timeOffset = $config->get( 'LocalTZoffset' );
		if ( is_null( $config->get( 'Localtimezone' ) ) ) {
			$timeOffset = 0;
		} elseif ( is_null( $timeOffset ) ) {
			$timeOffset = 0;
		}

		$autoExpand = $user->getBoolOption( 'userjs-revslider-autoexpand' );

		$out = RequestContext::getMain()->getOutput();
		// Load styles on page load to avoid FOUC
		$out->addModuleStyles( 'ext.RevisionSlider.lazyCss' );
		if ( $autoExpand ) {
			$out->addModules( 'ext.RevisionSlider.init' );
			$stats->increment( 'RevisionSlider.event.load' );
		} else {
			$out->addModules( 'ext.RevisionSlider.lazyJs' );
			$stats->increment( 'RevisionSlider.event.lazyload' );
		}
		$out->addModuleStyles( 'ext.RevisionSlider.noscript' );
		$out->addJsConfigVars( 'extRevisionSliderTimeOffset', intval( $timeOffset ) );
		$out->enableOOUI();

		$toggleButton = new OOUI\ButtonWidget( [
			'label' => ( new Message( 'revisionslider-toggle-label' ) )->text(),
			'icon' => $autoExpand ? 'collapse' : 'expand',
			'classes' => [ 'mw-revslider-toggle-button' ],
			'infusable' => true,
			'framed' => false,
			'title' => ( new Message( 'revisionslider-toggle-title-expand' ) )->text(),
		] );
		$toggleButton->setAttributes( [ 'style' => 'width: 100%; text-align: center;' ] );

		$progressBar = new OOUI\ProgressBarWidget( [ 'progress' => false ] );

		$out->prependHTML(
			Html::rawElement(
				'div',
				[
					'class' => 'mw-revslider-container',
					'aria-hidden' => 'true'
				],
				$toggleButton .
				Html::rawElement(
					'div',
					[
						'class' => 'mw-revslider-slider-wrapper',
						'style' => ( !$autoExpand ? ' display: none;' : '' ),
					],
					Html::rawElement(
						'div', [ 'class' => 'mw-revslider-placeholder' ],
						$progressBar
					)
				)
			)
		);
		return true;
	}

	/**
	 * @param array &$testModules
	 * @param ResourceLoader $rl
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( array &$testModules, ResourceLoader $rl ) {
		$testModules['qunit']['ext.RevisionSlider.tests'] = [
			'scripts' => [
				'tests/qunit/QUnit.revisionSlider.testOrSkip.js',
				'tests/qunit/RevisionSlider.Revision.test.js',
				'tests/qunit/RevisionSlider.Pointer.test.js',
				'tests/qunit/RevisionSlider.PointerView.test.js',
				'tests/qunit/RevisionSlider.Slider.test.js',
				'tests/qunit/RevisionSlider.SliderView.test.js',
				'tests/qunit/RevisionSlider.RevisionList.test.js',
				'tests/qunit/RevisionSlider.RevisionListView.test.js',
				'tests/qunit/RevisionSlider.DiffPage.test.js',
				'tests/qunit/RevisionSlider.HelpDialog.test.js',
			],
			'dependencies' => [
				'ext.RevisionSlider.Revision',
				'ext.RevisionSlider.Pointer',
				'ext.RevisionSlider.PointerView',
				'ext.RevisionSlider.Slider',
				'ext.RevisionSlider.SliderView',
				'ext.RevisionSlider.RevisionList',
				'ext.RevisionSlider.RevisionListView',
				'ext.RevisionSlider.DiffPage',
				'ext.RevisionSlider.HelpDialog',
				'jquery.ui.draggable',
				'jquery.ui.tooltip',
				'oojs-ui'
			],
			'localBasePath' => dirname( __DIR__ ),
			'remoteExtPath' => 'RevisionSlider',
		];

		return true;
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['revisionslider-disable'] = [
			'type' => 'toggle',
			'label-message' => 'revisionslider-preference-disable',
			'section' => 'rendering/diffs',
			'default' => $user->getBoolOption( 'revisionslider-disable' ),
		];

		return true;
	}
}
