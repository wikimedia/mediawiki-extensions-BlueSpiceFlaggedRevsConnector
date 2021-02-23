<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

use BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;
use BS\ExtendedSearch\Source\Job\UpdateRepoFile;
use BS\ExtendedSearch\Source\Job\UpdateWikiPage;
use JobQueueGroup;
use Title;

class UpdateSearchIndexAfterSetStable extends FlaggedRevsRevisionReviewFormAfterDoSubmit {

	/**
	 * @return bool
	 */
	protected function doProcess() {
		JobQueueGroup::singleton()->push(
			new UpdateWikiPage( $this->revisionReviewForm->getPage() )
		);
		if ( $this->revisionReviewForm->getPage()->getNamespace() === NS_FILE ) {
			JobQueueGroup::singleton()->push(
				new UpdateRepoFile( $this->revisionReviewForm->getPage() )
			);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function skipProcessing() {
		return !$this->revisionReviewForm->getPage() instanceof Title || $this->status !== true;
	}
}
