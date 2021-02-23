<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\LoadExtensionSchemaUpdates;

use BlueSpice\Hook\LoadExtensionSchemaUpdates;

class AddRemoveTMPGroupMaintenanceScript extends LoadExtensionSchemaUpdates {
	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$this->updater->addPostDatabaseUpdateMaintenance(
			'BSFlaggedRevsConnectorRemoveTMPGroup'
		);
		return true;
	}

}
