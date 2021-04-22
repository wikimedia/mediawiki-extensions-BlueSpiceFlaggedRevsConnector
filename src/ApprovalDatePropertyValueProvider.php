<?php

namespace BlueSpice\FlaggedRevsConnector;

use BlueSpice\SMWConnector\PropertyValueProvider;
use FlaggedRevision;
use SMWDataItem;
use SMWDITime;

class ApprovalDatePropertyValueProvider extends PropertyValueProvider {

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-flaggedrevsconnector-approval-date-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-flaggedrevsconnector-approval-date-sesp-desc";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_TIME;
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_FRCAPPROVALDATE';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "QM/Approval date";
	}

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 * @return null
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$flaggedRevision = FlaggedRevision::newFromStable( $semanticData->getSubject()->getTitle() );
		if ( $flaggedRevision !== null ) {
			$semanticData->addPropertyObjectValue(
				$property, SMWDITime::newFromTimestamp( $flaggedRevision->getTimestamp() )
			);
		}
	}
}
