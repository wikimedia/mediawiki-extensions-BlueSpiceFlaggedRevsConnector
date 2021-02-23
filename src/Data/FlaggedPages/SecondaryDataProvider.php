<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use BlueSpice\FlaggedRevsConnector\Data\Record;
use MediaWiki\MediaWikiServices;

class SecondaryDataProvider implements \BlueSpice\Data\ISecondaryDataProvider {
	public function __construct() {}

	/**
	 *
	 * @param \BlueSpice\Data\Record[] $dataSets
	 * @return \BlueSpice\Data\Record[]
	 */
	public function extend( $dataSets ) {
		foreach( $dataSets as $record ) {

			$title = \Title::newFromID( $record->get( Record::PAGE_ID ) );

			$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
				$title
			);
			$record->set( Record::PAGE_LINK, $link );
		}

		return $dataSets;
	}
}
