<?php

use MediaWiki\MediaWikiServices;

/**
 * Hooks for Thanks extension
 *
 * @file
 * @ingroup Extensions
 */
class ThanksHooks {

	/**
	 * ResourceLoaderTestModules hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * @param array &$testModules The modules array to add to.
	 * @param ResourceLoader &$resourceLoader The resource loader.
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( array &$testModules,
		ResourceLoader &$resourceLoader
	) {
		if ( class_exists( 'SpecialMobileDiff' ) ) {
			$testModules['qunit']['tests.ext.thanks.mobilediff'] = [
				'localBasePath' => dirname( __DIR__ ),
				'remoteExtPath' => 'Thanks',
				'dependencies' => [ 'ext.thanks.mobilediff' ],
				'scripts' => [
					'tests/qunit/test_ext.thanks.mobilediff.js',
				],
				'targets' => [ 'desktop', 'mobile' ],
			];
		}
		return true;
	}

	/**
	 * Handler for HistoryRevisionTools and DiffRevisionTools hooks.
	 * Inserts 'thank' link into revision interface
	 * @param Revision $rev Revision object to add the thank link for
	 * @param array &$links Links to add to the revision interface
	 * @param Revision|null $oldRev Revision object of the "old" revision when viewing a diff
	 * @param User $user The user performing the thanks.
	 * @return bool
	 */
	public static function insertThankLink( $rev, &$links, $oldRev, User $user ) {
		$recipientId = $rev->getUser();
		$recipient = User::newFromId( $recipientId );
		// Don't let users thank themselves.
		// Exclude anonymous users.
		// Exclude users who are blocked.
		// Check whether bots are allowed to receive thanks.
		if ( !$user->isAnon()
			&& $recipientId !== $user->getId()
			&& !$user->isBlocked()
			&& !$user->isBlockedGlobally()
			&& self::canReceiveThanks( $recipient )
			&& !$rev->isDeleted( Revision::DELETED_TEXT )
			&& ( !$oldRev || $rev->getParentId() == $oldRev->getId() )
		) {
			$links[] = self::generateThankElement( $rev->getId(), $recipient );
		}
		return true;
	}

