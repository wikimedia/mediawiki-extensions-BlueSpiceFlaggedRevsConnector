<?php

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCInfobox {

	protected $iFREntryDate = 0;
	protected $bFRInfoboxFirstElement = true;
	protected $iFRInfoboxElementCounter = 0;

	// TODO: user choose displaytype date/text

	/**
	 * Hook-Handler for Hook 'BSInfoBoxBeforeEntryViewAddData'
	 * @param array &$aData
	 * @param array $args
	 * @param \ViewBaseElement $oInfoBoxListEntryView
	 * @param \stdClass|null $row
	 * @return bool Always true to keep hook running
	 */
	public function onBSInfoBoxBeforeEntryViewAddData( &$aData, $args, $oInfoBoxListEntryView,
		$row = null ) {
		if ( $args['mode'] != 'flaggedrevisions' ) {
			return true;
		}
		global $wgLang;

		$sPrefixedText = $aData['PREFIXEDTITLE'];
		$iPageId = Title::newFromText( $sPrefixedText )->getArticleId();

		$dbr         = wfGetDB( DB_REPLICA );
		$sOrderSQL   = 'rc_timestamp DESC';
		$aConditions = [ 'rc_cur_id = ' . $iPageId ];
		$res = $dbr->selectRow(
			[ 'recentchanges' ],
			[ 'rc_comment' ],
			$aConditions,
			__METHOD__,
			[ 'ORDER BY' => $sOrderSQL, 'LIMIT' => 1 ]
		);

		$sComment = '';
		if ( !empty( $res->rc_comment ) ) {
			$sComment = $res->rc_comment;
		}
		if ( strlen( $sComment ) > 68 ) {
			$sComment = substr( $sComment, 0, 68 );
			$sComment = substr( $sComment, 0, strrpos( $sComment, ' ' ) ) . '...';
		}

		$aFields = [ 'fr_timestamp' ];

		$sOrderSQL   = 'fr_timestamp DESC';
		$aConditions = [ 'fr_page_id = ' . $iPageId ];
		$res = $dbr->selectRow(
			[ 'flaggedrevs' ],
			$aFields,
			$aConditions,
			__METHOD__,
			[ 'ORDER BY' => $sOrderSQL, 'LIMIT' => 1 ]
		);

		$sStableDate = false;
		$sUlPrefix = '';
		$sUlSuffix = '';

		$this->iFRInfoboxElementCounter ++;
		$sULLastPrefix = $args['count'] == $this->iFRInfoboxElementCounter ? '</ul>' : '';

		if ( substr( $res->fr_timestamp, 0, 8 ) != $this->iFREntryDate ) {
			$sUlSuffix = '</ul>';
			if ( $this->bFRInfoboxFirstElement == true ) {
				$sUlSuffix = '';
				$this->bFRInfoboxFirstElement = false;
			}
			$sUlPrefix = '<ul>';
			$this->iFREntryDate = substr( $res->fr_timestamp, 0, 8 );
			$sStableDate        = $res->fr_timestamp;
		}
		$aData['TEXT'] = '<br /><i><nowiki>' . $sComment . '</nowiki></i>';
		$aData['DATE'] = $sStableDate
			? '<b>' . $wgLang->sprintfDate( 'l, d. F Y', $sStableDate ) . '</b><br />'
			: '';

		$oInfoBoxListEntryView->setTemplate( $sUlSuffix . '{DATE}' . $sUlPrefix
			. '<li>[[:{PREFIXEDTITLE}|{DISPLAYTITLE}]]{TEXT}</li>' . $sULLastPrefix );
		return true;
	}

	/**
	 * Adds the mode "flaggedrevisions" to the Infobox extension
	 * @param array &$aObjectList
	 * @param array $args
	 * @return bool Always true to keep hook running
	 */
	public function onBSInfoBoxCustomMode( &$aObjectList, $args ) {
		if ( $args['mode'] != 'flaggedrevisions' ) {
			return true;
		}
		$args['showpending'] = BsCore::sanitizeArrayEntry(
			$args,
			'showpending',
			true,
			BsPARAMTYPE::BOOL
		);
		$args['namespaces'] = BsCore::sanitizeArrayEntry(
			$args,
			'namespaces',
			'all',
			BsPARAMTYPE::SQL_STRING
		);

		$dbr         = wfGetDB( DB_REPLICA );
		$sOrderSQL   = 'max(fr_timestamp) DESC';
		$aConditions = [];

		$oErrorListView = new ViewTagErrorList( $this );

		try {
			$aNamespaceIds = BsNamespaceHelper::getNamespaceIdsFromAmbiguousCSVString( $args['namespaces'] );
			$aConditions[] = '(SELECT page_namespace FROM ' . $dbr->tableName( 'page' )
				. ' WHERE page_id = fr_page_id) IN (' . implode( ',', $aNamespaceIds ) . ')';
		}
		catch ( BsInvalidNamespaceException $ex ) {
			$sInvalidNamespaces = implode( ', ', $ex->getListOfInvalidNamespaces() );
			$oErrorListView->addItem( new ViewTagError(
				wfMessage(
					'bs-flaggedrevsconnector-invalid-namespaces',
					count( $ex->getListOfInvalidNamespaces() ),
					$sInvalidNamespaces
				)->parse()
			) );
			return $oErrorListView->execute();
		}

		$aTables = [ 'flaggedrevs' ];

		$aFields = [ '(SELECT page_title FROM ' . $dbr->tableName( 'page' )
			. ' WHERE page_id = fr_page_id) as title',
			'(SELECT page_namespace FROM ' . $dbr->tableName( 'page' )
				. ' WHERE page_id = fr_page_id) as namespace'
		];
		$aOptions = [ 'GROUP BY' => 'title,namespace',
			'ORDER BY' => $sOrderSQL, 'LIMIT' => $args[ 'count' ]
		];

		$sPendingCondition = ( !$args[ 'showpending' ] ) || $args[ 'showpending' ] != true
			? ' where fp_pending_since IS NULL '
			: '';
		$aConditions[ ] = 'fr_page_id IN ( SELECT fp_page_id FROM '
			. $dbr->tableName( 'flaggedpages' ) . $sPendingCondition . ')';

		$res = $dbr->select(
				$aTables,
				$aFields,
				$aConditions,
				__METHOD__,
				$aOptions
		);
		foreach ( $res as $row ) {
			$aObjectList[ ] = $row;
		}

		return true;
	}
}
