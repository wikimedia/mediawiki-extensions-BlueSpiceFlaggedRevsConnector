<?php

use BlueSpice\Review\IReviewProcess;
use BlueSpice\Review\ReviewProcessFactory;
use BS\ExtendedSearch\Source\Job\UpdateRepoFile;
use MediaWiki\MediaWikiServices;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCReview {
	public static $reviewablePages = [];

	/**
	 *
	 * @param Title $oTitle
	 * @param bool &$bResult
	 * @return bool
	 */
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
	 * @return bool
	 */
	public static function onFlaggedRevsRevisionReviewFormAfterDoSubmit( $revisionReviewForm, $status ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceReview' ) === false ) {
			return true;
		}
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );

		if ( !$config->get( 'FlaggedRevsConnectorautoDeleteWorkflow' ) ) {
			return true;
		}
		$title = $revisionReviewForm->getPage();
		if ( !$title instanceof Title || !$title->exists() ) {
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

		if ( !$reviewProcess instanceof IReviewProcess || $reviewProcess->id < 1 ) {
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
