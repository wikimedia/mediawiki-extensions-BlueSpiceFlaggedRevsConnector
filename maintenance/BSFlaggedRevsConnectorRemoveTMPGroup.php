<?php

$IP = dirname( dirname( dirname( __DIR__ ) ) );

require_once "$IP/maintenance/Maintenance.php";

class BSFlaggedRevsConnectorRemoveTMPGroup extends LoggedUpdateMaintenance {
	protected $tmpGroupName = 'TMPAutoReviewAndReviewGroup';

	/**
	 *
	 * @return bool
	 */
	protected function doDBUpdates() {
		$this->output(
			"{$this->getUpdateKey()} -> remove temporary group {$this->tmpGroupName} from users\n"
		);
		$res = $this->getDB( DB_REPLICA )->select(
			'user_groups',
			['ug_group'],
			['ug_group' => $this->tmpGroupName],
			__METHOD__
		);
		if ( $res->numRows() < 1 ) {
			$this->output( "  No temporary groups to remove\n" );
			return true;
		}
		$this->output( "  {$res->numRows()} entries => removing..." );
		$success = $this->getDB( DB_PRIMARY )->delete(
			'user_groups',
			['ug_group' => $this->tmpGroupName],
			__METHOD__
		);
		if ( !$success ) {
			$this->output( "FAILED!\n\n" );
			return false;
		}
		$this->output( "OK\n\n" );
		return true;
	}

	/**
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'bs_flaggedrevsconnector-removetmpgroup';
	}

}
