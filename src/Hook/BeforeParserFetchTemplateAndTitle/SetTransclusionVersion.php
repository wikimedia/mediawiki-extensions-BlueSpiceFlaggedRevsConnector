<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\BeforeParserFetchTemplateAndTitle;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\Hook\BeforeParserFetchTemplateAndTitle;
use FlaggedRevision;
use FlaggedRevs;
use Title;

class SetTransclusionVersion extends BeforeParserFetchTemplateAndTitle {

	/**
	 * Decide on which template revision to transclude
	 *
	 * @return bool
	 */
	protected function doProcess() {
		if ( FlaggedRevs::inclusionSetting() !== FR_INCLUDES_FREEZE ) {
			return true;
		}

		$title = $this->parser->getTitle();
		if ( !$title instanceof Title ) {
			return true;
		}
		$revid = $this->parser->getRevisionId();
		if ( $revid === null ) {
			return true;
		}
		$helper = new Utils( $this->getConfig() );
		if ( !$helper->isFlaggableNamespace( $title ) ) {
			return true;
		}
		if ( $revid === $title->getLatestRevID() ) {
			return true;
		}

		$fr = FlaggedRevision::newFromId( $revid );
		if ( $fr === null ) {
			return true;
		}
		$stableTemplates = $fr->getTemplateVersions();

		foreach ( $stableTemplates as $ns => $template ) {
			if ( $this->title->getNamespace() !== $ns ) {
				continue;
			}
			foreach ( $template as $dbKey => $revision ) {
				if ( $dbKey === $this->title->getDBkey() ) {
					$this->id = $revision;
					return true;
				}
			}
		}

		return true;
	}
}
