<?php

namespace BlueSpice\FlaggedRevsConnector\Review\Type;

use BlueSpice\FlaggedRevsConnector\PermissionLessReviewForm;
use BlueSpice\Review\Config;
use BlueSpice\Review\IReviewProcess;
use BlueSpice\Review\Notifications;
use BsReviewProcess;
use FatalError;
use FlaggableWikiPage;
use FlaggedRevs;
use FRInclusionCache;
use Hooks;
use IContextSource;
use MediaWiki\MediaWikiServices;
use Message;
use MWException;
use Revision;
use RevisionReviewForm;
use Title;
use User;

class Flagging extends BsReviewProcess {

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
	public function getTypeMessage() : Message {
		return Message::newFromKey( 'bs-flaggedrevsconnector-review-type-flagging' );
	}

	/**
	 * @inheritDoc
	 */
	public function notify( $action, IContextSource $context, array $data = [] ) {
		if (
			$action === static::ACTION_VOTE &&
			$this->isFinished() === 'status' &&
			$this->shouldAutoReview()
		) {
			/** @var \BlueSpice\NotificationManager $notificationsManager */
			$notificationsManager = MediaWikiServices::getInstance()->getService( 'BSNotificationManager' );
			$notifier = $notificationsManager->getNotifier();
			$notification = new Notifications\ReviewFinishAndAutoflag(
				$context->getUser(),
				Title::newFromID( $this->getPid() ),
				User::newFromId( $this->getOwner() ),
				$data['comment']
			);
			$notifier->notify( $notification );
		} else {
			parent::notify( $action, $context, $data );
		}
	}

	private function shouldAutoReview() {
		return $this->getConfig()->has( Config::FLAGGEDREVSCONNECTORAUTOREVIEW ) &&
			$this->getConfig()->get( Config::FLAGGEDREVSCONNECTORAUTOREVIEW ) === true;

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
	 */
	private function autoFlag( $action, $context, $data ) {
		if ( $action !== IReviewProcess::ACTION_VOTE ) {
			return;
		}
		if ( !$this->shouldAutoReview() ) {
			return;
		}
		if ( $data['vote'] !== 'yes' ) {
			return;
		}

		if( $this->isFinished() !== 'status' ) {
			return;
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
		Hooks::run( 'checkPageIsReviewable', array( $title, &$bResult ) );
		if( !$bResult ) {
			return;
		}

		$oldValue = $GLOBALS['wgFlaggedRevsAutoReview'];
		$GLOBALS['wgFlaggedRevsAutoReview'] = true;
		// TODO: error handling with real \Status obects and transform $status result
		$status = $this->doOwnWorkingReview(
			$context->getUser(),
			$title,
			$title->getLatestRevID(),
			$data['comment']
		);

		$GLOBALS['wgFlaggedRevsAutoReview'] = $oldValue;

		$title->invalidateCache();
	}

	/**
	 * @param User $oUser
	 * @param Title $oTitle
	 * @param int $revId
	 * @param string $sComment
	 * @return bool|string
	 */
	protected function doOwnWorkingReview( User $oUser, Title $oTitle, $revId, $sComment = '' ) {
		// Have to use deprecated class, since FR expects it... :(
		$rev = Revision::newFromId( $revId );

		// Construct submit form...
		$form = new PermissionLessReviewForm( $oUser );
		$form->setPage( $oTitle );
		$form->setOldId( $revId );
		$form->setApprove( true );
		$form->setUnapprove( false );
		$form->setComment( $sComment );
		// The flagging parameters have the form 'flag_$name'.
		// Extract them and put the values into $form->dims
		foreach ( FlaggedRevs::getTags() as $tag ) {
			if ( FlaggedRevs::binaryFlagging() ) {
				$form->setDim( $tag, 1 );
			}
		}

		$article = new FlaggableWikiPage( $oTitle );
		// Get the file version used for File: pages
		$file = $article->getFile();
		if ( $file ) {
			$fileVer = array( 'time' => $file->getTimestamp(), 'sha1' => $file->getSha1() );
		} else {
			$fileVer = null;
		}
		// Now get the template and image parameters needed
		list( $templateIds, $fileTimeKeys ) =
			FRInclusionCache::getRevIncludes( $article, $rev, $oUser );
		// Get version parameters for review submission (flat strings)
		list( $templateParams, $imageParams, $fileParam ) =
			RevisionReviewForm::getIncludeParams( $templateIds, $fileTimeKeys, $fileVer );
		// Set the version parameters...
		$form->setTemplateParams( $templateParams );
		$form->setFileParams( $imageParams );
		$form->setFileVersion( $fileParam );
		// always OK; uses current templates/files
		$form->bypassValidationKey();

		// all params set
		$form->ready();

		# Try to do the actual review
		$status = $form->submit();

		return $status;
	}
}
