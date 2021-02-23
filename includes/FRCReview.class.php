<?php

use BlueSpice\Review\IReviewProcess;
use BlueSpice\Review\ReviewProcessFactory;
use MediaWiki\MediaWikiServices;
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

		static::updateSearchIndex( $title );
		return true;
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
