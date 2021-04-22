<?php

namespace BlueSpice\FlaggedRevsConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition\ArraySetting;

class DraftGroups extends ArraySetting {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_QUALITY_ASSURANCE
				. '/BlueSpiceFlaggedRevsConnector',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceFlaggedRevsConnector/'
				. static::FEATURE_QUALITY_ASSURANCE,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceFlaggedRevsConnector',
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-flaggedrevsconnector-pref-draftgroups';
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		global $wgGroupPermissions;
		$excludeGroups = [
			'bot',
			'autoconfirmed',
			'checkuser',
			'sysop',
			'reviewer'
		];
		$options = [];
		foreach ( $wgGroupPermissions as $group => $permissions ) {
			if ( in_array( $group, $excludeGroups ) ) {
				continue;
			}
			$options[] = $group;
		}
		return $options;
	}
}
