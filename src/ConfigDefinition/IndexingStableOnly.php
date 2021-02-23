<?php

namespace BlueSpice\FlaggedRevsConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;

class IndexingStableOnly extends BooleanSetting {

	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SEARCH . '/BlueSpiceFlaggedRevsConnector',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceFlaggedRevsConnector/' . static::FEATURE_SEARCH,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceFlaggedRevsConnector',
		];
	}

	public function getLabelMessageKey() {
		return 'bs-flaggedrevsconnector-pref-indexingStableOnly';
	}
}
