<?php

use MediaWiki\MediaWikiServices;

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
				$sUserLink = '| ' . MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
					$oUser->getUserPage(),
					new HtmlArmor( $oUser->getName() )
				);
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
			'logging',
			'log_comment',
			[
				'log_page' => $pageId,
				'log_timestamp' => $timestamp
			],
			__METHOD__
		);

		if ( $logRow && !empty( $logRow->log_comment ) ) {
			$separator = wfMessage( 'bs-flaggedrevsconnector-pipe-separator' )->plain();
			return "$separator {$logRow->log_comment}";
		}
		return '';
	}
}