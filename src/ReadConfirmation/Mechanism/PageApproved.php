<?php

namespace BlueSpice\FlaggedRevsConnector\ReadConfirmation\Mechanism;

use BlueSpice\NotificationManager;
use BlueSpice\PageAssignments\AssignmentFactory;
use BlueSpice\ReadConfirmation\IMechanism;
use BlueSpice\ReadConfirmation\Notifications\DailyRemind;
use BlueSpice\ReadConfirmation\Notifications\Remind;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\RevisionLookup;
use Psr\Log\LoggerInterface;
use Title;
use User;
use Wikimedia\Rdbms\LoadBalancer;

/**
 * Logic overview
 *
 * D = major draft, d = minor draft, S = major stable, s = minor stable, +r = read confirmed
 *
 * S+r -> d -> s => no read confirmation required
 * S+r -> D -> s => read confirmation required
 * S [-> d/D] -> s => read confirmation required
 * S+r [-> d/D] -> S => read confirmation required
 *
 * Class PageApproved
 * @package BlueSpice\FlaggedRevsConnector\ReadConfirmation\Mechanism
 */
class PageApproved implements IMechanism {

	/**
	 * @var LoadBalancer
	 */
	private $dbLoadBalancer;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var int|null
	 */
	private $revisionId = null;

	/**
	 * @var int
	 */
	private $reminderDelay = 0;

	/**
	 * @var RevisionLookup
	 */
	private $revisionLookup = null;

	/**
	 * @var array
	 */
	private $enabledNamespaces = [];

	/**
	 * @var array
	 */
	private $recentMustReadRevisions = [];

	/**
	 * @var AssignmentFactory
	 */
	protected $assignmentFactory = null;

	/**
	 * @var NotificationManager
	 */
	protected $notificationsManager = null;

