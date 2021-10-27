<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

use BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

class RunUpdatesWhenSetStable extends FlaggedRevsRevisionReviewFormAfterDoSubmit {

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$dataUpdater = $this->getServices()->getService( 'BSSecondaryDataUpdater' );
		$dataUpdater->run( $this->revisionReviewForm->getPage() );

		return true;
	}

	/**
	 * @return bool
	 */
	protected function skipProcessing() {
		return !$this->revisionReviewForm->getPage() || $this->status !== true;
	}
}
