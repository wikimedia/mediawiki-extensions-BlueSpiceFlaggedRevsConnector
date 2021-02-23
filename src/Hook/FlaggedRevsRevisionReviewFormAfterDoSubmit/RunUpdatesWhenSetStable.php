<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

use BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;
use WikiPage;

class RunUpdatesWhenSetStable extends FlaggedRevsRevisionReviewFormAfterDoSubmit {

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$wikipage = WikiPage::factory( $this->revisionReviewForm->getPage() );
		$content = $wikipage->getContent();
		if ( !$content ) {
		   return;
		}
		$updates = $content->getSecondaryDataUpdates( $this->revisionReviewForm->getPage() );
		foreach ( $updates as $update ) {
			$update->doUpdate();
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function skipProcessing() {
		return !$this->revisionReviewForm->getPage() || $this->status !== true;
	}
}
