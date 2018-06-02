<?php

class CookieWarningHooks {
	private static $inConfiguredRegion;

	/**
	 * BeforeInitialize hook handler.
	 *
	 * If the disablecookiewarning POST data is send, disables the cookiewarning bar with a
	 * cookie or a user preference, if the user is logged in.
	 *
	 * @param Title $title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $mediawiki
	 */
	public static function onBeforeInitialize( Title &$title, &$unused, OutputPage &$output,
		User &$user, WebRequest $request, MediaWiki $mediawiki
	) {
		if ( !$request->wasPosted() || !$request->getVal( 'disablecookiewarning' ) ) {
			return;
		}

		if ( $user->isLoggedIn() ) {
			$user->setOption( 'cookiewarning_dismissed', 1 );
			$user->saveSettings();
		} else {
			$request->response()->setCookie( 'cookiewarning_dismissed', true );
		}
		$output->redirect( $request->getRequestURL() );
	}
	/**
	 * SkinTemplateOutputPageBeforeExec hook handler.
	 *
	 * Adds the CookieWarning information bar to the output html.
	 *
	 * @param SkinTemplate $sk
	 * @param QuickTemplate $tpl
	 */
	public static function onSkinTemplateOutputPageBeforeExec(
		SkinTemplate &$sk, QuickTemplate &$tpl
	) {
		// if the cookiewarning should not be visible to the user, exit.
		if ( !self::showWarning( $sk->getContext() ) ) {
			return;
		}
		$moreLink = self::getMoreLink();
		// if a "more information" URL was configured, add a link to it in the cookiewarning
		// information bar
		if ( $moreLink ) {
			$moreLink = '&#160;' . Html::element(
				'a',
				[ 'href' => $moreLink ],
				$sk->msg( 'cookiewarning-moreinfo-label' )->text()
			);
		}

		if ( !isset( $tpl->data['headelement'] ) ) {
			$tpl->data['headelement'] = '';
		}
		$form = Html::openElement( 'form', [ 'method' => 'POST' ] ) .
	        Html::submitButton(
		        $sk->msg( 'cookiewarning-ok-label' )->text(),
		        [
			        'name' => 'disablecookiewarning',
			        'class' => 'mw-cookiewarning-dismiss'
		        ]
	        ) .
	        Html::closeElement( 'form' );

		$cookieImage = Html::openElement( 'div', [ 'class' => 'mw-cookiewarning-cimage' ] ) .
			'&#127850;' .
			Html::closeElement( 'div' );

		$isMobile = ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			MobileContext::singleton()->shouldDisplayMobileView();
		$tpl->data['headelement'] .= Html::openElement(
				'div',
				[ 'class' => 'mw-cookiewarning-container' ]
			) .
		    ( $isMobile ? $form : '' ) .
			Html::openElement(
				'div',
				[ 'class' => 'mw-cookiewarning-text' ]
			) .
		    ( $isMobile ? $cookieImage : '' ) .
			Html::element(
				'span',
				[],
				$sk->msg( 'cookiewarning-info' )->text()
			) .
			$moreLink .
		    ( !$isMobile ? $form : '' ) .
			Html::closeElement( 'div' ) .
			Html::closeElement( 'div' );
	}

	/**
	 * Returns the target for the "More information" link of the cookie warning bar, if one is set.
	 * The link can be set by either (checked in this order):
	 *  - the configuration variable $wgCookieWarningMoreUrl
	 *  - the interface message MediaWiki:Cookiewarning-more-link
	 *  - the interface message MediaWiki:Cookie-policy-link (bc T145781)
	 *
	 * @return string|null The url or null if none set
	 */
	private static function getMoreLink() {
		// Config instance of CookieWarning
		$conf = ConfigFactory::getDefaultInstance()->makeConfig( 'cookiewarning' );
		if ( $conf->get( 'CookieWarningMoreUrl' ) ) {
			return $conf->get( 'CookieWarningMoreUrl' );
		}
		$cookieWarningMessage = wfMessage( 'cookiewarning-more-link' );
		if ( $cookieWarningMessage->exists() && !$cookieWarningMessage->isDisabled() ) {
			return $cookieWarningMessage->escaped();
		}
		$cookiePolicyMessage = wfMessage( 'cookie-policy-link' );
		if ( $cookiePolicyMessage->exists() && !$cookiePolicyMessage->isDisabled() ) {
			return $cookiePolicyMessage->escaped();
		}
		return null;
	}

