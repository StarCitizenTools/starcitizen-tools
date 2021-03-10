<?php
namespace RelatedArticles;

use ResourceLoaderFileModule;
use ResourceLoaderContext;

/**
 * A ResourceLoader module that serves Hogan or Mustache depending on the
 * current target.
 *
 * FIXME: this is a copy&paste from the QuickSurveys extension. Find a way to
 * share the code or use mustache in MobileFrontend too.
 */
class ResourceLoaderMuHoganModule extends ResourceLoaderFileModule {

	/**
	 * Gets list of names of modules this module depends on.
	 *
	 * @param ResourceLoaderContext|null $context Resource loader context
	 * @return array List of module names
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		$dependencies = parent::getDependencies( $context );

		if ( $context && $context->getRequest()->getVal( 'target' ) === 'mobile' ) {
			$dependencies[] = 'mediawiki.template.hogan';
		} else {
			$dependencies[] = 'mediawiki.template.mustache';
		}

		return $dependencies;
	}
}
