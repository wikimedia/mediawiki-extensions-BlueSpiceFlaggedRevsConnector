<?php

use BlueSpice\Review\IReviewProcess;
use BlueSpice\Review\ReviewProcessFactory;
use MediaWiki\MediaWikiServices;

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
	 * @param RevisionReviewForm $revisionReviewForm
	 * @param mixed $status - true on success, error string on failure
	 * @return boolean
	 */
	public static function onFlaggedRevsRevisionReviewFormAfterDoSubmit( $revisionReviewForm, $status ) {
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );

		if( !$config->get( 'FlaggedRevsConnectorautoDeleteWorkflow' ) ) {
			return true;
		}
		$title = $revisionReviewForm->getPage();
		if( !$title instanceof Title || !$title->exists() ) {
			return true;
		}

		$targetFactory = $services->getService( 'BSReviewTargetFactory' );
		$target = $targetFactory->newFromContext(
			RequestContext::getMain(),
			$title->getArticleID(),
			'title'
		);
		/** @var ReviewProcessFactory $processFactory */
		$processFactory = $services->getService( 'BSReviewProcessFactory' );
		/** @var IReviewProcess $reviewProcess */
		$reviewProcess = $processFactory->newFromTarget(
			$target
		);

		if( !$reviewProcess instanceof IReviewProcess || $reviewProcess->id < 1 ) {
			return true;
		}

		$processFactory->delete( $reviewProcess );

		return true;
	}
}
