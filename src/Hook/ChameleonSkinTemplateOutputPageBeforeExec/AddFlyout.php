<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\ChameleonSkinTemplateOutputPageBeforeExec;

use BlueSpice\FlaggedRevsConnector\Panel\Flyout;
use BlueSpice\Hook\ChameleonSkinTemplateOutputPageBeforeExec;
use BlueSpice\SkinData;

class AddFlyout extends ChameleonSkinTemplateOutputPageBeforeExec {
	protected function doProcess() {
		global $wgFlaggedRevsNamespaces;

		$title = $this->skin->getSkin()->getTitle();

		$frc = $this->getServices()->getService( 'BSExtensionFactory' )
			->getExtension( 'BlueSpiceFlaggedRevsConnector' );
		$flagInfo = $frc->collectFlagInfo( $title );

		if ( !in_array( $title->getNamespace(), $wgFlaggedRevsNamespaces ) ) {
			return true;
		}

		if ( $flagInfo['state'] == 'notreviewable' ) {
			return true;
		}

		$this->mergeSkinDataArray(
			SkinData::PAGE_DOCUMENTS_PANEL,
			[
				'flaggedrevs' => [
					'position' => 60,
					'callback' => static function ( $sktemplate ) {
						return new Flyout( $sktemplate );
					}
				]
			]
		);
		return true;
	}
}
