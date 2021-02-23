<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\EditPageGetCheckboxesDefinition;

use BlueSpice\Hook\EditPageGetCheckboxesDefinition;

class ReplaceVisualEditorAutoReview extends EditPageGetCheckboxesDefinition {

	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		return !isset( $this->checkboxes['wpReviewEdit'] );
	}

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$this->checkboxes['bswpReviewEdit'] = $this->checkboxes['wpReviewEdit'];
		$this->checkboxes['bswpReviewEdit']['default']
			= $this->getContext()->getRequest()->getCheck( 'bswpReviewEdit' )
				|| $this->getContext()->getRequest()->getCheck( 'wpReviewEdit' );
		$this->checkboxes['bswpReviewEdit']['id'] = 'bswpReviewEdit';
		unset( $this->checkboxes['wpReviewEdit'] );

		return true;
	}

}
