<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use BlueSpice\Data\Page\PrimaryDataProvider as PageDataProvider;
use BlueSpice\FlaggedRevsConnector\Data\Record;
use FlaggableWikiPage;
use FlaggedRevsConnector;
use Title;

class PrimaryDataProvider extends PageDataProvider {

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

		return new Record( $pageData );
	}
}
