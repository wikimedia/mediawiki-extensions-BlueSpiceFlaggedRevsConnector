<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;
use FlaggableWikiPage;
use FlaggablePageView;

class ChangeRevisionForEdit extends BeforePageDisplay {

	protected function skipProcessing() {
		if ( !$this->hasDrafts() ) {
			return true;
		}
		if ( $this->showingLatest() ) {
			return true;
		}

		return false;
	}

	protected function doProcess() {
		$latestRev = $this->out->getTitle()->getLatestRevID();
		// 1 - set wgRevisionId client-side
		$this->out->addModules( 'ext.bs.flaggedrevsconnector.editversion' );
		if ( !$this->isEdit() ) {
			return true;
		}
		// 2 - if already in edit mode, set revision server-side
		$this->out->setRevisionId( $latestRev );
	}

	private function hasDrafts() {
		$fwp = FlaggableWikiPage::getTitleInstance( $this->out->getTitle() );
		return !$fwp->stableVersionIsSynced();
	}

	private function showingLatest() {
		$fpv = FlaggablePageView::singleton();
		if ( $fpv->showingStable() ) {
			return false;
		}
		$revShown = $this->out->getRevisionId();
		$latestRev = $this->out->getTitle()->getLatestRevID();
		return $revShown === $latestRev;
	}

	private function isEdit() {
		$req = $this->out->getRequest();
		$hasVeAction = (bool)$req->getVal( 'veaction', false );
		$isNormalEdit = $req->getVal( 'action', 'view' ) === 'edit';

		return $isNormalEdit || $hasVeAction;
	}

}
