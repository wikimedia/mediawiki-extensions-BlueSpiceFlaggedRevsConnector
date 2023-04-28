<?php

namespace BlueSpice\FlaggedRevsConnector\HookHandler;

use BlueSpice\FlaggedRevsConnector\Utils;
use File;
use MediaWiki\Extension\DrawioEditor\Hook\DrawioGetFileHook;
use MediaWiki\Revision\RevisionStore;
use User;

class GetStableFile implements DrawioGetFileHook {
	/** @var Utils */
	private $utils;
	/** @var RevisionStore */
	private $revisionStore;

	/**
	 * @param Utils $utils
	 * @param RevisionStore $revisionStore
	 */
	public function __construct( Utils $utils, RevisionStore $revisionStore ) {
		$this->utils = $utils;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	public function onDrawioGetFile(
		File &$file, &$latestIsStable, User $user,
		bool &$isNotApproved, File &$displayFile
	) {
		if ( !$this->utils->isFlaggableNamespace( $file->getTitle() ) ) {
			return true;
		}
		if ( $this->utils->userCanAccessDrafts( $user ) ) {
			return true;
		}

		$stable = $this->utils->getApprovedRevisionId( $file->getTitle() );
		if ( !$stable ) {
			$isNotApproved = true;
			$latestIsStable = false;
			return true;
		}

		$stableRevision = $this->revisionStore->getRevisionById( $stable );
		if ( !$stableRevision ) {
			$isNotApproved = true;
			$latestIsStable = false;
			return true;
		}

		if ( $stableRevision->isCurrent() ) {
			$latestIsStable = true;
			return true;
		}

		$oldFiles = $file->getHistory();
		foreach ( $oldFiles as $oldFile ) {
			if ( $oldFile->getTimestamp() === $stableRevision->getTimestamp() ) {
				$displayFile = $oldFile;
				$latestIsStable = false;
				return true;
			}
		}

		return true;
	}
}
