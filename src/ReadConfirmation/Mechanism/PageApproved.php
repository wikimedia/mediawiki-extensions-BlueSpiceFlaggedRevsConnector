<?php

namespace BlueSpice\FlaggedRevsConnector\ReadConfirmation\Mechanism;

use BlueSpice\ReadConfirmation\Notifications\Remind;
use BlueSpice\ReadConfirmation\IMechanism;
use BlueSpice\NotificationManager;
use Hooks;
use MediaWiki\MediaWikiServices;
use Title;
use User;
use Wikimedia\Rdbms\LoadBalancer;

class PageApproved implements IMechanism {

	/**
	 * @var LoadBalancer
	 */
	private $dbLoadBalancer;

	/**
	 * @var int|null
	 */
	private $revisionId = null;

	/**
	 * @var int
	 */
	private $reminderDelay = 0;

	/**
	 * @return PageApproved
	 */
	public static function factory() {

		$reminderDelay = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' )->get( 'FlaggedRevsConnectorPageApprovedReminderDelay' );
		$dbLoadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		return new self(
			$dbLoadBalancer,
			$reminderDelay
		);
	}

	/**
	 * PageApproved constructor.
	 * @param LoadBalancer $dbLoadBalancer
	 * @param $reminderDelay
	 */
	protected function __construct( $dbLoadBalancer, $reminderDelay ) {
		$this->dbLoadBalancer = $dbLoadBalancer;
		$this->reminderDelay = $reminderDelay;
	}

	/**
	 * @return void
	 */
	public function wireUpNotificationTrigger() {

		Hooks::register(
			'FlaggedRevsRevisionReviewFormAfterDoSubmit',
			'BlueSpice\\FlaggedRevsConnector\\Hook\\FlaggedRevsRevisionReviewFormAfterDoSubmit\\SendReadConfirmationOnApprove::callback'
		);
	}

	/**
	 * @param User $userAgent
	 * @param Title $title
	 * @return array|bool
	 */
	public function notify( Title $title, User $userAgent ) {
		if ( !$title instanceof Title) {
			return false;
		}
		/** @var NotificationManager $notificationsManager */
		$notificationsManager = MediaWikiServices::getInstance()->getService( 'BSNotificationManager' );
		$notifier = $notificationsManager->getNotifier();
		$notifyUsers = $this->getNotifyUsers( $title->getArticleID() );
		$notification = new Remind( $userAgent, $title, [], $notifyUsers );
		$notifier->notify( $notification );

		$notifiedUsers = [];
		foreach ( $notifyUsers as $userId ) {
			$user = User::newFromId( $userId );
			if ( !$user ) {
				continue;
			}
			$notifiedUsers[] = $user;

		}

		return $notifiedUsers;
	}

	/**
	 * @return void
	 */
	public function autoNotify() {
		$reviewMaxEndDate = date( "Y-m-d", time() - $this->reminderDelay * 3600 );

		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			'flaggedrevs',
			[ 'fr_page_id' ],
			"fr_timestamp < '$reviewMaxEndDate'",
			__METHOD__,
			[
				'ORDER BY' => 'fr_rev_id DESC',
				'DISTINCT' => true
			]
		);

