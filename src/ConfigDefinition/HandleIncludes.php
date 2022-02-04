<?php

namespace BlueSpice\FlaggedRevsConnector\ConfigDefinition;

use BlueSpice\ConfigDefinition\ArraySetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use HTMLSelectField;
use MediaWiki\MediaWikiServices;

class HandleIncludes extends ArraySetting implements IOverwriteGlobal {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		$ext = MediaWikiServices::getInstance()->getService( 'BSExtensionFactory' )
			->getExtension( 'BlueSpiceFlaggedRevsConnector' )->getName();
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_QUALITY_ASSURANCE . "/$ext",
			static::MAIN_PATH_EXTENSION . "/$ext/" . static::FEATURE_QUALITY_ASSURANCE,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . "/$ext",
		];
	}

	/**
	 *
	 * @return HTMLFormField
	 */
	public function getHtmlFormField() {
		return new HTMLSelectField( $this->makeFormFieldParams() );
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'bs-flaggedrevsconnector-pref-handleincludes';
	}

	/**
	 *
	 * @return array
	 */
	protected function getOptions() {
		$options = [
			// FR_INCLUDES_CURRENT
			$this->msg( 'bs-flaggedrevsconnector-pref-handleinclude-current' )->plain() => 0,
			// FR_INCLUDES_STABLE
			$this->msg( 'bs-flaggedrevsconnector-pref-handleinclude-stable' )->plain() => 2,
		];
		if ( $this->getValue() === 1 ) {
			// @deprecated since version 4.0.2 - not in use anymore. Keep this option
			// for users, that have already set it up this way to be able to save configs
			// without changing values
			wfDebugLog( 'bluespice-deprecations', __METHOD__, 'private' );
			// FR_INCLUDES_FREEZE
			$options[$this->msg( 'bs-flaggedrevsconnector-pref-handleinclude-freeze' )->plain()] = 1;
		}
		return $options;
	}

	/**
	 *
	 * @return string
	 */
	public function getGlobalName() {
		return "wgFlaggedRevsHandleIncludes";
	}

	/**
	 *
	 * @return int
	 */
	public function getValue() {
		return (int)parent::getValue();
	}

	/**
	 *
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'bs-flaggedrevsconnector-pref-handleincludes-help';
	}

}
