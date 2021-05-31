<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\ImagePageFileHistoryLine;

use BlueSpice\Hook\ImagePageFileHistoryLine;
use DateTime;
use DateTimeZone;
use File;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserGroupManager;

class HideDraftVersions extends ImagePageFileHistoryLine {
	/**
	 * @inheritDoc
	 */
	protected function skipProcessing() {
		return !( !$this->canSeeDraft() && $this->isDraft( $this->file ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcess() {
		$this->rowClass .= ' frc-invisible';

		return true;
	}

	/**
	 * Get groups that are allowed to see drafts
	 *
	 * @return string[]
	 */
	private function getAllowedGroups() {
		$allowedGroups = array_merge(
			$this->getConfig()->get( 'FlaggedRevsConnectorDraftGroups' ),
			// always allowed because of reasons...
			[ 'sysop', 'reviewer' ]
		);
		return array_unique( $allowedGroups );
	}

	/**
	 *
	 * @param File $file
	 * @return bool
	 */
	protected function isDraft( File $file ) {
		$db = $this->getServices()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $db->selectField(
			'flaggedrevs',
			'fr_img_timestamp',
			[
				'fr_page_id' => $file->getTitle()->getArticleId(),
				// Show all with some degree of stability
				'fr_quality > 0'
			],
			__METHOD__
		);
		if ( !$res ) {
			// could be first draft, but we do not lock down first draft!
			false;
		}
		$flagDate = DateTime::createFromFormat(
			'YmdHis',
			$res,
			new DateTimeZone( 'UTC' )
		);
		$reqTimestamp = DateTime::createFromFormat(
			'YmdHis',
			$file->getTimestamp(),
			new DateTimeZone( 'UTC' )
		);
		return $reqTimestamp > $flagDate;
	}

	/**
	 * @return bool
	 */
	private function canSeeDraft() {
		return !empty(
			array_intersect(
				$this->getAllowedGroups(),
				MediaWikiServices::getInstance()
					->getUserGroupManager()
					->getEffectiveGroups(
						$this->getContext()->getUser(),
						UserGroupManager::READ_NORMAL,
						true
					)
			)
		);
	}
}
