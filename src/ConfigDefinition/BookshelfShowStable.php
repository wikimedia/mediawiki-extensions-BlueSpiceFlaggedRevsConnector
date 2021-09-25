<?php

namespace BlueSpice\FlaggedRevsConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;

class BookshelfShowStable extends BooleanSetting {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_EXPORT . '/BlueSpiceFlaggedRevsConnector',
			static::MAIN_PATH_EXTENSION . '/BlueSpiceFlaggedRevsConnector/' . static::FEATURE_EXPORT,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/BlueSpiceFlaggedRevsConnector',
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-flaggedrevsconnector-pref-bookshelfshowstable';
	}

	/**
	 *
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'bs-flaggedrevsconnector-pref-bookshelfshowstable-help';
	}
}
