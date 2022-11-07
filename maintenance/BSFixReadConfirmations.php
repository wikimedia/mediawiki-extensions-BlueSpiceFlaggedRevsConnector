<?php

$IP = dirname( dirname( dirname( __DIR__ ) ) );
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\RevisionLookup;
use Wikimedia\Rdbms\LoadBalancer;

class BSFixReadConfirmations extends Maintenance {
	/**
	 * Revision lookup instance.
	 * Used to search prev/next revisions in history, revisions by ID etc.
	 *
	 * @var RevisionLookup
	 */
	private $revisionLookup;

	/**
	 * Load balancer instance.
	 * Used to interact with DB.
	 *
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	/**
	 * Adds "--dry" option to run script without actual changes in DB.
	 */
	public function __construct() {
		parent::__construct();
		$this->addOption( 'dry', 'Test run without doing actually DB fixes' );
	}

	/**
	 * Gets all revisions which are marked as read by some user.
	 *
	 * @return array Array with revisions, where key is revision ID and value is
	 * array, containing [userId => timestamp] key-values.
	 */
	private function getUserReadRevisions() {
		$res = $this->loadBalancer
			->getConnection( DB_REPLICA )
			->select(
				'bs_readconfirmation',
				'*',
				[],
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
	 * Checks whether specified revision is stable.
	 * It's considered as stable if is listed in "flaggedrevs" table and has "fr_quality > 0".
	 *
	 * @param int $revId Specified revision ID
	 * @return bool <tt>true</tt> if revision is stable, <tt>false</tt> otherwise.
	 */
	private function isRevisionStable( $revId ) {
		$res = $this->loadBalancer->getConnection( DB_REPLICA )->select(
			'flaggedrevs',
			[ 'fr_rev_id' ],
			[
				'fr_rev_id' => $revId,
				'fr_quality > 0'
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
	 * Gets specified revision and searches for nearest next MINOR STABLE revision in page revisions history.
	 *
	 * @param RevisionRecord $revision Specified revision's object.
	 * @return int|null Returns ID of necessary revision, or <tt>null</tt> if it was not found.
	 */
	private function searchNextMinorStable( $revision ) {
		while ( true ) {
			$nextRevision = $this->revisionLookup->getNextRevision( $revision );

			if ( $nextRevision !== null ) {
				$nextRevisionId = $nextRevision->getId();

				if ( $nextRevision->isMinor() && $this->isRevisionStable( $nextRevisionId ) ) {
					$this->output( 'MINOR STABLE found - ' . $nextRevisionId . "\n" );

					return $nextRevisionId;
				}
			}
 else {
				// End of revision history reached
				return null;
 }

			$revision = $nextRevision;
		}
	}

	/**
	 * Gets specified revision and searches for nearest previous MAJOR STABLE revision in page revisions history.
	 *
	 * @param RevisionRecord $revision Specified revision's object.
	 * @return int|null Returns ID of necessary revision, or <tt>null</tt> if it was not found.
	 */
	private function searchPrevMajorStable( $revision ) {
		while ( true ) {
			$prevRevision = $this->revisionLookup->getPreviousRevision( $revision );

			if ( $prevRevision !== null ) {
				$prevRevisionId = $prevRevision->getId();

				if ( !$prevRevision->isMinor() && $this->isRevisionStable( $prevRevisionId ) ) {
					$this->output( 'MAJOR STABLE found - ' . $prevRevisionId . "\n" );

					return $prevRevisionId;
				}
			}
 else {
				// End of revision history reached
				return null;
 }

			$revision = $prevRevision;
		}
	}

	/**
	 * Walks through all revisions, which have read confirmations.
	 *
	 * Deletes read confirmation entries for MINOR DRAFT revisions, if they are not based on MAJOR STABLE.
	 *
	 * In case of MAJOR DRAFT revisions, resets such read confirmation entries to nearest next MINOR STABLE.
	 * If there is not - deletes such entries.
	 *
	 * @return bool|void
	 */
	public function execute() {
		$this->loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$this->revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();

		// Collect all read confirmations
		$userReadRevisions = $this->getUserReadRevisions();

		foreach ( $userReadRevisions as $revId => $revisionReadConfirms ) {
			$revision = $this->revisionLookup->getRevisionById( $revId );

			$this->output( 'Current revision ID - ' . $revId . "\n" );

			if ( $revision === null ) {
				$this->error( "Could not find revision '$revId'!\n" );
				continue;
			}

			// If revision was MAJOR STABLE - do nothing
			if ( !$revision->isMinor() && $this->isRevisionStable( $revId ) ) {
				$this->output( 'Revision is MAJOR STABLE' . "\n" );
				$this->output( 'Nothing to do with it. Proceeding to the next one...' . "\n" );
				continue;
			}

			// Check if revision was MAJOR DRAFT
			if ( !$revision->isMinor() ) {
				// Look up for MINOR STABLE among newer revisions of specified page
				$this->output( 'Revision is MAJOR DRAFT' . "\n" );
				$this->output( 'Looking for MINOR STABLE among newer revisions...' . "\n" );

				$newerMinorStableRevision = $this->searchNextMinorStable( $revision );
				if ( $newerMinorStableRevision !== null ) {
					// MINOR STABLE is found among newer revisions. So reset read
					// confirmation entry to that MINOR STABLE revision
					$this->output( 'Reset read confirmation to specified MINOR STABLE revision' . "\n" );

					if ( !$this->hasOption( 'dry' ) ) {
						$this->loadBalancer->getConnection( DB_MASTER )->update(
							'bs_readconfirmation',
							[ 'rc_rev_id' => $newerMinorStableRevision ],
							[ 'rc_rev_id' => $revId ]
						);
					}
				} else {
					// MINOR STABLE was not found among newer revisions. In such case
					// we just delete specified read confirmation entry
					$this->output( 'Delete read confirmation entry for MAJOR DRAFT revision' . "\n" );

					if ( !$this->hasOption( 'dry' ) ) {
						$this->loadBalancer->getConnection( DB_MASTER )->delete(
							'bs_readconfirmation',
							[ 'rc_rev_id' => $revId ]
						);
					}
				}
			} else {
				// Otherwise revision was MINOR DRAFT
				$this->output( 'Revision is MINOR DRAFT' . "\n" );
				$this->output( 'Looking for MAJOR STABLE from later version (which current is based on)...' . "\n" );

				// Check if it was based on MAJOR STABLE
				$isBasedOnMajorStable = (bool)$this->searchPrevMajorStable( $revision );

				// If MAJOR STABLE was not found - delete MINOR DRAFT entry
				if ( !$isBasedOnMajorStable ) {
					$this->output( "Deleting of MINOR DRAFT entry...\n" );

					if ( !$this->hasOption( 'dry' ) ) {
						$this->loadBalancer->getConnection( DB_MASTER )->delete(
							'bs_readconfirmation',
							[ 'rc_rev_id' => $revId ]
						);
					}
				}
			}
		}

		return true;
	}
}

$maintClass = "BSFixReadConfirmations";
require_once RUN_MAINTENANCE_IF_MAIN;