	/**
	 * @return PageApproved
	 */
	public static function factory() {
		global $wgNamespacesWithEnabledReadConfirmation;
		$reminderDelay = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'bsg' )->get( 'FlaggedRevsConnectorPageApprovedReminderDelay' );
		$dbLoadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$logger = LoggerFactory::getInstance( 'BlueSpiceFlaggedRevsConnector' );
		$assignmentFactory = MediaWikiServices::getInstance()->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		$notificationsManager = MediaWikiServices::getInstance()->getService(
			'BSNotificationManager'
		);
		return new self(
			$dbLoadBalancer,
			$reminderDelay,
			$revisionLookup,
			$wgNamespacesWithEnabledReadConfirmation,
			$logger,
			$assignmentFactory,
			$notificationsManager
		);
	}

	/**
	 * PageApproved constructor.
	 * @param LoadBalancer $dbLoadBalancer
	 * @param int $reminderDelay
	 * @param RevisionLookup $revisionLookup
	 * @param array $enabledNamespaces
	 * @param LoggerInterface $logger
	 * @param AssignmentFactory $assignmentFactory
	 * @param NotificationManager $notificationsManager
	 */
	protected function __construct( $dbLoadBalancer, $reminderDelay, $revisionLookup,
		$enabledNamespaces, $logger, $assignmentFactory, $notificationsManager ) {
		$this->dbLoadBalancer = $dbLoadBalancer;
		$this->reminderDelay = $reminderDelay;
		$this->revisionLookup = $revisionLookup;
		$this->enabledNamespaces = $enabledNamespaces;
		$this->logger = $logger;
		$this->assignmentFactory = $assignmentFactory;
		$this->notificationsManager = $notificationsManager;
	}

	/**
	 * @return void
	 */
	public function wireUpNotificationTrigger() {
	}

	/**
	 * @param Title $title
	 * @param User $userAgent
	 * @return bool
	 */
	private function notifyDaily( Title $title, User $userAgent ) {
		if ( !$title->exists() ) {
			return false;
		}
		if ( $this->isMinorRevision( $title->getArticleID() )
			&& $this->hasNoPreviousMajorRevisionDrafts( $title->getArticleID() ) ) {
			return false;
		}
		$notifyUsers = $this->getNotifyUsers( $title->getArticleID() );
		$notification = new DailyRemind( $userAgent, $title, [], $notifyUsers );
		$this->notificationsManager->getNotifier()->notify( $notification );
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $userAgent
	 * @return bool
	 */
	public function notify( Title $title, User $userAgent ) {
		if ( !$title->exists() ) {
			return false;
		}
		if ( $this->isMinorRevision( $title->getArticleID() )
			&& $this->hasNoPreviousMajorRevisionDrafts( $title->getArticleID() ) ) {
			return false;
		}
		$notifyUsers = $this->getNotifyUsers( $title->getArticleID() );
		$notification = new Remind( $userAgent, $title, [], $notifyUsers );
		$this->notificationsManager->getNotifier()->notify( $notification );
		return true;
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
			return;
		}
		$userAgent = MediaWikiServices::getInstance()->getService( 'BSUtilityFactory' )
			->getMaintenanceUser()->getUser();

		foreach ( $res as $row ) {
			$title = Title::newFromID( $row->rev_pid );
			if ( !$title ) {
				continue;
			}
			$this->notifyDaily( $title, $userAgent );
		}
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function canConfirm( Title $title, User $user, $revId = null ) {
		if ( !$title instanceof Title ) {
			return false;
		}

		if ( !$revId ) {
			return false;
		}

		if ( !$this->isRevisionStable( $revId ) ) {
			return false;
		}

		if ( $this->isMinorRevision( $revId ) ) {
			if ( $this->hasNoPreviousMajorRevisionDrafts( $revId ) ) {
				return false;
			}
			$this->logger->debug(
				'Requested rev_id = {revId} is minor',
				[
					'revId' => $revId
				]
			);
			$revId = $this->getRecentMustReadRevision( $title->getArticleID() );
		}

		if ( !in_array( $user->getId(), $this->getAssignedUsers( $title->getArticleID() ) ) ) {
			return false;
		}

		$arrayWithThisUsersIdIfAlreadyReadTheRevision =
			$this->usersAlreadyReadRevision( $revId, [ $user->getId() ] );
		if ( !empty( $arrayWithThisUsersIdIfAlreadyReadTheRevision ) ) {
			return false;
		}

		$this->revisionId = $revId;

		return true;
	}

	/**
	 *
	 * @param int $revId
	 * @return bool
	 */
	private function isMinorRevision( $revId ) {
		$revision = $this->revisionLookup->getRevisionById( $revId );
		if ( $revision instanceof RevisionRecord ) {
			return $revision->isMinor();
		}
		return false;
	}

	/**
	 *
	 * @param int $revId
	 * @return bool
	 */
	private function hasNoPreviousMajorRevisionDrafts( $revId ) {
		$revision = $this->revisionLookup->getRevisionById( $revId );
		if ( $revision instanceof RevisionRecord ) {
			$previousRevision = $this->revisionLookup->getPreviousRevision( $revision );
			while ( $previousRevision instanceof RevisionRecord ) {
				if ( !$previousRevision->isMinor() ) {
					return false;
				}
				$previousRevision = $this->revisionLookup->getPreviousRevision( $previousRevision );
			}
		}
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function confirm( Title $title, User $user, $revId = null ) {
		if ( !$this->canConfirm( $title, $user, $revId ) ) {
			return false;
		}

		$this->logger->debug(
			'Read confirmation, requested rev_id = {revId}, final rev_id = {fRevId}, user_id = {userId}',
			[
				'revId' => $revId,
				'fRevId' => $this->revisionId,
				'userId' => $user->getId()
			]
		);

		$row = [
			'rc_rev_id' => $this->revisionId,
			'rc_user_id' => $user->getId(),
			'rc_timestamp' => wfTimestampNow()
		];

		$this->dbLoadBalancer->getConnection( DB_MASTER )->upsert(
			'bs_readconfirmation',
			$row,
			[ [ 'rc_rev_id', 'rc_user_id' ] ],
			$row
		);

		return true;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public function mustRead( Title $title ) {
		if ( !$title instanceof Title ) {
			return false;
		}

		if ( !isset( $this->enabledNamespaces[$title->getNamespace()] ) ||
			!$this->enabledNamespaces[$title->getNamespace()] ) {
			return false;
		}

		if ( !$this->getRecentMustReadRevision( $title->getArticleID() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int $pageId
	 * @return bool|int
	 */
	protected function getRecentMustReadRevision( $pageId ) {
		if ( !isset( $this->recentMustReadRevisions[$pageId] ) ) {
			$this->recentMustReadRevisions[$pageId] = false;
			$mustReadRevision = $this->getMustReadRevisions( [ $pageId ] );
			if ( isset( $mustReadRevision[$pageId] ) ) {
				$this->recentMustReadRevisions[$pageId] = $mustReadRevision[$pageId];
			}
		}
		return $this->recentMustReadRevisions[$pageId];
	}

	/**
	 * @param int $pageId
	 * @return array
	 */
	private function getNotifyUsers( $pageId ) {
		$affectedUsers = $this->getAssignedUsers( $pageId );
		if ( count( $affectedUsers ) < 1 ) {
			return [];
		}
		$revId = $this->getRecentMustReadRevision( $pageId );
		if ( !$revId ) {
			return [];
		}
		return array_diff(
			$affectedUsers,
			$this->usersAlreadyReadRevision( $revId, $affectedUsers )
		);
	}

	/**
	 * @param int $pageId
	 * @return array
	 */
	private function getAssignedUsers( $pageId ) {
		$title = Title::newFromID( $pageId );
		if ( !$title || !$title->exists() ) {
			return [];
		}
		$target = $this->assignmentFactory->newFromTargetTitle( $title );
		if ( !$target ) {
			return [];
		}
		return $target->getAssignedUserIDs();
	}

	/**
	 * @param int $revId
	 * @return bool
	 */
	private function isRevisionStable( $revId ) {
		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			'flaggedpages',
			[ 'fp_page_id' ],
			[
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

	/**
	 * Gets THE LATEST read revisions of each page for each user specified
	 *
	 * @param array $userIds List of user IDs
	 * @return array Array with such structure:
	 *  [
	 *    <user_id1> => [
	 *		 <page_id1> => <latest_read_revision1>,
	 * 		 <page_id2> => <latest_read_revision2>
	 *	  ],
	 * 	  <user_id2> => [
	 * 	  ...
	 * 	  ],
	 * 	  ...
	 * 	]
	 */
	private function getUserLatestReadRevisions( array $userIds ): array {
		$conds = [];
		if ( $userIds ) {
			$conds = [
				'rc_user_id' => $userIds
			];
		}

		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			[
				'revision',
				'bs_readconfirmation'
			],
			[
				'latest_rev' => 'MAX(rc_rev_id)',
				'rev_page',
				'rc_user_id'
			],
			$conds,
			__METHOD__,
			[
				'GROUP BY' => [
					'rev_page',
					'rc_user_id'
				]
			],
			[
				'bs_readconfirmation' => [
					'INNER JOIN', 'rc_rev_id = rev_id'
				]
			]
		);

		$latestRevs = [];
		foreach ( $res as $row ) {
			$latestRevs[ (int)$row->rc_user_id ][ (int)$row->rev_page ] = (int)$row->latest_rev;
		}

		return $latestRevs;
	}

	/**
	 * @inheritDoc
	 */
	public function getLatestReadConfirmations( array $userIds = [] ): array {
		$userLatestReadRevisions = $this->getUserLatestReadRevisions( $userIds );

		$pageIds = [];
		foreach ( $userLatestReadRevisions as $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				$pageIds[$pageId] = true;
			}
		}
		$pageIds = array_keys( $pageIds );

		$recentRevisions = $this->getMustReadRevisions( $pageIds );

		$readConfirmations = [];
		foreach ( $userLatestReadRevisions as $userId => $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				// In case if there are no major revisions of the page
				$recentRevisionId = 0;

				// There is some major revision of the page
				if ( isset( $recentRevisions[ $pageId ] ) ) {
					$recentRevisionId = $recentRevisions[ $pageId ];
				}

				$readConfirmations[$userId][$pageId] = [
					'latest_rev' => $recentRevisionId,
					'latest_read_rev' => $latestReadRevisionId
				];
			}
		}

		return $readConfirmations;
	}

	/**
	 * @param array $userIds
	 * @param array $pageIds
	 * @return array [ <page_id> => [ <user_id1>, <user_id2>, ...], ... ]
	 */
	public function getCurrentReadConfirmations( array $userIds = [], array $pageIds = [] ) {
		$currentReadConfirmations = [];
		$userReadRevisions = $this->getUserReadRevisions( $userIds );
		$recentRevisions = $this->getMustReadRevisions( $pageIds );
		foreach ( $pageIds as $pageId ) {
			$reads = [];
			if (
				isset( $recentRevisions[$pageId] ) &&
				isset( $userReadRevisions[$recentRevisions[$pageId]] )
			) {
				$reads = $userReadRevisions[$recentRevisions[$pageId]];
			}
			$currentReadConfirmations[$pageId] = $reads;
		}

		return $currentReadConfirmations;
	}

	/**
	 * @param int $revisionId
	 * @param int $userIds
	 * @return array
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

		return [];
	}

	/**
	 * @param array $userIds
	 * @return array
	 */
	private function getUserReadRevisions( $userIds = [] ) {
		$conds = [];
		if ( !empty( $userIds ) ) {
			$conds['rc_user_id'] = $userIds;
		}
		$res = $this->dbLoadBalancer
			->getConnection( DB_REPLICA )
			->select(
				'bs_readconfirmation',
				'*',
				$conds,
				__METHOD__
			);

		$readRevisions = [];
		foreach ( $res as $row ) {
			$revId = (int)$row->rc_rev_id;
			if ( !isset( $readRevisions[ $revId ] ) ) {
				$readRevisions[ $revId ] = [];
			}
			$readRevisions[ $revId ][(int)$row->rc_user_id] = $row->rc_timestamp;
		}

		return $readRevisions;
	}

	/**
	 * @param array $pageIds
	 * @return array
	 */
	private function getMustReadRevisions( array $pageIds = [] ) {
		$recentData = [];

		$conds = [];

		if ( !empty( $pageIds ) ) {
			$conds['rev_page'] = $pageIds;
		}

		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			[ 'revision', 'flaggedpages' ],
			[ 'rev_id', 'rev_page', 'rev_minor_edit', 'fp_stable' ],
			$conds,
			__METHOD__,
			[ 'ORDER BY' => 'rev_id DESC' ],
			[
				'flaggedpages' => [ 'LEFT JOIN', 'rev_page = fp_page_id' ]
			]
		);

		foreach ( $res as $row ) {
			if ( isset( $recentData[$row->rev_page] ) ) {
				continue;
			}
			if ( $row->rev_id <= $row->fp_stable && (int)$row->rev_minor_edit === 0 ) {
				$recentData[$row->rev_page] = (int)$row->rev_id;
			}
		}

		return $recentData;
	}

}