	/**
	 * Check whether a user is allowed to receive thanks or not
	 *
	 * @param User $user Recipient
	 * @return bool true if allowed, false if not
	 */
	protected static function canReceiveThanks( User $user ) {
		global $wgThanksSendToBots;

		if ( $user->isAnon() ) {
			return false;
		}

		if ( !$wgThanksSendToBots && in_array( 'bot', $user->getGroups() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Helper for self::insertThankLink
	 * Creates either a thank link or thanked span based on users session
	 * @param int $id Revision or log ID to generate the thank element for.
	 * @param User $recipient User who receives thanks notification.
	 * @param string $type Either 'revision' or 'log'.
	 * @return string
	 */
	protected static function generateThankElement( $id, $recipient, $type = 'revision' ) {
		global $wgUser;
		// Check if the user has already thanked for this revision or log entry.
		// Session keys are backwards-compatible, and are also used in the ApiCoreThank class.
		$sessionKey = ( $type === 'revision' ) ? $id : $type . $id;
		if ( $wgUser->getRequest()->getSessionData( "thanks-thanked-$sessionKey" ) ) {
			return Html::element(
				'span',
				[ 'class' => 'mw-thanks-thanked' ],
				wfMessage( 'thanks-thanked', $wgUser, $recipient->getName() )->text()
			);
		}

		$genderCache = MediaWikiServices::getInstance()->getGenderCache();
		// Add 'thank' link
		$tooltip = wfMessage( 'thanks-thank-tooltip' )
				->params( $wgUser->getName(), $recipient->getName() )
				->text();

		$subpage = ( $type === 'revision' ) ? '' : 'Log/';
		return Html::element(
			'a',
			[
				'class' => 'mw-thanks-thank-link',
				'href' => SpecialPage::getTitleFor( 'Thanks', $subpage . $id )->getFullURL(),
				'title' => $tooltip,
				'data-' . $type . '-id' => $id,
				'data-recipient-gender' => $genderCache->getGenderOf( $recipient->getName(), __METHOD__ ),
			],
			wfMessage( 'thanks-thank', $wgUser, $recipient->getName() )->text()
		);
	}

	/**
	 * @param OutputPage $outputPage The OutputPage to add the module to.
	 */
	protected static function addThanksModule( OutputPage $outputPage ) {
		$confirmationRequired = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'ThanksConfirmationRequired' );
		$outputPage->addModules( [ 'ext.thanks.corethank' ] );
		$outputPage->addJsConfigVars( 'thanks-confirmation-required', $confirmationRequired );
	}

	/**
	 * Handler for PageHistoryBeforeList hook.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/PageHistoryBeforeList
	 * @param WikiPage|Article|ImagePage|CategoryPage|Page &$page The page for which the history
	 *   is loading.
	 * @param RequestContext $context RequestContext object
	 * @return bool true in all cases
	 */
	public static function onPageHistoryBeforeList( &$page, $context ) {
		if ( $context->getUser()->isLoggedIn() ) {
			static::addThanksModule( $context->getOutput() );
		}
		return true;
	}

	/**
	 * Handler for DiffViewHeader hook.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/DiffViewHeader
	 * @param DifferenceEngine $diff DifferenceEngine object that's calling.
	 * @param Revision $oldRev Revision object of the "old" revision (may be null/invalid)
	 * @param Revision $newRev Revision object of the "new" revision
	 * @return bool true in all cases
	 */
	public static function onDiffViewHeader( $diff, $oldRev, $newRev ) {
		if ( $diff->getUser()->isLoggedIn() ) {
			static::addThanksModule( $diff->getOutput() );
		}
		return true;
	}

	/**
	 * Add Thanks events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['edit-thank'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-edit-thank',
		];

		$notifications['edit-thank'] = [
			'category' => 'edit-thank',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => 'EchoCoreThanksPresentationModel',
			'bundle' => [
				'web' => true,
				'expandable' => true,
			],
		];

		if ( class_exists( Flow\FlowPresentationModel::class ) ) {
			$notifications['flow-thank'] = [
				'category' => 'edit-thank',
				'group' => 'positive',
				'section' => 'message',
				'presentation-model' => 'EchoFlowThanksPresentationModel',
				'bundle' => [
					'web' => true,
					'expandable' => true,
				],
			];
		}

		$icons['thanks'] = [
			'path' => [
				'ltr' => 'Thanks/thanks-green-ltr.svg',
				'rtl' => 'Thanks/thanks-green-rtl.svg'
			]
		];

		return true;
	}

	/**
	 * Add user to be notified on echo event
	 * @param EchoEvent $event The event.
	 * @param User[] &$users The user list to add to.
	 * @return bool
	 */
	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		switch ( $event->getType() ) {
			case 'edit-thank':
			case 'flow-thank':
				$extra = $event->getExtra();
				if ( !$extra || !isset( $extra['thanked-user-id'] ) ) {
					break;
				}
				$recipientId = $extra['thanked-user-id'];
				$recipient = User::newFromId( $recipientId );
				$users[$recipientId] = $recipient;
				break;
		}
		return true;
	}

	/**
	 * Handler for LocalUserCreated hook
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * @param User $user User object that was created.
	 * @param bool $autocreated True when account was auto-created
	 * @return bool
	 */
	public static function onAccountCreated( $user, $autocreated ) {
		// New users get echo preferences set that are not the default settings for existing users.
		// Specifically, new users are opted into email notifications for thanks.
		if ( !$autocreated ) {
			$user->setOption( 'echo-subscriptions-email-edit-thank', true );
			$user->saveSettings();
		}
		return true;
	}

	/**
	 * Add thanks button to SpecialMobileDiff page
	 * @param OutputPage &$output OutputPage object
	 * @param MobileContext $ctx MobileContext object
	 * @param array $revisions Array of the two revisions that are being compared in the diff
	 * @return bool true in all cases
	 */
	public static function onBeforeSpecialMobileDiffDisplay( &$output, $ctx, $revisions ) {
		$rev = $revisions[1];

		// If the MobileFrontend extension is installed and the user is
		// logged in or recipient is not a bot if bots cannot receive thanks, show a 'Thank' link.
		if ( $rev
			&& class_exists( 'SpecialMobileDiff' )
			&& self::canReceiveThanks( User::newFromId( $rev->getUser() ) )
			&& $output->getUser()->isLoggedIn()
		) {
			$output->addModules( [ 'ext.thanks.mobilediff' ] );

			if ( $output->getRequest()->getSessionData( 'thanks-thanked-' . $rev->getId() ) ) {
				// User already sent thanks for this revision
				$output->addJsConfigVars( 'wgThanksAlreadySent', true );
			}

		}
		return true;
	}

	/**
	 * Handler for GetLogTypesOnUser.
	 * So users can just type in a username for target and it'll work.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/GetLogTypesOnUser
	 * @param string[] &$types The list of log types, to add to.
	 * @return bool
	 */
	public static function onGetLogTypesOnUser( array &$types ) {
		$types[] = 'thanks';
		return true;
	}

	/**
	 * Handler for BeforePageDisplay.  Inserts javascript to enhance thank
	 * links from static urls to in-page dialogs along with reloading
	 * the previously thanked state.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out OutputPage object
	 * @param Skin $skin The skin in use.
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage $out, $skin ) {
		$title = $out->getTitle();
		// Add to Flow boards.
		if ( $title instanceof Title && $title->hasContentModel( 'flow-board' ) ) {
			$out->addModules( 'ext.thanks.flowthank' );
		}
		// Add to Special:Log.
		if ( $title->isSpecial( 'Log' ) ) {
			static::addThanksModule( $out );
		}
		return true;
	}

	/**
	 * Conditionally load API module 'flowthank' depending on whether or not
	 * Flow is installed.
	 *
	 * @param ApiModuleManager $moduleManager Module manager instance
	 * @return bool
	 */
	public static function onApiMainModuleManager( ApiModuleManager $moduleManager ) {
		if ( class_exists( 'FlowHooks' ) ) {
			$moduleManager->addModule(
				'flowthank',
				'action',
				'ApiFlowThank'
			);
		}
		return true;
	}

	/**
	 * Handler for EchoGetBundleRule hook, which defines the bundle rules for each notification.
	 *
	 * @param EchoEvent $event The event being notified.
	 * @param string &$bundleString Determines how the notification should be bundled.
	 * @return bool True for success
	 */
	public static function onEchoGetBundleRules( $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'edit-thank':
				$bundleString = 'edit-thank';
				// Try to get either the revid (old name) or id (new name) parameter.
				$revOrLogId = $event->getExtraParam( 'revid' );
				if ( !$revOrLogId ) {
					$revOrLogId = $event->getExtraParam( 'id' );
				}
				if ( $revOrLogId ) {
					$bundleString .= $revOrLogId;
				}
				break;
			case 'flow-thank':
				$bundleString = 'flow-thank';
				$postId = $event->getExtraParam( 'post-id' );
				if ( $postId ) {
					$bundleString .= $postId;
				}
				break;
		}
		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LogEventsListLineEnding
	 * @param LogEventsList $page The log events list.
	 * @param string &$ret The lineending HTML, to modify.
	 * @param DatabaseLogEntry $entry The log entry.
	 * @param string[] &$classes CSS classes to add to the line.
	 * @param string[] &$attribs HTML attributes to add to the line.
	 * @throws ConfigException
	 */
	public static function onLogEventsListLineEnding(
		LogEventsList $page, &$ret, DatabaseLogEntry $entry, &$classes, &$attribs
	) {
		global $wgUser;

		// Don't thank if anonymous or blocked
		if ( $wgUser->isAnon() || $wgUser->isBlocked() || $wgUser->isBlockedGlobally() ) {
			return;
		}

		// Make sure this log type is whitelisted.
		$logTypeWhitelist = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'ThanksLogTypeWhitelist' );
		if ( !in_array( $entry->getType(), $logTypeWhitelist ) ) {
			return;
		}

		// Don't thank if no recipient,
		// or if recipient is the current user or unable to receive thanks.
		// Don't check for deleted revision (this avoids extraneous queries from Special:Log).
		$recipient = $entry->getPerformer();
		if ( !$recipient
			|| $recipient->getId() === $wgUser->getId()
			|| !self::canReceiveThanks( $recipient )
		) {
			return;
		}

		// Create thank link either for the revision (if there is an associated revision ID)
		// or the log entry.
		$type = $entry->getAssociatedRevId() ? 'revision' : 'log';
		$id = $entry->getAssociatedRevId() ? $entry->getAssociatedRevId() : $entry->getId();
		$thankLink = self::generateThankElement( $id, $recipient, $type );

		// Add parentheses to match what's done with Thanks in revision lists and diff displays.
		$ret .= ' ' . wfMessage( 'parentheses' )->rawParams( $thankLink )->escaped();
	}
}
