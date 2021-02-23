<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BeforePageDisplay;

class AddResources extends \BlueSpice\Hook\BeforePageDisplay {

	protected function skipProcessing() {
		global $wgFlaggedRevsNamespaces;

		if( !in_array( $this->out->getTitle()->getNamespace(), $wgFlaggedRevsNamespaces ) ) {
			return true;
		}

		if( !$this->out->isArticleRelated() ) {
			return true;
		}
		return false;
	}

	protected function doProcess() {
		$this->out->addModuleStyles( 'ext.bluespice.flaggedRevsConnector.styles' );
		$this->out->addModules( 'ext.bluespice.flaggedRevsConnector.js' );
	}

}
