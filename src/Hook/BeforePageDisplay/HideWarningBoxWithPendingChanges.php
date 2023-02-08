<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;

class HideWarningBoxWithPendingChanges extends BeforePageDisplay {

	/**
	 * Hook will be called only on Special:Watchlist and nowhere else
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		$title = $this->out->getTitle();
		if ( !$title->isSpecial( 'Watchlist' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Adding css file which disabled Warning Box with pending changes
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$this->out->addModuleStyles( 'ext.bluespice.flaggedrevsconnector.warningbox' );
		return true;
	}
}
