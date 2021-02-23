<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use BlueSpice\FlaggedRevsConnector\Data\Record;
use FlaggedRevsConnector;
use Title;
use BlueSpice\Data\Page\PrimaryDataProvider as PageDataProvider;
use FlaggableWikiPage;

class PrimaryDataProvider extends PageDataProvider {

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
			}
			$revisionsSinceStable = $flaggablePage->getPendingRevCount();
		}
		$stateMessage = wfMessage("bs-flaggedrevsconnector-state-$state" );

		$pageData = parent::getRecordFromTitle( $title )->getData();
		$pageData->{Record::REVISION_STATE} =  $stateMessage->plain();
		$pageData->{Record::REVISION_STATE_RAW} = $state;
		$pageData->{Record::REVISIONS_SINCE_STABLE} = $revisionsSinceStable;

		return new Record( $pageData );
	}
}
