<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BSExtendedSearchWikipageFetchRevision;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\Services;
use BS\ExtendedSearch\Hook\BSExtendedSearchWikipageFetchRevision;
use ConfigException;
use MediaWiki\MediaWikiServices;

class GetStableRevision extends BSExtendedSearchWikipageFetchRevision {
	/**
	 * @return bool
	 * @throws ConfigException
	 */
	protected function skipProcessing() {
		$helper = new Utils( $this->getConfig() );
		return !$this->indexOnlyApproved() || !$helper->isFlaggableNamespace( $this->title );
	}

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$helper = new Utils( $this->getConfig() );

		$revId = $helper->getApprovedRevisionId( $this->title );
		if ( $revId === -1 ) {
			$this->revision = null;
			return false;
		}

		$this->revision = MediaWikiServices::getInstance()->getRevisionStore()->getRevisionById(
			$revId
		);

		return true;
	}

	/**
	 * Should only approved revisions be indexed
	 *
	 * @return bool
	 * @throws ConfigException
	 */
	private function indexOnlyApproved() {
		$config = Services::getInstance()->getConfigFactory()->makeConfig( 'bsg' );

		return $config->has( 'FlaggedRevsConnectorIndexStableOnly' ) &&
			(bool)$config->get( 'FlaggedRevsConnectorIndexStableOnly' ) === true;
	}
}
