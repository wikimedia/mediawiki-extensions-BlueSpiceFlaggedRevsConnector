<?php

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCSemanticMediaWiki {

	/**
	 * Update Semantic MediaWiki data when a revision got flagged by a user
	 *
	 * @param ApiReview|mixed $module
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
		if ( $oTitle === null ) {
			return true;
		}

		$oWikiPage = WikiPage::factory( $oTitle );
		if ( $oWikiPage->getContent() != null ) {
			DataUpdate::runUpdates( $oWikiPage->getContent()->getSecondaryDataUpdates( $oTitle ) );
		}
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
