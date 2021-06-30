<?php

use MediaWiki\MediaWikiServices;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCBookshelf {

	// TODO: Implement T****T way
	/**
	 * Adds the FlaggedRevs history page to the book
	 * @param array &$aTemplate
	 * @param Title &$aBookPage
	 * @param array &$aArticles
	 * @return bool Always true to keep hook running
	 */
	public function onBSBookshelfExportBeforeArticles( &$aTemplate, &$aBookPage, &$aArticles ) {
		global $wgLang, $wgFlaggedRevsNamespaces;
		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' );

		$bShowStable = $config->get(
			'FlaggedRevsConnectorBookshelfShowStable'
		);
		$bShowNoStable = $config->get(
			'FlaggedRevsConnectorBookshelfShowNoStable'
		);
		$bShowNoFR = $config->get( 'FlaggedRevsConnectorBookshelfShowNoFR' );

		if ( !$bShowStable && !$bShowNoStable && !$bShowNoFR ) {
			// In this case we do not need to do anything
			return true;
		}

		// Let's add the "FlaggedRevs History page"
		$oFlaggedRevsHistoryPage = $aTemplate[ 'dom' ]->createElement( 'div' );
		$oFlaggedRevsHistoryPage->setAttribute(
			'class',
			'bs-section bs-custompage bs-flaggedrevisionhistorypage bs-flaggedrevshistorypage'
		);
		$aTemplate[ 'content-elements' ][ 'content' ]->parentNode->insertBefore(
			$oFlaggedRevsHistoryPage, $aTemplate[ 'content-elements' ][ 'content' ]
		);

		// Now let's see what articles we've got.
		$aArticleIds = [];
		// ids
		$aStables = [];
		// ids
		$aNoStables = [];
		// article arrays
		$aNoFalggedRevs = [];

		// Let's get all page_ids from the collections array and build a the
		// IN clause for the SQL statement.
		foreach ( $aArticles as $aArticle ) {
			$sArticleTitle = $aArticle[ 'display-title' ];

			$oTitle = Title::newFromText( $aArticle[ 'title' ] );
			if ( $oTitle instanceof Title == false ) {
				wfDebugLog(
					'BS::FlaggedRevsConnector',
					'onBSBookshelfExportBeforeArticles: Not a valid article: '
					. var_export( $aArticle, true )
				);
				continue;
			}

			// If the articles namespace is not registered with FlaggedRevs, we skip it
			if ( !in_array( $oTitle->getNamespace(), $wgFlaggedRevsNamespaces ) ) {
				$aNoFalggedRevs[] = $aArticle;
				continue;
			}

			// Otherwise we take it into account
			$iArticleId = $oTitle->getArticleID();
			$aArticleIds[ $sArticleTitle ] = $iArticleId;

			// Not all IDs that return _no_ row in the log-qery below are
			// "no stables". Maybe there is just no log entry. Or the review is
			// past the threshold date
			if ( FlaggableWikiPage::getTitleInstance( $oTitle )->getStable() === 0 ) {
				$aNoStables[] = $iArticleId;
			}
		}

		// Now, fetch data from DB
		if ( empty( $aArticleIds ) ) {
			wfDebugLog(
				'BS::FlaggedRevsConnector',
				'onBSBookshelfExportBeforeArticles: No articles provided.'
			);
		} else {
			$dbr = wfGetDB( DB_REPLICA );

			// We only want the last 2 years comments
			$sDateLimit = '\'' . $dbr->timestamp( time() - 2 * 365 * 24 * 60 * 60 ) . '\'';

			// Get the data
			$res = $dbr->select(
				'logging',
				[ 'log_timestamp', 'log_comment', 'log_page' ],
				[
					'log_type' => 'review',
					'log_page' => $aArticleIds,
					'log_timestamp >= ' . $sDateLimit,
					'log_action ' . $dbr->buildLike( 'approve', $dbr->anyString() )
				],
				__METHOD__,
				[ 'ORDER BY' => 'log_timestamp DESC' ]
			);

			$aStableRows = [];
			foreach ( $res as $oRow ) {
				// This is a stable page
				$aStables[] = $oRow->log_page;
				$aStableRows[] = $oRow;
			}
		}

		// Now, after fetching all neccessary data we build the tables
		$oStableDiv   = null;
		$oNoStableDiv = null;
		$oNoFRDiv     = null;

		\Hooks::run(
			'BSFlaggedRecsConnectorBookshelfBeforeHistoryPage',
			[
				&$aTemplate, &$aBookPage, &$aArticles,
				&$bShowStable, &$bShowNoStable, &$bShowNoFR,
				&$aStables, &$aNoStables, &$aNoFalggedRevs
			]
		);
		\Hooks::run(
			'BSFRCBookshelfBeforeHistoryPage',
			[
				&$aTemplate, &$aBookPage, &$aArticles,
				&$bShowStable, &$bShowNoStable, &$bShowNoFR,
				&$aStables, &$aNoStables, &$aNoFalggedRevs
			]
		);

		if ( $bShowStable && !empty( $aStables ) ) {
			$oStableDiv = $aTemplate[ 'dom' ]->createElement( 'div' );
			$oStableDiv->setAttribute( 'class', 'bs-flaggedrevshistorypage-recent-changes' );
			$oStableDiv->appendChild( $aTemplate[ 'dom' ]->createElement(
				'h2',
				wfMessage( 'bs-flaggedrevsconnector-flaggedrevshistorypage-stableversionstabletitle' )->plain()
			) );

			$oStableVersionsTable = $aTemplate[ 'dom' ]->createElement( 'table' );
			$oStableVersionsTable->setAttribute( 'width', '100%' );
			$oStableVersionsTable->setAttribute(
				'class',
				'bs-flaggedrevshistorypage-stables-list'
			);
			$oStableDiv->appendChild( $oStableVersionsTable );

			$oTHead = $oStableVersionsTable->appendChild(
				$aTemplate[ 'dom' ]->createElement( 'thead' )
			);
			$oTBody = $oStableVersionsTable->appendChild(
				$aTemplate[ 'dom' ]->createElement( 'tbody' )
			);
			$oTHRow = $oTHead->appendChild( $aTemplate[ 'dom' ]->createElement( 'tr' ) );
			$oTHRow->appendChild( $aTemplate[ 'dom' ]->createElement(
				'th',
				wfMessage( 'bs-flaggedrevsconnector-flaggedrevshistorypage-stabledate' )->plain()
			) );
			$oTHRow->appendChild( $aTemplate[ 'dom' ]->createElement(
				'th',
				wfMessage( 'bs-flaggedrevsconnector-flaggedrevshistorypage-title' )->plain()
			) );
			$oTHRow->appendChild( $aTemplate[ 'dom' ]->createElement(
				'th',
				wfMessage( 'bs-flaggedrevsconnector-flaggedrevshistorypage-comment' )->plain()
			) );

			$sCSSClass = 'odd';
			foreach ( $aStableRows as $oRow ) {
				$sRevDate = wfTimestamp( TS_MW, $oRow->log_timestamp );
				$sRevDate = $wgLang->userAdjust( $sRevDate );
				$sRevDate = $wgLang->sprintfDate( 'd.m.Y', $sRevDate );

				$sTitle = array_search( $oRow->log_page, $aArticleIds );

				$oTRow = $aTemplate[ 'dom' ]->createElement( 'tr' );
				$oTRow->appendChild( $aTemplate[ 'dom' ]->createElement( 'td', $sRevDate ) );
				$oTRow->appendChild( $aTemplate[ 'dom' ]->createElement( 'td', $sTitle ) );
				$oTRow->appendChild( $aTemplate[ 'dom' ]->createElement( 'td', $oRow->log_comment ) );
				$oTRow->setAttribute( 'class', $sCSSClass );

				$oTBody->appendChild( $oTRow );
				$sCSSClass = ( $sCSSClass == 'odd' ) ? 'even' : 'odd';
			}
		}

		if ( $bShowNoStable && !empty( $aNoStables ) ) {
			$oNoStableDiv = $aTemplate[ 'dom' ]->createElement( 'div' );
			$oNoStableDiv->setAttribute( 'class', 'bs-flaggedrevshistorypage-no-stables' );
			$oNoStableDiv->appendChild( $aTemplate[ 'dom' ]->createElement(
				'h2',
				wfMessage(
					'bs-flaggedrevsconnector-flaggedrevshistorypage-nostableversionstabletitle'
				)->plain()
			) );

			$oNoStablesTable = $aTemplate[ 'dom' ]->createElement( 'table' );
			$oNoStablesTable->setAttribute( 'width', '100%' );
			$oNoStablesTable->setAttribute( 'class', 'bs-flaggedrevshistorypage-no-stables-list' );
			$oNoStableDiv->appendChild( $oNoStablesTable );

			$sCSSClass = 'odd';
			foreach ( $aNoStables as $iKey => $iArticleId ) {
				$sArticleTitle = array_search( $iArticleId, $aArticleIds );
				$oTRow = $aTemplate[ 'dom' ]->createElement( 'tr' );
				$oTRow->appendChild( $aTemplate[ 'dom' ]->createElement( 'td', $sArticleTitle ) );
				$oTRow->setAttribute( 'class', $sCSSClass );

				$oNoStablesTable->appendChild( $oTRow );
				$sCSSClass = ( $sCSSClass == 'odd' ) ? 'even' : 'odd';
			}
		}

		if ( $bShowNoFR && !empty( $aNoFalggedRevs ) ) {
			$oNoFRDiv = $aTemplate[ 'dom' ]->createElement( 'div' );
			$oNoFRDiv->setAttribute( 'class', 'bs-flaggedrevshistorypage-no-flaggedrevs' );
			$oNoFRDiv->appendChild( $aTemplate[ 'dom' ]->createElement(
				'h2',
				wfMessage( 'bs-flaggedrevsconnector-flaggedrevshistorypage-noflaggedrevstitle' )->plain()
			) );

			$oNoFRTable = $aTemplate[ 'dom' ]->createElement( 'table' );
			$oNoFRTable->setAttribute( 'width', '100%' );
			$oNoFRTable->setAttribute( 'class', 'bs-flaggedrevshistorypage-no-flaggedrevs-list' );
			$oNoFRDiv->appendChild( $oNoFRTable );

			$sCSSClass = 'odd';
			foreach ( $aNoFalggedRevs as $aArticle ) {
				$oTRow = $aTemplate[ 'dom' ]->createElement( 'tr' );
				$oTRow->appendChild( $aTemplate[ 'dom' ]->createElement( 'td', $aArticle['display-title'] ) );
				$oTRow->setAttribute( 'class', $sCSSClass );

				$oNoFRTable->appendChild( $oTRow );
				$sCSSClass = ( $sCSSClass == 'odd' ) ? 'even' : 'odd';
			}
		}

		// Finally we add the tables to the page
		if ( $oStableDiv != null ) { $oFlaggedRevsHistoryPage->appendChild( $oStableDiv );
		}
		if ( $oNoStableDiv != null ) { $oFlaggedRevsHistoryPage->appendChild( $oNoStableDiv );
		}
		if ( $oNoFRDiv != null ) { $oFlaggedRevsHistoryPage->appendChild( $oNoFRDiv );
		}

		return true;
	}
}
