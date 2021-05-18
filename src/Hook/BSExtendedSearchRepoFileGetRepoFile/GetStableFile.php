<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BSExtendedSearchRepoFileGetRepoFile;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\Services;
use BS\ExtendedSearch\Hook\BSExtendedSearchRepoFileGetRepoFile;
use ConfigException;
use MediaWiki\MediaWikiServices;

class GetStableFile extends BSExtendedSearchRepoFileGetRepoFile {

	/**
	 * @return bool
	 * @throws ConfigException
	 */
	protected function skipProcessing() {
		$helper = new Utils( $this->getConfig() );
		return !$this->indexOnlyApproved() || !$helper->isFlaggableNamespace( $this->file->getTitle() );
	}

	protected function doProcess() {
		$helper = new Utils( $this->getConfig() );

		$revId = $helper->getApprovedRevisionId( $this->file->getTitle() );
		if ( !$revId ) {
			$this->file = null;
			return false;
		}
		if ( $this->file->getTitle()->getLatestRevID() === $revId ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();

		$revision = $services->getRevisionStore()->getRevisionById(
			$revId
		);

		$this->file = $services->getRepoGroup()->findFile(
			$this->file->getTitle(), [ 'time' => $revision->getTimestamp() ]
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
