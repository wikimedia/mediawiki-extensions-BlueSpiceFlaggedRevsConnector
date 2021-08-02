<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use BlueSpice\FlaggedRevsConnector\Data\Record;
use MediaWiki\MediaWikiServices;
use Title;

class SecondaryDataProvider implements \BlueSpice\Data\ISecondaryDataProvider {

	/**
	 *
	 * @param \BlueSpice\Data\Record[] $dataSets
	 * @return \BlueSpice\Data\Record[]
	 */
	public function extend( $dataSets ) {
		foreach ( $dataSets as $record ) {

			$title = \Title::newFromID( $record->get( Record::PAGE_ID ) );

			$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
				$title
			);
			$record->set( Record::PAGE_LINK, $link );

			$categoryLinks = [];

			$categories = $record->get( Record::PAGE_CATEGORIES, [] );
			foreach ( $categories as $category ) {
				$categoryTitle = Title::makeTitle( NS_CATEGORY, $category );
				$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
					$categoryTitle,
					$categoryTitle->getText()
				);
				$categoryLinks[] = $link;
			}

			$record->set( Record::PAGE_CATEGORIES_LINKS, $categoryLinks );
		}

		return $dataSets;
	}
}
