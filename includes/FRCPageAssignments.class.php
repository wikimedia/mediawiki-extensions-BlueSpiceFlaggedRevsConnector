<?php

use BlueSpice\FlaggedRevsConnector\Notifications\PageReview;

use MediaWiki\MediaWikiServices;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCPageAssignments {
	/**
	 * Augment store data
	 * @param BSApiMyPageAssignmentStore $oApiModule
	 * @param array &$aData
	 * @return bool
	 */
	public function onBSApiExtJSStoreBaseBeforePostProcessData( $oApiModule, &$aData ) {
		if ( $oApiModule instanceof BSApiMyPageAssignmentStore ) {
			$this->extendBSApiMyPageAssignmentStore( $aData );
		}
		return true;
	}

	/**
	 * Append "last_stable_date" field to each dataset
	 * @param array &$aData
	 * @return bool
	 */
	protected function extendBSApiMyPageAssignmentStore( &$aData ) {
		$aPageIds = [];
		foreach ( $aData as $oDataSet ) {
			$oDataSet->last_stable_date = null;
			$aPageIds[] = $oDataSet->page_id;
		}
		if ( empty( $aPageIds ) ) {
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'flaggedpages',
			[ 'fp_page_id', 'fp_stable' ],
			[
				'fp_page_id' => $aPageIds
			],
			__METHOD__
		);

		$aStableRevisions = [];
		foreach ( $res as $row ) {
			$aStableRevisions[] = $row->fp_stable;
		}

		if ( empty( $aStableRevisions ) ) {
			return true;
		}

		$res = $dbr->select(
			'flaggedrevs',
			[ 'fr_page_id', 'fr_timestamp' ],
			[
				'fr_rev_id' => $aStableRevisions,
			],
			__METHOD__
		);

		$aStablePages = [];
		foreach ( $res as $row ) {
			$aStablePages[$row->fr_page_id] = $row->fr_timestamp;
		}

		foreach ( $aData as $oDataSet ) {
			if ( isset( $aStablePages[$oDataSet->page_id] ) ) {
				$oDataSet->last_stable_date = $aStablePages[$oDataSet->page_id];
			}
		}
	}

	/**
	 *
	 * @param SpecialPageAssignments $oSender
	 * @param array &$aDeps
	 * @return bool
	 */
	public function onBSPageAssignmentsOverview( $oSender, &$aDeps ) {
		$aDeps[] = 'bluespice.flaggedRevsConnector.pageassignmentintegration';
		return true;
	}

	/**
	 * Notify assignees about successfull review
	 * @param ApiBase $module
	 * @return bool
	 */
	public function onAPIAfterExecute( $module ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpicePageAssignments' ) ) {
			return true;
		}

		if ( $module instanceof ApiReview === false ) {
			return true;
		}

		$aResult = $module->getResult()->getResultData();

		if ( !isset( $aResult['review'] ) || !isset( $aResult['review']['result'] ) ) {
			return true;
		}

		// Only send in case of successfull action
		if ( strtolower( $aResult['review']['result'] ) !== 'success' ) {
			return true;
		}

		$oTitle = self::getTitleFromAPIParam( $module->getRequest() );
		if ( $oTitle === null ) {
			return true;
		}

		$factory = MediaWikiServices::getInstance()->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		if ( !$factory ) {
			return true;
		}
		$target = $factory->newFromTargetTitle( $oTitle );
		if ( !$target ) {
			return true;
		}

		$notificationsManager = MediaWikiServices::getInstance()->getService( 'BSNotificationManager' );
		$notifier = $notificationsManager->getNotifier();

		$notification = new PageReview( $module->getUser(), $oTitle, $target );
		$notifier->notify( $notification );

		return true;
	}

	/**
	 * When in APIReview context this extracts the 'revid' parameter and
	 * builds a Title object
	 * @param WebRequest $oWebRequest
	 * @return Title|null
	 */
	protected static function getTitleFromAPIParam( $oWebRequest ) {
		$iRevId = $oWebRequest->getVal( 'revid', -1 );
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$oRevision = $revisionLookup->getRevisionById( $iRevId );
		if ( $oRevision === null ) {
			return null;
		}
		return $oRevision->getPageAsLinkTarget();
	}
}
