<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BlueSpiceEchoConnectorUserLocatorValidUsers;

use BlueSpice\EchoConnector\Hook\BlueSpiceEchoConnectorUserLocatorValidUsers;
use DateTime;
use DateTimeZone;
use Title;

class LockdownNotifications extends BlueSpiceEchoConnectorUserLocatorValidUsers {
	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		if ( !$this->title ) {
			return true;
		}
		if ( !in_array( $this->title->getNamespace(), $this->getNamespaceWhitelist() ) ) {
			return true;
		}
		if ( !$this->isDraft( $this->title ) ) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	private function isDraft( Title $title ) {
		$revision = $this->getServices()->getRevisionLookup()->getRevisionByTitle( $title );
		if ( !$revision ) {
			// whenever there is a deletion, there is no revision anymore
			return false;
		}
		$res = $this->getServices()->getDBLoadBalancer()->getConnection( DB_REPLICA )->selectField(
			'flaggedrevs',
			'fr_rev_timestamp',
			[
				'fr_page_id' => $title->getArticleId(),
				// Show all with some degree of stability
				'fr_quality > 0'
			],
			__METHOD__
		);
		if ( !$res ) {
			return true;
		}
		$flagDate = DateTime::createFromFormat(
			'YmdHis',
			$res,
			new DateTimeZone( 'UTC' )
		);
		$reqTimestamp = DateTime::createFromFormat(
			'YmdHis',
			$revision->getTimestamp(),
			new DateTimeZone( 'UTC' )
		);
		return $reqTimestamp > $flagDate;
	}

	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		foreach ( $this->users as $key => $user ) {
			$groupInters = array_intersect(
				$this->getGroupWhitelist(),
				$user->getEffectiveGroups( true )
			);
			if ( count( $groupInters ) > 0 ) {
				continue;
			}
			unset( $this->users[$key] );
		}
		return false;
	}

	/**
	 *
	 * @return int[]
	 */
	private function getNamespaceWhitelist() {
		return $this->getConfig()->has( 'FlaggedRevsNamespaces' )
			? $this->getConfig()->get( 'FlaggedRevsNamespaces' )
			: $GLOBALS['wgFlaggedRevsNamespaces'];
	}

	/**
	 *
	 * @return string[]
	 */
	private function getGroupWhitelist() {
		$allowedGroups = array_merge(
			$this->getConfig()->get( 'FlaggedRevsConnectorDraftGroups' ),
			// always allowed because of reasons...
			[ 'sysop', 'reviewer' ]
		);
		return array_unique( $allowedGroups );
	}
}