		if ( $res->numRows() > 0 ) {
			$userAgent = MediaWikiServices::getInstance()->getService( 'BSUtilityFactory' )->getMaintenanceUser()->getUser();;
			foreach ( $res as $row ) {
				$title = Title::newFromID( $row->rev_pid );
				$this->getAssignedUsers( $row->rev_pid );
				$this->notify( $title, $userAgent );
			}
		}
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param null $revId
	 * @return bool
	 */
	public function canConfirm( Title $title, User $user, $revId = null ) {
		if ( !$title instanceof Title ) {
			return false;
		}

		if ( !$revId ) {
			$revId = $this->getRecentRevision( $title->getArticleID() );
			if ( $revId === false) {
				return false;
			}
		}

		if ( !$this->isRevisionStable( $title->getArticleID(), $revId ) ) {
			return false;
		}

		if ( !in_array( $user->getId(), $this->getAssignedUsers( $title->getArticleID() ) ) ) {
			return false;
		}

		if ( is_array( $this->usersAlreadyReadRevision( $revId, [ $user->getId() ] ) ) ) {
			return false;
		}

		$this->revisionId = $revId;

		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param null $revId
	 * @return bool
	 */
	public function confirm( Title $title, User $user, $revId = null ) {
		if ( !$this->canConfirm( $title, $user, $revId ) ) {
			return false;
		}

		$row = [
			'rc_rev_id' => $this->revisionId,
			'rc_user_id' =>  $user->getId()
		];

		$this->dbLoadBalancer->getConnection( DB_MASTER )->delete( 'bs_readconfirmation', $row );
		$row['rc_timestamp'] = wfTimestampNow();
		$this->dbLoadBalancer->getConnection( DB_MASTER )->insert( 'bs_readconfirmation', $row );

		return true;
	}

	/**
	 * @param $pageId
	 * @return bool|int
	 */
	protected function getRecentRevision( $pageId ) {
		$revisionId = false;

		$conds = [
			'rev_page' => $pageId,
			'rev_minor_edit' => 0
		];

		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			'revision',
			[ 'rev_id', 'rev_page' ],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => 'rev_id DESC',
				'LIMIT' => 1
			]
		);
		if ( $res->numRows() > 0 ) {
			$revisionId = (int) $res->fetchRow()['rev_id'];
		}

		return $revisionId;
	}

	/**
	 * @param $revisionId
	 * @param $userIds
	 * @return bool|array
	 */
	private function usersAlreadyReadRevision( $revisionId, $userIds ) {
		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			'bs_readconfirmation',
			'*',
			[
				'rc_user_id' => $userIds,
				'rc_rev_id' => $revisionId
			],
			__METHOD__
		);
		if ( $res->numRows() > 0 ) {
			$userIds = [];
			foreach ( $res as $row ) {
				$userIds[] = $row->rc_user_id;
			}
			return $userIds;
		}

		return false;
	}

	/**
	 * @param $pageId
	 * @return array
	 */
	private function getNotifyUsers( $pageId ) {
		$affectedUsers = $this->getAssignedUsers( $pageId );
		if ( count( $affectedUsers ) > 0 ) {
			$revisionId = $this->getRecentRevision( $pageId );
			$usersAlreadyReadRevision = $this->usersAlreadyReadRevision( $revisionId, $affectedUsers );
			if ( is_array( $usersAlreadyReadRevision ) ) {
				$affectedUsers = array_diff( $affectedUsers, $usersAlreadyReadRevision );
			}
		}

		return $affectedUsers;
	}



	/**
	 * @param $pageId
	 * @return array
	 */
	private function getAssignedUsers( $pageId ) {
		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			'bs_pageassignments',
			[ 'pa_assignee_key', 'pa_assignee_type' ],
			[
				'pa_page_id' => $pageId,
			]
		);
		$userIds = [];
		if ( $res->numRows() > 0 ) {
			foreach ( $res as $row ) {
				switch ( $row->pa_assignee_type ) {
					case 'group':
						$userIds = array_merge( $userIds, $this->getUsersInGroup( $row->pa_assignee_key ) );
						break;
					case 'user':
						$userIds[] = ( User::newFromName( $row->pa_assignee_key ) )->getId();
						break;
				}
			}
		}

		return $userIds;
	}

	/**
	 * @param $group
	 * @return array
	 */
	private function getUsersInGroup( $group ) {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection(
			DB_REPLICA
		);
		$res = $db->select(
			[ 'user', 'user_groups' ],
			[ 'user_id', 'user_name' ],
			[
				'ug_group' => $group,
				'ug_user = user_id'
			],
			__METHOD__
		);

		$users = [];
		foreach ( $res as $row ) {
			$users[] = (int) $row->user_id;
		}

		return $users;
	}

	/**
	 * @param $pageId
	 * @param $revId
	 * @return bool
	 */
	private function isRevisionStable( $pageId, $revId ) {
		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			'flaggedpages',
			[ 'fp_page_id' ],
			[
				'fp_page_id' => $pageId,
				'fp_stable' => $revId
			],
			__METHOD__,
			[
				'LIMIT' => 1
			]
		);
		if ( $res->numRows() > 0 ) {
			return true;
		}
		return false;
	}

}