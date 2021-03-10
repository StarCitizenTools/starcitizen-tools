<?php
/**
 * Add a link to user's personal sandbox to personal tools menu.
 *
 * https://www.mediawiki.org/wiki/Extension:SandboxLink
 *
 * @file
 * @license MIT
 */

class SandboxLinkHooks {
	/**
	 * Return a Title for the page where the current user's sandbox is.
	 *
	 * @param Skin $skin For context
	 * @return Title|null
	 */
	private static function getSandboxTitle( Skin $skin ) {
		$subpageMsg = $skin->msg( 'sandboxlink-subpage-name' )->inContentLanguage();
		if ( $subpageMsg->isDisabled() ) {
			return null;
		}
		$username = $skin->getUser()->getName();
		return Title::makeTitleSafe( NS_USER, $username . '/' . $subpageMsg->plain() );
	}

	/**
	 * Return a link descriptor for the page where the current user's sandbox is,
	 * relative to current title and in current language.
	 *
	 * @param Skin $skin For context
	 * @return array Link descriptor in a format accepted by PersonalUrls hook
	 */
	private static function makeSandboxLink( Skin $skin ) {
		$currentTitle = $skin->getTitle();

		$title = self::getSandboxTitle( $skin );
		if ( !$title ) {
			return null;
		}

		if ( $title->exists() && $title->isRedirect() ) {
			$href = $title->getLocalURL( [ 'redirect' => 'no' ] );
		} elseif ( $title->exists() ) {
			$href = $title->getLocalURL();
		} else {
			$query = [ 'action' => 'edit', 'redlink' => '1' ];

			$editintroMsg = $skin->msg( 'sandboxlink-editintro-pagename' )->inContentLanguage();
			if ( !$editintroMsg->isDisabled() ) {
				$query['editintro'] = $editintroMsg->plain();
			}

			$preloadMsg = $skin->msg( 'sandboxlink-preload-pagename' )->inContentLanguage();
			if ( !$preloadMsg->isDisabled() ) {
				$query['preload'] = $preloadMsg->plain();
			}

			$href = $title->getLocalURL( $query );
		}

		return [
			'id' => 'pt-sandbox',
			'text' => $skin->msg( 'sandboxlink-portlet-label' )->text(),
			'href' => $href,
			'class' => $title->exists() ? false : 'new',
			'exists' => $title->exists(),
			'active' => $title->equals( $currentTitle ),
		];
	}

	/**
	 * SkinPreloadExistence hook handler.
	 *
	 * Add the title of the page where the current user's sandbox is to link existence cache.
	 *
	 * @param Title[] &$titles
	 * @param Skin $skin
	 * @return bool true
	 */
	public static function onSkinPreloadExistence( &$titles, $skin ) {
		$title = self::getSandboxTitle( $skin );
		if ( $title ) {
			$titles[] = $title;
		}
		return true;
	}

	/**
	 * PersonalUrls hook handler.
	 *
	 * Possibly add a link to the page where the current user's sandbox is to personal tools menu.
	 *
	 * @param array &$personalUrls
	 * @param Title &$title (unused)
	 * @param Skin $skin
	 * @return bool true
	 */
	public static function onPersonalUrls( &$personalUrls, &$title, $skin ) {
		global $wgSandboxLinkDisableAnon;
		if ( $wgSandboxLinkDisableAnon && $skin->getUser()->isAnon() ) {
			return true;
		}

		$link = self::makeSandboxLink( $skin );
		if ( !$link ) {
			return true;
		}

		$newPersonalUrls = [];
		$done = false;

		// Insert our link before the link to user preferences.
		// If the link to preferences is missing, insert at the end.
		foreach ( $personalUrls as $key => $value ) {
			if ( $key === 'preferences' ) {
				$newPersonalUrls['sandbox'] = $link;
				$done = true;
			}
			$newPersonalUrls[$key] = $value;
		}
		if ( !$done ) {
			$newPersonalUrls['sandbox'] = $link;
		}

		$personalUrls = $newPersonalUrls;
		return true;
	}
}
