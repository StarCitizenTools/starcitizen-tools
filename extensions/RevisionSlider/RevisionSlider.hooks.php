<?php

/**
 * RevisionSlider extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */
class RevisionSliderHooks {

	public static function onDiffViewHeader(
		DifferenceEngine $diff,
		Revision $oldRev,
		Revision $newRev
	) {
		$out = RequestContext::getMain()->getOutput();
		$out->addModules( 'ext.RevisionSlider.init' );
		$out->addHTML( '<div id="revision-slider-container" style="min-height: 150px;">' );
		$placeHolder = ( new Message( 'revisionslider-loading-placeholder' ) )->parse();
		$out->addHTML(
			'<p id="revision-slider-placeholder" style="text-align: center">' .  $placeHolder. '</p>'
		);
		$noScriptMessage = ( new Message( 'revisionslider-loading-noscript' ) )->parse();
		$out->addHTML(
			'<noscript><p style="text-align: center" >' . $noScriptMessage . '</p></noscript>'
		);
		$out->addHTML( '</div>' );
	}

	public static function onResourceLoaderTestModules( array &$testModules, ResourceLoader $rl ) {
		$testModules['qunit']['ext.RevisionSlider.tests'] = [
			'scripts' => [
				'tests/RevisionSlider.Revision.test.js',
			],
			'dependencies' => [
				'ext.RevisionSlider.Revision'
			],
			'localBasePath' => __DIR__,
		];

		return true;
	}
}
