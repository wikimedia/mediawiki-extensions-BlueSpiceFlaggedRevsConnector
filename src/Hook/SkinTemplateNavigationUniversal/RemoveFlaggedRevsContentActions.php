<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\SkinTemplateNavigationUniversal;

use BlueSpice\Hook\SkinTemplateNavigationUniversal;

class RemoveFlaggedRevsContentActions extends SkinTemplateNavigationUniversal {
	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		return !isset( $this->links['actions']['default'] );
	}

	protected function doProcess() {
		unset( $this->links['actions']['default'] );
	}

}
