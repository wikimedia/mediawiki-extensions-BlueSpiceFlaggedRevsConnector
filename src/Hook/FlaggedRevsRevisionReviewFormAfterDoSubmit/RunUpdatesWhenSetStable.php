<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

use BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;
use DeferredUpdates;
use Exception;
use WikiPage;

class RunUpdatesWhenSetStable extends FlaggedRevsRevisionReviewFormAfterDoSubmit {

	/**
	 * @return bool
	 */
	protected function doProcess() {
		try {
			$wikiPage = WikiPage::factory( $this->revisionReviewForm->getPage() );
		} catch ( Exception $e ) {
			return true;
		}
		if ( !$wikiPage ) {
			return true;
		}
		$wikiPage->doSecondaryDataUpdates( [
			'recursive' => false,
			'defer' => DeferredUpdates::POSTSEND
		] );

		return true;
	}

	/**
	 * @return bool
	 */
	protected function skipProcessing() {
		return !$this->revisionReviewForm->getPage() || $this->status !== true;
	}
}
