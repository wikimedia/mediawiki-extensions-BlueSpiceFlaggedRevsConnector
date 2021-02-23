<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;
use MediaWiki\MediaWikiServices;

class HideFileDraftResources extends BeforePageDisplay {

	protected function skipProcessing() {
		if ( $this->out->getTitle()->getNamespace() !== NS_FILE ) {
			return true;
		}
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
		if ( !in_array( NS_FILE, $mainConfig->get( 'FlaggedRevsNamespaces' ) ) ) {
			return true;
		}

		return false;
	}

	protected function doProcess() {
		$this->out->addModuleStyles( 'ext.bluespice.flaggedRevsConnector.filepage.styles' );
	}

}
