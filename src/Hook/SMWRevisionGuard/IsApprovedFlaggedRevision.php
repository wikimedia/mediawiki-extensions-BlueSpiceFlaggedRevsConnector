<?php
namespace BlueSpice\FlaggedRevsConnector\Hook\SMWRevisionGuard;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\SMWConnector\Hook\SMWRevisionGuard\IsApprovedRevision;
use MediaWiki\MediaWikiServices;

class IsApprovedFlaggedRevision extends IsApprovedRevision {

	/**
	 * @return bool
	 */
	public function doProcess() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$flaggedRevsNamespaces = $config->get( 'FlaggedRevsNamespaces' );
		if ( $this->title->exists() && in_array( $this->title->getNamespace(), $flaggedRevsNamespaces ) ) {
			$utils = new Utils( $config );
			$latestApprovedRevisionId = $utils->getApprovedRevisionId( $this->title );
			if ( (int)$this->latestRevID !== $latestApprovedRevisionId ) {
				return false;
			}
		}
		return true;
	}

}
