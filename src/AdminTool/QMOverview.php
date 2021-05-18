<?php

namespace BlueSpice\FlaggedRevsConnector\AdminTool;

use BlueSpice\IAdminTool;

class QMOverview implements IAdminTool {

	/**
	 *
	 * @return string
	 */
	public function getURL() {
		$tool = \SpecialPage::getTitleFor( 'QMOverview' );
		return $tool->getLocalURL();
	}

	/**
	 *
	 * @return \Message
	 */
	public function getDescription() {
		return wfMessage( 'bs-qmoverview-desc' );
	}

	/**
	 *
	 * @return \Message
	 */
	public function getName() {
		return wfMessage( 'bs-flaggedrevsconnector-admin-tool-overview' );
	}

	/**
	 *
	 * @return string[]
	 */
	public function getClasses() {
		return [
			'bs-icon-checkmark-circle'
		];
	}

	/**
	 *
	 * @return array
	 */
	public function getDataAttributes() {
		return [];
	}

	/**
	 *
	 * @return string[]
	 */
	public function getPermissions() {
		return [
			"review"
		];
	}

}
