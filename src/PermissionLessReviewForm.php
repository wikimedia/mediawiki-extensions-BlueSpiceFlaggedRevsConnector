<?php

namespace BlueSpice\FlaggedRevsConnector;

use RevisionReviewForm;

/**
 * This is not hacky at all. No need to read this code. Go away ;)
 */
class PermissionLessReviewForm extends RevisionReviewForm {
	/**
	 * user::isAllowed is the last ceck in parent, so we are fine to just ignore it,
	 * wenever the status is review_denied
	 * @return string|bool
	 */
	protected function doCheckParameters() {
		$status = parent::doCheckParameters();
		if( $status !== 'review_denied' ) {
			return $status;
		}
		return true;
	}

	/**
	 * Title::userCan check is ignored here
	 * @return bool
	 */
	public function isAllowed() {
		return true;
	}
}
