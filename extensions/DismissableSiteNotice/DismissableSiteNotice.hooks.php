<?php

class DismissableSiteNoticeHooks {

	/**
	 * @param string &$notice
	 * @param Skin $skin
	 * @return bool true
	 */
	public static function onSiteNoticeAfter( &$notice, $skin ) {
		global $wgMajorSiteNoticeID, $wgDismissableSiteNoticeForAnons;

		if ( !$notice ) {
			return true;
		}

		// Dismissal for anons is configurable
		if ( $wgDismissableSiteNoticeForAnons || $skin->getUser()->isLoggedIn() ) {
			// Cookie value consists of two parts
			$major = (int)$wgMajorSiteNoticeID;
			$minor = (int)$skin->msg( 'sitenotice_id' )->inContentLanguage()->text();

			$out = $skin->getOutput();
			$out->addModuleStyles( 'ext.dismissableSiteNotice.styles' );
			$out->addModules( 'ext.dismissableSiteNotice' );
			$out->addJsConfigVars( 'wgSiteNoticeId', "$major.$minor" );

			$notice = Html::rawElement( 'div', [ 'class' => 'mw-dismissable-notice' ],
				Html::rawElement( 'div', [ 'class' => 'mw-dismissable-notice-close' ],
					$skin->msg( 'sitenotice_close-brackets' )
						->rawParams(
							Html::element( 'a', [ 'href' => '#' ], $skin->msg( 'sitenotice_close' )->text() )
						)
						->escaped()
				) .
				Html::rawElement( 'div', [ 'class' => 'mw-dismissable-notice-body' ], $notice )
			);
		}

		if ( $skin->getUser()->isAnon() ) {
			// Hide the sitenotice from search engines (see bug T11209 comment 4)
			// NOTE: Is this actually effective?
			// NOTE: Avoid document.write (T125323)
			// NOTE: Must be compatible with JavaScript in ancient Grade C browsers.
			$jsWrapped =
				'<div id="mw-dismissablenotice-anonplace"></div>' .
				Html::inlineScript(
					'(function(){' .
					'var node=document.getElementById("mw-dismissablenotice-anonplace");' .
					'if(node){'.
					// Replace placeholder with parsed HTML from $notice.
					// Setting outerHTML is supported in all Grade C browsers
					// and gracefully fallsback to just setting a property.
					// It is short for:
					// - Create temporary element or document fragment
					// - Set innerHTML.
					// - Replace node with wrapper's child nodes.
					'node.outerHTML=' . Xml::encodeJsVar( $notice ) . ';' .
					'}' .
					'}());'
				);
			$notice = $jsWrapped;
		}

		return true;
	}
}
