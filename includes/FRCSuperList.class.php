<?php

use MediaWiki\MediaWikiServices;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCSuperList {

	/**
	 *
	 * @param array &$aFields
	 * @return bool
	 */
	public function onSuperListGetFieldDefinitions( &$aFields ) {
		$aFields[] = [
			'name' => 'is_flaggedrevs_enabled',
			'type' => 'boolean',
		];
		$aFields[] = [
			'name' => 'flaggedrevs_state',
			'type' => 'boolean',
		];
		$aFields[] = [
			'name' => 'flaggedrevs_date',
			// 'type' => 'date',
		];
		$aFields[] = [
			'name' => 'flaggedrevs_is_new_available',
			'type' => 'boolean',
		];
		return true;
	}

	/**
	 *
	 * @param array &$aColumns
	 * @return bool
	 */
	public function onSuperListGetColumnDefinitions( &$aColumns ) {
		$aColumns[] = [
			'header' => wfMessage( 'bs-flaggedrevsconnector-sl-flaggedrevs-state' )->plain(),
			'dataIndex' => 'flaggedrevs_state',
			'render' => 'boolean',
			'filter' => [
				'type' => 'boolean'
			],
		];
		$aColumns[] = [
			'header' => wfMessage( 'bs-flaggedrevsconnector-sl-flaggedrevs-date' )->plain(),
			'dataIndex' => 'flaggedrevs_date',
			'render' => 'date',
			'hidden' => true,
			'filter' => [
				'type' => 'date',
				'dataFormat' => 'Y-m-d',
			],
		];
		$aColumns[] = [
			'header' => wfMessage( 'bs-flaggedrevsconnector-sl-flaggedrevs-is-new-available' )->plain(),
			'dataIndex' => 'flaggedrevs_is_new_available',
			'render' => 'boolean',
			'filter' => [
				'type' => 'boolean'
			],
		];
		return true;
	}

	/**
	 *
	 * @param array $aFilters
	 * @param array &$aTables
	 * @param array &$aFields
	 * @param array &$aConditions
	 * @param array &$aJoinConditions
	 * @return bool
	 */
	public function onSuperListQueryPagesWithFilter( $aFilters, &$aTables, &$aFields, &$aConditions,
		&$aJoinConditions ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sTablePrefix = $dbr->tablePrefix();

		$aTables[] = "{$sTablePrefix}flaggedpages AS fp";
		$aJoinConditions["{$sTablePrefix}flaggedpages AS fp"] = [
			'LEFT OUTER JOIN', "{$sTablePrefix}page.page_id=fp.fp_page_id"
		];
		$aTables[] = "{$sTablePrefix}flaggedrevs AS fr";
		$aJoinConditions["{$sTablePrefix}flaggedrevs AS fr"] = [
			'LEFT OUTER JOIN', "fp.fp_stable=fr.fr_rev_id"
		];
		$aFields[] = "IF(ISNULL(fp.fp_stable), 0, 1) AS flaggedrevs_state";
		$aFields[] = "fr.fr_timestamp AS flaggedrevs_date";
		$aFields[] = "fp.fp_stable<>{$sTablePrefix}page.page_latest AS flaggedrevs_is_new_available";

		if ( array_key_exists( 'flaggedrevs_state', $aFilters ) ) {
			if ( !$aFilters['flaggedrevs_state'][0]['value'] ) {
				$aFilters['flaggedrevs_state'][0]['value'] = 0;
			} else {
				$aFilters['flaggedrevs_state'][0]['value'] = 1;
			}
			$aConditions[] = "IF(ISNULL(fp.fp_stable), 0, 1) = "
				. intval( $aFilters['flaggedrevs_state'][0]['value'] );
		}
		if ( array_key_exists( 'flaggedrevs_date', $aFilters ) ) {
			SuperList::filterDateTable(
				'DATE(fr.fr_timestamp)',
				$aFilters['flaggedrevs_date'],
				$aConditions
			);
		}
		if ( array_key_exists( 'flaggedrevs_is_new_available', $aFilters ) ) {
			if ( !$aFilters['flaggedrevs_is_new_available'][0]['value'] ) {
				$aFilters['flaggedrevs_is_new_available'][0]['value'] = 0;
			} else {
				$aFilters['flaggedrevs_is_new_available'][0]['value'] = 1;
			}

			$aConditions[] = "(fp.fp_stable<=>{$sTablePrefix}page.page_latest) != "
				. $aFilters['flaggedrevs_is_new_available'][0]['value'];
		}

		return true;
	}

	/**
	 *
	 * @param array &$aRows
	 * @return bool
	 */
	public function onSuperListBuildDataSets( &$aRows ) {
		if ( !count( $aRows ) ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$flaggedRevsNamespaces = $services->getMainConfig()->get( 'FlaggedRevsNamespaces' );

		$aPageIds = array_keys( $aRows );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$aTables = [
			'page', 'flaggedpages', 'flaggedrevs'
		];
		$aJoinConditions = [
			'flaggedpages' => [ 'LEFT OUTER JOIN', 'page_id=fp_page_id' ],
			'flaggedrevs' => [ 'LEFT OUTER JOIN', 'fp_stable=fr_rev_id' ]
		];
		$sField = "page_id, page_namespace, page_latest, fp_stable, fr_timestamp";
		$sCondition = "page_id IN (" . implode( ',', $aPageIds ) . ")";
		$aOptions = [
			'ORDER BY' => 'page_id'
		];

		$res = $dbr->select( $aTables, $sField, $sCondition, __METHOD__,
			$aOptions, $aJoinConditions );

		foreach ( $res as $row ) {
			$bHasStableRevision = $row->fp_stable != '';
			$aRows[$row->page_id]['flaggedrevs_state'] = $bHasStableRevision;
			$aRows[$row->page_id]['flaggedrevs_date'] = $bHasStableRevision
				? wfTimestamp( TS_MW, $row->fr_timestamp )
				: '';
			$aRows[$row->page_id]['flaggedrevs_is_new_available']
				= $row->page_latest != $row->fp_stable;
			if ( in_array( $row->page_namespace, $flaggedRevsNamespaces ) ) {
				$aRows[$row->page_id]['is_flaggedrevs_enabled'] = true;
			} else {
				$aRows[$row->page_id]['is_flaggedrevs_enabled'] = false;
			}
		}

		return true;
	}
}
