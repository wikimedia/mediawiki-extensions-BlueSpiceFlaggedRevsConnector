<?php

use BlueSpice\FlaggedRevsConnector\PermissionLessReviewForm;
use BS\ExtendedSearch\Source\Job\UpdateRepoFile;

class FRCReview {

	public static $reviewablePages = [];

	public static function onCheckPageIsReviewable( $oTitle, &$bResult ) {
		if ( isset( static::$reviewablePages[(int)$oTitle->getArticleId()] ) ) {
			$bResult = static::$reviewablePages[(int)$oTitle->getArticleId()];
			return static::$reviewablePages[(int)$oTitle->getArticleId()];
		}
		static::$reviewablePages[(int)$oTitle->getArticleId()]
			= FlaggableWikiPage::getTitleInstance( $oTitle )->isReviewable();
		$bResult = static::$reviewablePages[(int)$oTitle->getArticleId()];
		return static::$reviewablePages[(int)$oTitle->getArticleId()];
	}

	/**
	 * @param Review $oReviewInstance
	 * @param int $step_id
	 * @param BsReviewProcess $oReviewProcess
	 * @param Title $oTitle
	 * @param stdClass $oParams
	 * @param User $oUser
	 * @param Status $oStatus
	 * @return boolean
	 */
	public static function onBSReviewVoteComplete( $oReviewInstance, $step_id, $oReviewProcess, $oTitle, $oParams, $oUser, $oStatus ) {
		$config = \BlueSpice\Services::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		if( !$config->get( 'FlaggedRevsConnectorautoReview' ) ) {
			return true;
		}
		if( $oReviewProcess->isFinished() !== 'status' ) {
			return true;
		}
		if ( $oReviewProcess->getType() !== FlaggedRevsConnector::REVIEW_TYPE_FLAGGING ) {
			// Only handle flagging review types
			return true;
		}
		if ( !$oReviewProcess->isAbortWhenDenied() ) {
			foreach ( $oReviewProcess->steps as $st ) {
				if ( (int) $st->status === 0 ) {
					return true;
				}
			}
		}
		$bResult = true;
		\Hooks::run( 'checkPageIsReviewable', array( $oTitle, &$bResult ) );
		if( !$bResult ) {
			return true;
		}

		global $wgFlaggedRevsAutoReview;
		$tmp = $wgFlaggedRevsAutoReview;
		$wgFlaggedRevsAutoReview = true;
		$status = static::doOwnWorkingReview(
			$oUser,
			$oTitle,
			$oTitle->getLatestRevID()
		);

		$wgFlaggedRevsAutoReview = $tmp;

		$oTitle->invalidateCache();

		//unfortunatley $bRes will always be true... so just return and do not
		//modify the status object on error
		if( $status !== true ) {
			$oStatus->warning( $status );
		}
		return true;
	}

	/**
	 * @param RevisionReviewForm $oRevisionReviewForm
	 * @param mixed $status - true on success, error string on failure
	 * @return boolean
	 */
	public static function onFlaggedRevsRevisionReviewFormAfterDoSubmit( $oRevisionReviewForm, $status ) {
		$config = \BlueSpice\Services::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		if( !$config->get( 'FlaggedRevsConnectorautoDeleteWorkflow' ) ) {
			return true;
		}
		if( !$oTitle = $oRevisionReviewForm->getPage() ) {
			return true;
		}
		self::updateSearchIndex( $oTitle );

		$oBsReviewProcess = BsReviewProcess::newFromPid(
			(int) $oTitle->getArticleId()
		);
		if( !$oBsReviewProcess instanceof BsReviewProcess ) {
			return true;
		}

		BsReviewProcess::removeReviews( (int) $oTitle->getArticleId() );
		$oTitle->invalidateCache();

		$aParams = array(
			'action' => 'delete',
			'target' => $oTitle,
			'comment' => '',
			'params' => null,
			'doer' => $oRevisionReviewForm->getUser()
		);
		$oReview = BsExtensionManager::getExtension( 'Review' );
		$oReview->getLogger()->addEntry(
			$aParams[ 'action' ],
			$aParams[ 'target' ],
			$aParams[ 'comment' ],
			$aParams[ 'params' ],
			$aParams[ 'doer' ]
		);

		return true;
	}

	/**
	 * @param User $oUser
	 * @param Title $oTitle
	 * @param integer $revid
	 * @param string $sComment
	 * @return boolean
	 */
	public static function doOwnWorkingReview( User $oUser, Title $oTitle, $revid, $sComment = '' ) {
		$rev = Revision::newFromId( $revid );
		// Construct submit form...
		$form = new PermissionLessReviewForm( $oUser );
		$form->setPage( $oTitle );
		$form->setOldId( $revid );
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
		$form->bypassValidationKey(); // always OK; uses current templates/files

		$form->ready(); // all params set

		# Try to do the actual review
		$status = $form->submit();

		self::updateSearchIndex( $oTitle );

		# Approve/de-approve success
		/*if ( $status === true ) {

		}*/
		return $status;
	}

	/**
	 * @param Title $title
	 */
	protected static function updateSearchIndex( $title ) {
		\JobQueueGroup::singleton()->push(
			new \BS\ExtendedSearch\Source\Job\UpdateWikiPage( $title )
		);
		if ( $title->getNamespace() === NS_FILE ) {
			JobQueueGroup::singleton()->push(
				new UpdateRepoFile( $title )
			);
		}
	}
}
