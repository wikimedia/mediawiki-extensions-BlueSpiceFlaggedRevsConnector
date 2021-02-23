<?php

class FRCHistoryView {

	/**
	 * Show date of flagging in history view
	 * @param HistoryPager $pager
	 * @param object $row
	 * @param string $s
	 * @param array $classes
	 * @global Language $wgLang
	 * @return boolean
	 */
	public static function onPageHistoryLineEnding( $pager, &$row, &$s, &$classes ) {
		global $wgLang;
		$dbr = wfGetDB( DB_REPLICA );
		$frRow = $dbr->selectRow(
			'flaggedrevs',
			'*',
			array(
				'fr_rev_id' => $row->rev_id
			),
			__METHOD__
		);

		if( $frRow ) {
			$oUser = User::newFromId( $frRow->fr_user );
			$sUserLink = '';
			if( $oUser instanceof User ) {
				$sUserLink = '| '.Linker::link( $oUser->getUserPage(), $oUser->getName() );
			}
			$msg = wfMessage('bs-flaggedrevsconnector-history-row-fr-info')
				->params(
					$wgLang->timeanddate( $frRow->fr_timestamp, true ),
					$sUserLink,
					self::getComment( $frRow->fr_timestamp, $frRow->fr_page_id )
				)
				->plain();
			$s .= " [$msg]";
		}
		return true;
	}

	protected static function getComment( $timestamp, $pageId ) {
		$dbr = wfGetDB( DB_REPLICA );
		$logRow = $dbr->selectRow(
			[ 'logging' , 'comment' ],
			'comment_text',
			[
				'log_page' => $pageId,
				'log_timestamp' => $timestamp,
				'log_comment_id = comment_id'
			],
			__METHOD__
		);

		if ( $logRow && !empty( $logRow->comment_text ) ) {
			$separator = wfMessage( 'bs-flaggedrevsconnector-pipe-separator' )->plain();
			return "$separator {$logRow->comment_text}";
		}
		return '';
	}
}