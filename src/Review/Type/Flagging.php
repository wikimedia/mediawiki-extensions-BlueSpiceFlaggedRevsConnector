<?php

namespace BlueSpice\FlaggedRevsConnector\Review\Type;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\Review\Data\Review\Record;
use BlueSpice\Review\IReviewProcess;
use BlueSpice\Review\ITarget;
use BlueSpice\Review\Notifications;
use BsReviewProcess;
use Config;
use FatalError;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use Message;
use MWException;
use Title;
use User;

class Flagging extends BsReviewProcess {
	/** @var Utils */
	private $utils;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		$type, ITarget $target, Config $config, Record $record, array $steps
	) {
		parent::__construct( $type, $target, $config, $record, $steps );
		$this->utils = MediaWikiServices::getInstance()->getService(
			'BSFlaggedRevsConnectorUtils'
		);
	}

	/**
	 *
	 * @return string
	 */
	public function getType() {
		return 'flagging';
	}

	/**
	 *
	 * @return Message
	 */
	public function getTypeMessage(): Message {
		return Message::newFromKey( 'bs-flaggedrevsconnector-review-type-flagging' );
	}

	/**
	 * @inheritDoc
	 */
	public function notify( $action, IContextSource $context, array $data = [] ) {
		if (
			$action === static::ACTION_VOTE &&
			$this->isFinished() === 'status'
		) {
			$services = MediaWikiServices::getInstance();
			/** @var \BlueSpice\NotificationManager $notificationsManager */
			$notificationsManager = $services->getService( 'BSNotificationManager' );
			$notifier = $notificationsManager->getNotifier();
			$userFactory = $services->getUserFactory();
			$notification = new Notifications\ReviewFinishAndAutoflag(
				$context->getUser(),
				Title::newFromID( $this->getPid() ),
				$userFactory->newFromId( $this->getOwner() ),
				$data['comment']
			);
			$notifier->notify( $notification );
		} else {
			parent::notify( $action, $context, $data );
		}
	}

	/**
	 * @param string $action
	 * @param IContextSource $context
	 * @param array $data
	 * @throws FatalError
	 * @throws MWException
	 */
	public function onAction( $action, IContextSource $context, array $data = [] ) {
		parent::onAction( $action, $context, $data );
		$this->autoFlag( $action, $context, $data );
	}

	/**
	 * @param string $action
	 * @param IContextSource $context
	 * @param array $data
	 * @throws FatalError
	 * @throws MWException
	 * @return void
	 */
	private function autoFlag( $action, $context, $data ) {
		if ( $action !== IReviewProcess::ACTION_VOTE ) {
			return;
		}
		if ( $data['vote'] !== 'yes' ) {
			return;
		}

		if ( $this->isFinished() !== 'status' ) {
			return;
		}

		if ( !$this->isAbortWhenDenied() ) {
			foreach ( $this->steps as $st ) {
				if ( (int)$st->status === 0 ) {
					return true;
				}
			}
		}

		$target = $this->getTarget();
		if ( !$target instanceof \BlueSpice\Review\Target\Title ) {
			return;
		}
		$title = Title::newFromID( $target->getIdentifier() );
		if ( !$title instanceof Title || !$title->exists() ) {
			return;
		}
		$bResult = true;
		MediaWikiServices::getInstance()->getHookContainer()->run( 'checkPageIsReviewable', [
			$title,
			&$bResult
		] );
		if ( !$bResult ) {
			return;
		}

		$oldValue = $GLOBALS['wgFlaggedRevsAutoReview'];
		$GLOBALS['wgFlaggedRevsAutoReview'] = true;
		$revision = MediaWikiServices::getInstance()->getRevisionLookUp()->getRevisionByTitle(
			$title
		);
		// TODO: error handling with real \Status obects and transform $status result
		$status = $this->utils->approveRevision(
			$context->getUser(),
			$title,
			$revision,
			$data['comment']
		);

		$GLOBALS['wgFlaggedRevsAutoReview'] = $oldValue;

		$title->invalidateCache();
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @param RevisionRecord $revision
	 * @param string $comment
	 * @deprecated since 4.1 - Use BlueSpice\FlaggedRevsConnector\Utils::approveRevision instead
	 * @return bool|string
	 */
	protected function doOwnWorkingReview(
		User $user, Title $title, RevisionRecord $revision, $comment = ''
	) {
		return $this->utils->approveRevision( $user, $title, $revision, $comment );
	}
}
