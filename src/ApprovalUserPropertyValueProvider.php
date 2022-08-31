<?php

namespace BlueSpice\FlaggedRevsConnector;

use BlueSpice\SMWConnector\PropertyValueProvider;
use FlaggedRevision;
use MediaWiki\MediaWikiServices;
use SMW\DIWikiPage;

class ApprovalUserPropertyValueProvider extends PropertyValueProvider {

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-flaggedrevsconnector-approval-user-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-flaggedrevsconnector-approval-user-sesp-desc";
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_FRCAPPROVALUSER';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "QM/Approval by";
	}

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$flaggedRevision = FlaggedRevision::newFromStable( $semanticData->getSubject()->getTitle() );
		if ( $flaggedRevision !== null ) {
			$title = MediaWikiServices::getInstance()->getUserFactory()
				->newFromId( $flaggedRevision->getUser() )
				->getUserPage();
			$semanticData->addPropertyObjectValue( $property, DIWikiPage::newFromTitle( $title ) );
		}
	}
}
