<?php
class EchoCoreThanksPresentationModel extends EchoEventPresentationModel {
	/** @var LogEntry|bool|null */
	private $logEntry;

	public function canRender() {
		$hasTitle = (bool)$this->event->getTitle();
		if ( $this->getThankType() === 'log' ) {
			$logEntry = $this->getLogEntry();
			return $hasTitle && $logEntry && !$logEntry->getDeleted();
		}
		return $hasTitle;
	}

	public function getIconType() {
		return 'thanks';
	}

	public function getHeaderMessage() {
		$type = $this->event->getExtraParam( 'logid' ) ? 'log' : 'rev';
		if ( $this->isBundled() ) {
			// Message is either notification-bundle-header-rev-thank
			// or notification-bundle-header-log-thank.
			$msg = $this->msg( "notification-bundle-header-$type-thank" );
			$msg->params( $this->getBundleCount() );
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		} else {
			// Message is either notification-header-rev-thank or notification-header-log-thank.
			$msg = $this->getMessageWithAgent( "notification-header-$type-thank" );
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		}
	}

	public function getCompactHeaderMessage() {
		$msg = parent::getCompactHeaderMessage();
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	public function getBodyMessage() {
		$comment = $this->getRevOrLogComment();
		if ( $comment ) {
			$msg = new RawMessage( '$1' );
			$msg->plaintextParams( $comment );
			return $msg;
		}
	}

	private function getRevisionEditSummary() {
		if ( !$this->userCan( Revision::DELETED_COMMENT ) ) {
			return false;
		}

		$revId = $this->event->getExtraParam( 'revid', false );
		if ( !$revId ) {
			return false;
		}

		$revision = Revision::newFromId( $revId );
		if ( !$revision ) {
			return false;
		}

		$summary = $revision->getComment( Revision::RAW );
		return $summary ?: false;
	}

	/**
	 * Get the comment/summary/excerpt of the log entry or revision,
	 * for use in the notification body.
	 * @return string|bool The comment or false if it could not be retrieved.
	 */
	protected function getRevOrLogComment() {
		if ( $this->event->getExtraParam( 'logid' ) ) {
			$logEntry = $this->getLogEntry();
			if ( !$logEntry ) {
				return '';
			}
			$formatter = LogFormatter::newFromEntry( $logEntry );
			$excerpt = $formatter->getPlainActionText();
			// Turn wikitext into plaintext
			$excerpt = Linker::formatComment( $excerpt );
			$excerpt = Sanitizer::stripAllTags( $excerpt );
			return $excerpt;
		} else {
			// Try to get edit summary.
			$summary = $this->getRevisionEditSummary();
			if ( $summary ) {
				return $summary;
			}
			// Fallback on edit excerpt.
			if ( $this->userCan( Revision::DELETED_TEXT ) ) {
				return $this->event->getExtraParam( 'excerpt', false );
			}
		}
	}

	public function getPrimaryLink() {
		if ( $this->event->getExtraParam( 'logid' ) ) {
			$logId = $this->event->getExtraParam( 'logid' );
			$url = Title::newFromText( "Special:Redirect/logid/$logId" )->getCanonicalURL();
			$label = 'notification-link-text-view-logentry';
		} else {
			$url = $this->event->getTitle()->getLocalURL( [
				'oldid' => 'prev',
				'diff' => $this->event->getExtraParam( 'revid' )
			] );
			$label = 'notification-link-text-view-edit';
		}
		return [
			'url' => $url,
			// Label is only used for non-JS clients.
			'label' => $this->msg( $label )->text(),
		];
	}

	public function getSecondaryLinks() {
		$pageLink = $this->getPageLink( $this->event->getTitle(), null, true );
		if ( $this->isBundled() ) {
			return [ $pageLink ];
		} else {
			return [ $this->getAgentLink(), $pageLink ];
		}
	}

	/**
	 * @return LogEntry|false
	 */
	private function getLogEntry() {
		if ( $this->logEntry !== null ) {
			return $this->logEntry;
		}
		$logId = $this->event->getExtraParam( 'logid' );
		if ( !$logId ) {
			$this->logEntry = false;
		} else {
			$this->logEntry = DatabaseLogEntry::newFromId( $logId, wfGetDB( DB_REPLICA ) );
			if ( !$this->logEntry ) {
				$this->logEntry = false;
			}
		}
		return $this->logEntry;
	}

	/**
	 * Returns thank type
	 *
	 * @return string 'log' or 'rev'
	 */
	private function getThankType() {
		return $this->event->getExtraParam( 'logid' ) ? 'log' : 'rev';
	}
}
