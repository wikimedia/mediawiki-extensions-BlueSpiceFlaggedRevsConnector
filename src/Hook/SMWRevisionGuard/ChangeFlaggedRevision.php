<?php
namespace BlueSpice\FlaggedRevsConnector\Hook\SMWRevisionGuard;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\SMWConnector\Hook\SMWRevisionGuard\ChangeRevision;
use MediaWiki\MediaWikiServices;
use Revision;

class ChangeFlaggedRevision extends ChangeRevision {

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
		if ( $this->title->exists()
			&& in_array( $this->title->getNamespace(), $flaggedRevsNamespaces ) ) {
			$utils = new Utils( $config );
			$latestApprovedRevisionId = $utils->getApprovedRevisionId( $this->title );
			if ( $latestApprovedRevisionId === -1 ) {
				return;
			}
			$approvedRev = Revision::newFromId( $latestApprovedRevisionId );
			if ( $approvedRev instanceof Revision ) {
				$this->revision = $approvedRev;
			}
		}
	}

}
