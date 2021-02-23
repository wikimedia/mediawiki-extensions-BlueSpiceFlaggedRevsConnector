<?php

use MediaWiki\MediaWikiServices;

class FRCUEModulePDF {

	/**
	 * Rewrites the 'oldid' parameter to the last stable revision if needed.
	 * @param array $aParams
	 * @return boolean
	 */
	public function onBSUEModulePDFbeforeGetPage( &$aParams ) {
		global $wgRequest;

		//PW(04.12.2013): Ugly, but works so far
		//RV(04.02.2015): Still ugly, but works so far
		if( $wgRequest->getInt( 'stable', 1 ) === 0 ) {
			return true;
		}

		//We need to skip if the oldid is requested directly
		if( $wgRequest->getInt( 'oldid', -1 ) !== -1 ) {
			return true;
		}

		//If this function gets called from BookshelfUI export we may not
		//have a 'article-id'. But we always have a 'title'
		if( !isset( $aParams[ 'article-id' ] ) ) {
			$oTitle = Title::newFromText( $aParams[ 'title' ] );
			if( $oTitle instanceof Title ) {
				$aParams[ 'article-id' ] = $oTitle->getArticleID();
			}
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->selectRow(
			'flaggedpages',
			'fp_stable',
			[ 'fp_page_id' => $aParams[ 'article-id' ] ],
			__METHOD__
		);

		if ( !isset( $res->fp_stable ) || is_null( $res->fp_stable ) ) {
			return true;
		}

		$aParams[ 'oldid' ] = $res->fp_stable;

		wfDebugLog(
			'BS::FlaggedRevsConnector',
			__METHOD__.': Fetched old revision ' . $res->fp_stable . ' for page_id ' . $aParams[ 'article-id' ]
		);

		return true;
	}

	/**
	 * Adds stylings for PDF Export
	 * @param array $aTemplate
	 * @param array $aStyleBlocks
	 * @return boolean Always true to keep hook running
	 */
	public function onBSUEModulePDFBeforeAddingStyleBlocks( &$aTemplate, &$aStyleBlocks ) {
		$aStyleBlocks[ 'FlaggedRevsConnector' ] =
			file_get_contents( dirname( __DIR__ ) . '/resources/flaggedrevs-export.css' );
		return true;
	}

	/**
	 * Adds dates below headings
	 * @param Title $oTitle
	 * @param array $aPage
	 * @param array $aParams
	 * @param DOMXpath $oDOMXPath
	 * @global WebRequest $wgRequest
	 * @global Language $wgLang
	 * @return boolean Always true to keep hook running
	 */
	public function onBSUEModulePDFgetPage( $oTitle, &$aPage, &$aParams, $oDOMXPath ) {
		global $wgRequest, $wgLang;

		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		if ( !$config->get( 'FlaggedRevsConnectorUEModulePDFShowFRTag' ) ) {
			return true;
		}

		$oFlaggableWikiPage = FlaggableWikiPage::getTitleInstance( $oTitle );
		if ( !$oFlaggableWikiPage->isReviewable() ) {
			return true;
		}
		$iRevId = isset( $aParams['oldid'] ) && $aParams['oldid'] !== 0
			? $aParams['oldid']
			: $oTitle->getLatestRevID();

		$oFlaggedRevision = FlaggedRevision::newFromId( $iRevId );
		$oRevison = Revision::newFromId( $iRevId );

		if( $oRevison instanceof Revision === false ) {
			return true;
		}

		$aDates = array(
			'laststabledate' => '',
			'stablerevisiondate' => ''
		);

		$aDates['stablerevisiondate'] = $wgLang->sprintfDate(
			'd.m.Y - H:i',
			$wgLang->userAdjust( $oRevison->getTimestamp() )
		);

		//Is the requested revision id a flagged revision?
		if( $oFlaggedRevision instanceof FlaggedRevision ) { // No...
			$aDates['laststabledate'] = $wgLang->sprintfDate(
				'd.m.Y - H:i',
				$wgLang->userAdjust( $oFlaggedRevision->getTimestamp() )
			);
		}

		$aPage['meta']['laststabledate']     = $aDates[ 'laststabledate' ];
		$aPage['meta']['stablerevisiondate'] = $aDates[ 'stablerevisiondate' ];

		$oStableTag = $aPage[ 'dom' ]->createElement(
			'span',
			wfMessage( 'bs-flaggedrevsconnector-addstabledatetochapterheadlinesmodifier-laststable-tag-text' )->plain() . ': '
		);
		$oStableTag->setAttribute( 'class', 'bs-flaggedrevshistorypage-laststable-tag' );

		if ( empty( $aDates[ 'laststabledate' ] ) ) {
			$sDateNode = $aPage[ 'dom' ]->createElement(
				'span',
				wfMessage( 'bs-flaggedrevsconnector-addstabledatetochapterheadlinesmodifier-no-stable-date' )->plain()
			);
			$sDateNode->setAttribute( 'class', 'nostable' );
		}
		else {
			$sDateNode = $aPage[ 'dom' ]->createTextNode( $aDates[ 'laststabledate' ] );
		}

		$oStableTag->appendChild( $sDateNode );
		$oStableRevDateTag = $aPage[ 'dom' ]->createElement(
			'span',
			' / ' . wfMessage( 'bs-flaggedrevsconnector-addstabledatetochapterheadlinesmodifier-stablerevisiondate-tag-text' )->plain() .
			': ' . $aDates[ 'stablerevisiondate' ]
		);
		$oStableRevDateTag->setAttribute( 'class', 'bs-flaggedrevshistorypage-stablerevisiondate-tag' );

		$aPage[ 'firstheading-element' ]->parentNode->insertBefore(
			$oStableRevDateTag, $aPage[ 'firstheading-element' ]->nextSibling
		);

		$aPage[ 'firstheading-element' ]->parentNode->insertBefore(
			$oStableTag, $aPage[ 'firstheading-element' ]->nextSibling
		);

		return true;
	}

	public static function onArticleViewHeader( &$article, &$outputDone, &$pcache ) {
		if ( !class_exists( 'FlaggablePageView', true ) ) {
			return true;
		}

		//Clear FlaggablePageView instance,
		//for correct subpage export
		FlaggablePageView::singleton()->clear();

		return true;
	}
}