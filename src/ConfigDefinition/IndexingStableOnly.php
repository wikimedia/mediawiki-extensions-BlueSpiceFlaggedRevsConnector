<?php

namespace BlueSpice\FlaggedRevsConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;

class IndexingStableOnly extends BooleanSetting {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SEARCH . '/BlueSpiceFlaggedRevsConnector',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceFlaggedRevsConnector/' . static::FEATURE_SEARCH,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceFlaggedRevsConnector',
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-flaggedrevsconnector-pref-indexingstableonly';
	}
}
