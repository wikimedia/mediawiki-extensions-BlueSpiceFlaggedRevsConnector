<?php
namespace BlueSpice\FlaggedRevsConnector\Hook\SMWRevisionGuard;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\SMWConnector\Hook\SMWRevisionGuard\IsApprovedRevision;
use MediaWiki\MediaWikiServices;

class IsApprovedFlaggedRevision extends IsApprovedRevision {

	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		return !$this->getConfig()->get( 'FlaggedRevsConnectorStabilizeSMWPropertyValues' );
	}

	/**
	 * @return bool
	 */
	public function doProcess() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$flaggedRevsNamespaces = $config->get( 'FlaggedRevsNamespaces' );
		if ( $this->title->exists()
			&& in_array( $this->title->getNamespace(), $flaggedRevsNamespaces ) ) {
			$utils = new Utils( $config );
			$latestApprovedRevisionId = $utils->getApprovedRevisionId( $this->title );
			if ( $latestApprovedRevisionId === -1 ) {
				// Allow saving attributes of "first draft"
				return true;
			}
			if ( (int)$this->latestRevID !== $latestApprovedRevisionId ) {
				return false;
			}
		}
		return true;
	}

}
