<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

use BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;
use BS\ExtendedSearch\Source\Job\UpdateRepoFile;
use BS\ExtendedSearch\Source\Job\UpdateWikiPage;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use Title;

class UpdateSearchIndexAfterSetStable extends FlaggedRevsRevisionReviewFormAfterDoSubmit {

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$jobs = [
			new UpdateWikiPage( $this->revisionReviewForm->getPage() )
		];
		if ( $this->revisionReviewForm->getPage()->getNamespace() === NS_FILE ) {
			$jobs[] = new UpdateRepoFile( $this->revisionReviewForm->getPage() );
		}
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobs );

		return true;
	}

	/**
	 * @return bool
	 */
	protected function skipProcessing() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceExtendedSearch' ) ) {
			return true;
		}
		return !$this->revisionReviewForm->getPage() instanceof Title || $this->status !== true;
	}
}
