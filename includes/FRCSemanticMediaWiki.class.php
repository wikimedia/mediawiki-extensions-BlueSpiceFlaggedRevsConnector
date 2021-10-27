<?php

use MediaWiki\MediaWikiServices;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCSemanticMediaWiki {

	/**
	 * Update Semantic MediaWiki data when a revision got flagged by a user
	 *
	 * @param ApiReview|stdClass $module
	 * @return bool
	 * @throws MWException
	 */
	public function onAPIAfterExecute( $module ) {
		if ( $module instanceof ApiReview === false ) {
			return true;
		}

		$aResult = $module->getResult()->getResultData();

		if ( !isset( $aResult['review'] ) || !isset( $aResult['review']['result'] ) ) {
			return true;
		}

		// Only update in case of successfull action
		if ( strtolower( $aResult['review']['result'] ) !== 'success' ) {
			return true;
		}

		$oTitle = $this->getTitleFromAPIParam( $module->getRequest() );
		if ( !$oTitle ) {
			return true;
		}
		$dataUpdater = MediaWikiServices::getInstance()->getService( 'BSSecondaryDataUpdater' );
		$dataUpdater->run( $oTitle );
		return true;
	}

	/**
	 * When in APIReview context this extracts the 'revid' parameter and
	 * builds a Title object
	 * @param WebRequest $oWebRequest
	 * @return Title|null
	 */
	protected function getTitleFromAPIParam( $oWebRequest ) {
		$iRevId = $oWebRequest->getVal( 'revid', -1 );
		$oRevision = Revision::newFromId( $iRevId );
		if ( $oRevision === null ) {
			return null;
		}
		return $oRevision->getTitle();
	}
}
