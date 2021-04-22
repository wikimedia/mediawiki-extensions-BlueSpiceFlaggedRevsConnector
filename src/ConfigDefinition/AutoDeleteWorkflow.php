<?php

namespace BlueSpice\FlaggedRevsConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;

class AutoDeleteWorkflow extends BooleanSetting {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_QUALITY_ASSURANCE . '/BlueSpiceFlaggedRevsConnector',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceFlaggedRevsConnector/' . static::FEATURE_QUALITY_ASSURANCE,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceFlaggedRevsConnector',
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-flaggedrevsconnector-pref-autodeleteworkflow';
	}
}
