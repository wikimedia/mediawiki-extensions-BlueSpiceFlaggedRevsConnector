<?php
$IP = dirname( dirname( dirname( __DIR__ ) ) );
require_once "$IP/maintenance/Maintenance.php";

use BlueSpice\Services;

class ApiMigrateApprovedRevs extends LoggedUpdateMaintenance {

	/**
	 *
	 * @var DerivativeContext
	 */
	protected $context = null;

	/**
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * @var array
	 */
	protected $data = [];

	public function __construct() {
		parent::__construct();

		$this->addOption(
			'preferOriginalActor',
			'If valid user is set as original actor, use that account',
			false,
			false
		);
		$this->addOption( 'actor', 'User to execute the action as', false );
	}

	protected function readData() {
		$res = Services::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_REPLICA )
			->select(
				[
					'ar' => 'approved_revs',
					'r' => 'revision'
				],
				'*',
				[],
				__METHOD__,
				[],
				[
					'r' => [
						'LEFT JOIN',
						[ 'ar.rev_id = r.rev_id' ]
					]
				]
			);
		if ( $res->numRows() < 1 ) {
			return true;
		}

		foreach ( $res as $row ) {
			$this->data[$row->page_id] = $row;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function doDBUpdates() {
		$this->output( "... approved_revs -> flaggedpages migration ...\n" );
		$this->readData();

		if ( count( $this->data ) < 1 ) {
			$this->output( "Nothing to migrate\n" );
			return true;
		}

		$this->output( count( $this->data ) . " approved_revs records \n" );

		$this->context = new \DerivativeContext( \RequestContext::getMain() );
		foreach ( $this->data as $pageId => $approvedRev ) {
			$this->setActor( (int)$approvedRev->approver_id );
			$this->flagStable( $approvedRev->rev_id, $pageId );
		}

		if ( !empty( $this->errors ) ) {
			$this->output( "\nERRORS:\n" );
			foreach ( $this->errors as $error ) {
				$this->output( "* $error\n" );
			}
		}

		return true;
	}

	/**
	 * @param int $revId
	 * @param int $pageId
	 */
	protected function flagStable( $revId, $pageId ) {
		$this->context->setRequest(
			$this->makeDerivativeRequest( $revId )
		);
		$api = new ApiMain( $this->context, true );
		try {
			$api->execute();
			$data = $api->getResult()->getResultData();
			if ( isset( $data['review']['result'] )
				&& $data['review']['result'] === 'Success' ) {
				$this->output( "Approved page with ID $pageId" . PHP_EOL );
			} else {
				throw new ApiUsageException( null, Status::newFatal( 'Api error' ) );
			}
		} catch ( ApiUsageException $exception ) {
			$title = Title::newFromID( $pageId );
			$this->output(
				"Approval failed for page " . $title->getPrefixedText() .
				". Reason: " . $exception->getMessage() . PHP_EOL
			);
		}
	}

	/**
	 * @param int $revId
	 * @return DerivativeRequest
	 */
	protected function makeDerivativeRequest( $revId ) {
		return new DerivativeRequest(
			$this->context->getRequest(),
			[
				'action' => 'review',
				'revid' => $revId,
				'flag_accuracy' => 1,
				'comment' => "Autoreviewd by " . __CLASS__,
				'token' => $this->context->getUser()->getEditToken()
			]
		);
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'bs_approved_revs_to_frc_api_migration';
	}

	/**
	 *
	 * @param int $id
	 */
	private function setActor( $id = 0 ) {
		$actor = $this->getOption( 'actor' );
		$user = null;
		if ( $id > 0 ) {
			if ( !(bool)$this->getOption( 'preferOriginalActor' ) ) {
				$user = User::newFromName( $actor );
			} else {
				$user = User::newFromId( $id );
			}
		} elseif ( $actor ) {
			$user = User::newFromName( $actor );
		}

		if ( !$user || $user->getId() === 0 ) {
			$this->fatalError( 'No valid user for revision approval' );
		} else {
			$GLOBALS['wgUser'] = $user;
			$this->context->setUser( $user );
		}
	}
}

$maintClass = "ApiMigrateApprovedRevs";
require_once RUN_MAINTENANCE_IF_MAIN;
