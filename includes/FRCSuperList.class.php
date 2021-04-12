<?php

class FRCSuperList {

	public function onSuperListGetFieldDefinitions(&$aFields) {
		$aFields[] = array(
			'name' => 'flaggedrevs_state',
			'type' => 'boolean',
		);
		$aFields[] = array(
			'name' => 'flaggedrevs_date',
			//'type' => 'date',
		);
		$aFields[] = array(
			'name' => 'flaggedrevs_is_new_available',
			'type' => 'boolean',
		);
		return true;
	}

	public function onSuperListGetColumnDefinitions(&$aColumns) {
		$aColumns[] = array(
			'header' => wfMessage('bs-flaggedrevsconnector-sl-flaggedrevs-state')->plain(), 
			'dataIndex' => 'flaggedrevs_state', 
			'render' => 'boolean',
			'filter' => array(
				'type' => 'boolean'
			),
		);
		$aColumns[] = array(
			'header' => wfMessage('bs-flaggedrevsconnector-sl-flaggedrevs-date')->plain(), 
			'dataIndex' => 'flaggedrevs_date', 
			'render' => 'date', 
			'hidden' => true,
			'filter' => array(
				'type' => 'date',
				'dataFormat' => 'Y-m-d',
			),
		);
		$aColumns[] = array(
			'header' => wfMessage('bs-flaggedrevsconnector-sl-flaggedrevs-is-new-available')->plain(), 
			'dataIndex' => 'flaggedrevs_is_new_available', 
			'render' => 'boolean', 
			'filter' => array(
				'type' => 'boolean'
			),
		);
		return true;
	}

	public function onSuperListQueryPagesWithFilter($aFilters, &$aTables, &$aFields, &$aConditions, &$aJoinConditions) {
		$dbr = wfGetDB( DB_REPLICA );
		$sTablePrefix = $dbr->tablePrefix();

		$aTables[] = "{$sTablePrefix}flaggedpages AS fp";
		$aJoinConditions["{$sTablePrefix}flaggedpages AS fp"] = array(
			'LEFT OUTER JOIN', "{$sTablePrefix}page.page_id=fp.fp_page_id"
		);
		$aTables[] = "{$sTablePrefix}flaggedrevs AS fr";
		$aJoinConditions["{$sTablePrefix}flaggedrevs AS fr"] = array(
			'LEFT OUTER JOIN', "fp.fp_stable=fr.fr_rev_id"
		);
		$aFields[] = "IF(ISNULL(fp.fp_stable), 0, 1) AS flaggedrevs_state";
		$aFields[] = "fr.fr_timestamp AS flaggedrevs_date";
		$aFields[] = "fp.fp_stable<>{$sTablePrefix}page.page_latest AS flaggedrevs_is_new_available";

		if (array_key_exists('flaggedrevs_state', $aFilters)) {
			if(!$aFilters['flaggedrevs_state'][0]['value'])
				$aFilters['flaggedrevs_state'][0]['value'] = 0;
			else $aFilters['flaggedrevs_state'][0]['value'] = 1;
			$aConditions[] = "IF(ISNULL(fp.fp_stable), 0, 1) = " . intval($aFilters['flaggedrevs_state'][0]['value']);
		}
		if (array_key_exists('flaggedrevs_date', $aFilters)) {
			SuperList::filterDateTable('DATE(fr.fr_timestamp)', $aFilters['flaggedrevs_date'], $aConditions);
		}
		if (array_key_exists('flaggedrevs_is_new_available', $aFilters)) {
			if(!$aFilters['flaggedrevs_is_new_available'][0]['value'])
				$aFilters['flaggedrevs_is_new_available'][0]['value'] = 0;
			else $aFilters['flaggedrevs_is_new_available'][0]['value'] = 1;

			$aConditions[] = "(fp.fp_stable<=>{$sTablePrefix}page.page_latest) != " . $aFilters['flaggedrevs_is_new_available'][0]['value'];
		}

		return true;
	}

	public function onSuperListBuildDataSets(&$aRows) {
		if (!count($aRows)) {
			return true;
		}

		$aPageIds = array_keys($aRows);

		$dbr = wfGetDB( DB_REPLICA );
		$aTables = array(
			'page', 'flaggedpages', 'flaggedrevs'
		);
		$aJoinConditions = array(
			'flaggedpages' => array('LEFT OUTER JOIN', 'page_id=fp_page_id'),
			'flaggedrevs' => array('LEFT OUTER JOIN', 'fp_stable=fr_rev_id')
		);
		$sField = "page_id, page_latest, fp_stable, fr_timestamp";
		$sCondition = "page_id IN (" . implode(',', $aPageIds) . ")";
		$aOptions = array(
			'ORDER BY' => 'page_id'
		);

		$res = $dbr->select( $aTables, $sField, $sCondition, __METHOD__, 
			$aOptions, $aJoinConditions );

		while ($row = $res->fetchObject()) {
			$bHasStableRevision = $row->fp_stable != '';
			$aRows[$row->page_id]['flaggedrevs_state'] = $bHasStableRevision;
			$aRows[$row->page_id]['flaggedrevs_date'] = $bHasStableRevision 
				? wfTimestamp( TS_MW, $row->fr_timestamp )
				: '';
			$aRows[$row->page_id]['flaggedrevs_is_new_available'] 
				= $row->page_latest != $row->fp_stable;
		}

		return true;
	}
}
