<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use BlueSpice\Data\Filter;
use BlueSpice\Data\Page\PrimaryDataProvider as PageDataProvider;
use BlueSpice\FlaggedRevsConnector\Data\Record;
use BlueSpice\FlaggedRevsConnector\Extension as FlaggedRevsConnector;
use MediaWiki\MediaWikiServices;
use Title;

class PrimaryDataProvider extends PageDataProvider {

	/**
	 * Category to title map array.
	 * Key is title key, value is array of categories names
	 *
	 * @var array
	 */
	private $categoryMap;

	/**
	 * FlaggedRevs pre-loaded data.
	 * For now has such structure:
	 * [
	 *   <page_id1> => [
	 *     'fp_reviewed' => <fp_reviewed1>,
	 *     'fp_stable' => <fp_stable1>
	 *   ],
	 * 	 <page_id2> => [
	 *     'fp_reviewed' => <fp_reviewed2>,
	 *     'fp_stable' => <fp_stable2>
	 *   ],
	 *   ...
	 * ]
	 *
	 *
	 * @var array
	 */
	private $flaggedRevsData;

	/**
	 * Namespaces, which can be read by current context user.
	 * It is done to save time by getting rid of per-title permission check.
	 *
	 * Has such structure:
	 * [
	 *   <nsId1> => true,
	 *   <nsId2> => true,
	 *   ...
	 * ]
	 *
	 * @var array
	 */
	private $readNamespaces = [];

	/**
	 * Inits {@link PrimaryDataProvider::$categoryMap}
	 *
	 * @see PrimaryDataProvider::$categoryMap
	 */
	private function initCategoryMap() {
		$this->categoryMap = [];

		$res = $this->db->select(
			'categorylinks',
			[ 'cl_to', 'cl_from' ],
			[],
			__METHOD__
		);

		foreach ( $res as $row ) {
			// This is a filterable field value. It must contain a value a user would set as filter
			$categoryName = str_replace( '_', ' ', $row->cl_to );
			$this->categoryMap[$row->cl_from][] = $categoryName;
		}
	}

	/**
	 * Inits {@link PrimaryDataProvider::$flaggedRevsData}
	 *
	 * @see PrimaryDataProvider::$flaggedRevsData
	 */
	private function initFlaggedRevsData() {
		$this->flaggedRevsData = [];

		$res = $this->db->select(
			'flaggedpages',
			[
				'fp_page_id',
				'fp_reviewed',
				'fp_stable'
			]
		);

		foreach ( $res as $row ) {
			$this->flaggedRevsData[$row->fp_page_id]['fp_reviewed'] = (bool)$row->fp_reviewed;
			$this->flaggedRevsData[$row->fp_page_id]['fp_stable'] = (int)$row->fp_stable;
		}
	}

	/**
	 * Inits {@link PrimaryDataProvider::$readNamespaces}
	 *
	 * @see PrimaryDataProvider::$readNamespaces
	 */
	private function initReadNamespaces() {
		$services = MediaWikiServices::getInstance();
		$pm = $services->getPermissionManager();
		$nsInfo = $services->getNamespaceInfo();
		$user = $this->context->getUser();
		$namespaces = $nsInfo->getCanonicalNamespaces();

		foreach ( $namespaces as $nsId => $nsName ) {
			$title = Title::makeTitle( $nsId, 'Dummy' );

			$userCanRead = $pm->userCan( 'read', $user, $title );
			if ( $userCanRead ) {
				$this->readNamespaces[$nsId] = true;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function makeData( $params ) {
		$this->initCategoryMap();
		$this->initReadNamespaces();
		$this->initFlaggedRevsData();

		return parent::makeData( $params );
	}

	/**
	 * @inheritDoc
	 */
	protected function skipPreFilter( Filter $filter ) {
		if ( $filter->getField() === 'page_categories' ) {
			return true;
		}
		return parent::skipPreFilter( $filter );
	}

	/**
	 * Checks if user have permission to read specified page.
	 * Permission checks are very expensive,
	 * so for performance reasons permission is not checked for each specific page, but for page namespace instead.
	 *
	 * @param Title $title Title which is checked
	 * @return bool <tt>true</tt> if user can read this page, <tt>false</tt> otherwise
	 *
	 * @see PrimaryDataProvider::$readNamespaces
	 */
	protected function userCanRead( Title $title ): bool {
		if ( $this->isSystemUser ) {
			return true;
		}

		if ( isset( $this->readNamespaces[$title->getNamespace()] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets such data to be outputted in grid as "State" (draft, implicit draft, etc.) and "Revisions since stable".
	 * Uses pre-loaded flagged revs data for that, to have one general query to load only necessary data
	 * instead of loading all title data for each title in separate queries.
	 *
	 * @param Title $title
	 * @return array
	 *
	 * @see PrimaryDataProvider::$flaggedRevsData
	 * @see \BlueSpice\FlaggedRevsConnector\Data\FlaggedPages\FlaggedPageData
	 */
	private function getFlaggedRevsOutput( Title $title ): array {
		$titleId = $title->getArticleID();

		$flaggedPageData = new FlaggedPageData( $title, $this->db );
		$state = FlaggedRevsConnector::STATE_DRAFT;
		$stableRevID = -1;
		if ( isset( $this->flaggedRevsData[$titleId] ) ) {
			$stableRevID = $this->flaggedRevsData[$titleId]['fp_stable'];
		}

		if ( $stableRevID === -1 ) {
			$state = FlaggedRevsConnector::STATE_UNMARKED;
		}

		$latestRevId = $title->getLatestRevID();

		if ( $stableRevID === $latestRevId ) {
			$state = FlaggedRevsConnector::STATE_STABLE;

			$synced = $this->flaggedRevsData[$titleId]['fp_reviewed'];

			if ( !$synced ) {
				$state = FlaggedRevsConnector::STATE_IMPLICIT_DRAFT;
			}
		}

		$revisionsSinceStable = $flaggedPageData->getPendingRevCount( $stableRevID );

		return [ $state, $revisionsSinceStable ];
	}

	/**
	 *
	 * @param Title $title
	 * @return Record
	 */
	protected function getRecordFromTitle( Title $title ) {
		$titleId = $title->getArticleID();

		$enabledNamespaces = $this->context->getConfig()->get( 'FlaggedRevsNamespaces' );
		if ( !in_array( $title->getNamespace(), $enabledNamespaces ) ) {
			$state = FlaggedRevsConnector::STATE_NOT_ENABLED;
			$revisionsSinceStable = 0;
		} else {
			list( $state, $revisionsSinceStable ) = $this->getFlaggedRevsOutput( $title );
		}

		// Give grep a chance:
		// `bs-flaggedrevsconnector-state-unmarked`
		// `bs-flaggedrevsconnector-state-stable`
		// `bs-flaggedrevsconnector-state-draft`
		// `bs-flaggedrevsconnector-state-implicit-draft`
		// `bs-flaggedrevsconnector-state-notenabled`
		$stateMessage = wfMessage( "bs-flaggedrevsconnector-state-$state" );

		$pageData = parent::getRecordFromTitle( $title )->getData();
		$pageData->{Record::REVISION_STATE} = $stateMessage->plain();
		$pageData->{Record::REVISION_STATE_RAW} = $state;
		$pageData->{Record::REVISIONS_SINCE_STABLE} = $revisionsSinceStable;
		$pageData->{Record::PAGE_CATEGORIES} = [];
		if ( !empty( $this->categoryMap[$titleId] ) ) {
			$pageData->{Record::PAGE_CATEGORIES} = $this->categoryMap[$titleId];
		}

		return new Record( $pageData );
	}
}
