<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use BlueSpice\Data\Filter;
use BlueSpice\Data\Page\PrimaryDataProvider as PageDataProvider;
use BlueSpice\FlaggedRevsConnector\Data\Record;
use FlaggableWikiPage;
use FlaggedRevsConnector;
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
	 * Inits {@link PrimaryDataProvider::$categoryMap}
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
			$this->categoryMap[$row->cl_from][] = $row->cl_to;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function makeData( $params ) {
		$this->initCategoryMap();
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
	 *
	 * @param Title $title
	 * @return Record
	 */
	protected function getRecordFromTitle( Title $title ) {
		$enabledNamespaces = $this->context->getConfig()->get( 'FlaggedRevsNamespaces' );
		if ( !in_array( $title->getNamespace(), $enabledNamespaces ) ) {
			$state = FlaggedRevsConnector::STATE_NOT_ENABLED;
			$revisionsSinceStable = 0;
		} else {
			$flaggablePage = FlaggableWikiPage::getTitleInstance(
				$title
			);

			$state = FlaggedRevsConnector::STATE_DRAFT;
			$stableRevID = $flaggablePage->getStable();
			if ( !$stableRevID ) {
				$state = FlaggedRevsConnector::STATE_UNMARKED;
			}
			if ( $stableRevID === $title->getLatestRevID() ) {
				$state = FlaggedRevsConnector::STATE_STABLE;
				if ( !$flaggablePage->stableVersionIsSynced() ) {
					$state = FlaggedRevsConnector::STATE_IMPLICIT_DRAFT;
				}
			}
			$revisionsSinceStable = $flaggablePage->getPendingRevCount();
		}
		$stateMessage = wfMessage( "bs-flaggedrevsconnector-state-$state" );

		$pageData = parent::getRecordFromTitle( $title )->getData();
		$pageData->{Record::REVISION_STATE} = $stateMessage->plain();
		$pageData->{Record::REVISION_STATE_RAW} = $state;
		$pageData->{Record::REVISIONS_SINCE_STABLE} = $revisionsSinceStable;
		$pageData->{Record::PAGE_CATEGORIES} = [];
		if ( !empty( $this->categoryMap[$title->getArticleID()] ) ) {
			$pageData->{Record::PAGE_CATEGORIES} = $this->categoryMap[$title->getArticleID()];
		}

		return new Record( $pageData );
	}
}
