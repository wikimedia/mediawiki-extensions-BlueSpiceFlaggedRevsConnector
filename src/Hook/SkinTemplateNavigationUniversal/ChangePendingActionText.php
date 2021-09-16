<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\SkinTemplateNavigationUniversal;

use BlueSpice\Hook\SkinTemplateNavigationUniversal;

class ChangePendingActionText extends SkinTemplateNavigationUniversal {
	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		return !isset( $this->links['views']['current'] );
	}

	protected function doProcess() {
		$this->links['views']['current']['text']
			= $this->msg( "bs-flaggedrevsconnector-state-draft" )->plain();
	}

}
