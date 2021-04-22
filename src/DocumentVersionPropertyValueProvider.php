<?php

namespace BlueSpice\FlaggedRevsConnector;

use BlueSpice\SMWConnector\PropertyValueProvider;
use FlaggedRevs;
use SMWDataItem;
use SMWDINumber;

class DocumentVersionPropertyValueProvider extends PropertyValueProvider {

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-flaggedrevsconnector-document-version-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-flaggedrevsconnector-document-version-sesp-desc";
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_FRCDOCVERSION';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "QM/Document version";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_NUMBER;
	}

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 * @return null
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$title = $semanticData->getSubject()->getTitle();
		if ( !FlaggedRevs::inReviewNamespace( $title ) ) {
			return;
		}
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'flaggedrevs',
			'*',
			[
				'fr_page_id' => $title->getArticleID(),
				// May be 1 or 2 in our setups
				'fr_quality > 0'
			],
			__METHOD__
		);

		$numberOfStableRevisions = $dbr->numRows( $res );

		if ( $numberOfStableRevisions > 0 ) {
			$semanticData->addPropertyObjectValue(
				$property, new SMWDINumber( $numberOfStableRevisions )
			);
		}
	}
}
