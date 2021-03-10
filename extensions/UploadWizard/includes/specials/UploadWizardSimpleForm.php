<?php
/**
 * This upload form is used at Special:UploadWizard
 *
 * Special:UploadWizard is easy to use multi-file upload page.
 *
 * @file
 * @ingroup SpecialPage
 * @ingroup Upload
 */

/**
 * This is a hack on UploadForm, to make one that works from UploadWizard when JS is not available.
 *
 * @codeCoverageIgnore
 */
class UploadWizardSimpleForm extends UploadForm {

	/**
	 * Normally, UploadForm adds its own Javascript.
	 * We wish to prevent this, because we want to control the case where we have Javascript.
	 * So, we make the addUploadJS a no-op.
	 */
	protected function addUploadJS() {
	}
}
