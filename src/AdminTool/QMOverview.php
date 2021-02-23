<?php

namespace BlueSpice\FlaggedRevsConnector\AdminTool;

use BlueSpice\IAdminTool;

class QMOverview implements IAdminTool {

	public function getURL() {
		$tool = \SpecialPage::getTitleFor( 'QMOverview' );
		return $tool->getLocalURL();
	}

	public function getDescription() {
		return wfMessage( 'bs-qmoverview-desc' );
	}

	public function getName() {
		return wfMessage( 'bs-flaggedrevsconnector-admin-tool-overview' );
	}

	public function getClasses() {
		return [
			'bs-icon-checkmark-circle'
		];
	}

	public function getDataAttributes() {
		return [];
	}

	public function getPermissions() {
		return [
			"review"
		];
	}

}