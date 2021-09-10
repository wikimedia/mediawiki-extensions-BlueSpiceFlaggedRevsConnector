<?php
namespace BlueSpice\FlaggedRevsConnector\Hook\SMWRevisionGuard;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\SMWConnector\Hook\SMWRevisionGuard\ChangeRevisionId;
use MediaWiki\MediaWikiServices;

class ChangeFlaggedRevisionId extends ChangeRevisionId {

	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		return !$this->getConfig()->get( 'FlaggedRevsConnectorStabilizeSMWPropertyValues' );
	}

	/**
	 *
	 * @return void
	 */
	public function doProcess() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$flaggedRevsNamespaces = $config->get( 'FlaggedRevsNamespaces' );
		if ( $this->title->exists() && in_array( $this->title->getNamespace(), $flaggedRevsNamespaces ) ) {
			$utils = new Utils( $config );
			$latestApprovedRevisionId = $utils->getApprovedRevisionId( $this->title );
			if ( $latestApprovedRevisionId !== -1 ) {
				$this->latestRevID = $latestApprovedRevisionId;
			}
		}
	}
}
