<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\DrawioGetFile;

use BlueSpice\FlaggedRevsConnector\Hook\DrawioGetFile;
use BlueSpice\FlaggedRevsConnector\Utils;

class GetStableFile extends DrawioGetFile {

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$utils = new Utils( $this->getConfig() );
		if ( !$utils->isFlaggableNamespace( $this->file->getTitle() ) ) {
			return true;
		}
		if ( $utils->userCanAccessDrafts( $this->user ) ) {
			return true;
		}

		$stable = $utils->getApprovedRevisionId( $this->file->getTitle() );
		if ( !$stable ) {
			$file = null;
			$this->isLatestStable = false;
			return true;
		}

		$revisionStore = \MediaWiki\MediaWikiServices::getInstance()->getRevisionStore();
		$stableRevision = $revisionStore->getRevisionById( $stable );
		if ( !$stableRevision ) {
			$file = null;
			$this->isLatestStable = false;
			return true;
		}

		if ( $stableRevision->isCurrent() ) {
			$latestIsStable = true;
			return true;
		}

		$oldFiles = $this->file->getHistory();
		foreach ( $oldFiles as $oldFile ) {
			if ( $oldFile->getTimestamp() === $stableRevision->getTimestamp() ) {
				$this->file = $oldFile;
				$this->isLatestStable = false;
				return true;
			}
		}

		return true;
	}
}
