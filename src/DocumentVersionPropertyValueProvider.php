<?php

namespace BlueSpice\FlaggedRevsConnector;

use BlueSpice\SMWConnector\PropertyValueProvider;
use FlaggedRevision;
use FlaggedRevs;
use SMW\DIWikiPage;
use SMWDINumber;
use SMWDataItem;
use User;

class DocumentVersionPropertyValueProvider extends PropertyValueProvider {

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-frc-document-version-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-frc-document-version-sesp-desc";
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
			array(
				'fr_page_id' => $title->getArticleID(),
				'fr_quality > 0' //May be 1 or 2 in our setups
			),
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

