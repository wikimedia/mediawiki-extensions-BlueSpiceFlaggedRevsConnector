<?php

use MediaWiki\MediaWikiServices;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FRCHistoryView {

	/**
	 * Show date of flagging in history view
	 * @param HistoryPager $pager
	 * @param stdClass &$row
	 * @param string &$s
	 * @param array &$classes
	 * @return bool
	 */
	public static function onPageHistoryLineEnding( $pager, &$row, &$s, &$classes ) {
		global $wgLang;
		$dbr = wfGetDB( DB_REPLICA );
		$frRow = $dbr->selectRow(
			'flaggedrevs',
			'*',
			[
				'fr_rev_id' => $row->rev_id
			],
			__METHOD__
		);

		if ( $frRow ) {
			$services = MediaWikiServices::getInstance();
			$oUser = $services->getUserFactory()->newFromId( $frRow->fr_user );
			$sUserLink = '';
			if ( $oUser instanceof User ) {
				$sUserLink = '| ' . $services->getLinkRenderer()->makeLink(
					$oUser->getUserPage(),
					new HtmlArmor( $oUser->getName() )
				);
			}
			$msg = wfMessage( 'bs-flaggedrevsconnector-history-row-fr-info' )
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

	/**
	 *
	 * @param string $timestamp
	 * @param int $pageId
	 * @return string
	 */
	protected static function getComment( $timestamp, $pageId ) {
		$dbr = wfGetDB( DB_REPLICA );
		$logRow = $dbr->selectRow(
			[ 'logging', 'comment' ],
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