	/**
	 * BeforePageDisplay hook handler.
	 *
	 * Adds the required style and JS module, if cookiewarning is enabled.
	 *
	 * @param OutputPage $out
	 */
	public static function onBeforePageDisplay( OutputPage $out ) {
		if ( self::showWarning( $out->getContext() ) ) {
			$conf = self::getConfig();
			if (
				ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
				MobileContext::singleton()->shouldDisplayMobileView()
			) {
				$moduleStyles = [ 'ext.CookieWarning.mobile.styles' ];
			} else {
				$moduleStyles = [ 'ext.CookieWarning.styles' ];
			}
			$modules = [ 'ext.CookieWarning' ];
			if (
				$conf->get( 'CookieWarningGeoIPLookup' ) === 'js' &&
				is_array( $conf->get( 'CookieWarningForCountryCodes' ) )
			) {
				$modules[] = 'ext.CookieWarning.geolocation';
				$moduleStyles[] = 'ext.CookieWarning.geolocation.styles';
			}
			$out->addModules( $modules );
			$out->addModuleStyles( $moduleStyles );
		}
	}

	/**
	 * ResourceLoaderGetConfigVars hook handler.
	 *
	 * @param array $vars
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		$conf = self::getConfig();
		if (
			$conf->get( 'CookieWarningGeoIPLookup' ) === 'js' &&
			is_array( $conf->get( 'CookieWarningForCountryCodes' ) )
		) {
			$vars += [
				'wgCookieWarningGeoIPServiceURL' => $conf->get( 'CookieWarningGeoIPServiceURL' ),
				'wgCookieWarningForCountryCodes' => $conf->get( 'CookieWarningForCountryCodes' ),
			];
		}
	}

	/**
	 * Retruns the Config object for the CookieWarning extension.
	 *
	 * @return Config
	 */
	private static function getConfig() {
		return ConfigFactory::getDefaultInstance()->makeConfig( 'cookiewarning' );
	}

	/**
	 * Checks, if the CookieWarning information bar should be visible to this user on
	 * this page.
	 *
	 * @param IContextSource $context
	 * @return boolean Returns true, if the cookie warning should be visible, false otherwise.
	 */
	private static function showWarning( IContextSource $context ) {
		$user = $context->getUser();
		$conf = self::getConfig();
		if (
			// if enabled in LocalSettings.php
			$conf->get( 'CookieWarningEnabled' ) &&
			// if not already dismissed by this user (and saved in the user prefs)
			!$user->getBoolOption( 'cookiewarning_dismissed', false ) &&
			// if not already dismissed by this user (and saved in the browser cookies)
			!$context->getRequest()->getCookie( 'cookiewarning_dismissed' ) &&
			(
				$conf->get( 'CookieWarningGeoIPLookup' ) === 'js' ||
				self::inConfiguredRegion( $context, $conf )
			)
		) {
			return true;
		}
		return false;
	}

	/**
	 * Checks, if the user is in one of the configured regions.
	 *
	 * @TODO: This function or the function users should set the cookie or user option, if this
	 * function returns false to avoid a location lookup on each request.
	 * @param IContextSource $context
	 * @param Config $conf
	 * @return bool
	 */
	private static function inConfiguredRegion( IContextSource $context, Config $conf ) {
		if ( self::$inConfiguredRegion === null ) {
			if (
				!$conf->get( 'CookieWarningForCountryCodes' ) ||
				$conf->get( 'CookieWarningGeoIPLookup' ) === 'none'
			) {
				wfDebugLog( 'CookieWarning', 'IP geolocation not configured, skipping.' );
				self::$inConfiguredRegion = true;
			} else {
				wfDebugLog( 'CookieWarning', 'Try to locate the user\'s IP address.' );
				$geoLocation = new GeoLocation;
				$located = $geoLocation
					->setConfig( $conf )
					->setIP( $context->getRequest()->getIP() )
					->locate();
				if ( !$located ) {
					wfDebugLog( 'CookieWarning', 'Locating the user\'s IP address failed or is' .
						' configured false.' );
					self::$inConfiguredRegion = true;
				} else {
					wfDebugLog( 'CookieWarning', 'Locating the user was successful, located' .
						' region: ' . $geoLocation->getCountryCode() );
					self::$inConfiguredRegion = array_key_exists( $geoLocation->getCountryCode(),
						$conf->get( 'CookieWarningForCountryCodes' ) );
				}
			}
		}
		return self::$inConfiguredRegion;
	}

	/**
	 * GetPreferences hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @param User $user
	 * @param array $defaultPreferences
	 * @return bool
	 */
	public static function onGetPreferences( User $user, &$defaultPreferences ) {
		$defaultPreferences['cookiewarning_dismissed'] = [
			'type' => 'api',
			'default' => '0',
		];
		return true;
	}
}
