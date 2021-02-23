<?php

namespace BlueSpice\FlaggedRevsConnector;

use BlueSpice\SMWConnector\PropertyValueProvider;
use FlaggedRevision;
use SMW\DIWikiPage;
use User;

class ApprovalUserPropertyValueProvider extends PropertyValueProvider {

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-frc-approval-user-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-frc-approval-user-sesp-desc";
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
			$semanticData->addPropertyObjectValue(
				$property, DIWikiPage::newFromTitle(
					User::newFromId( $flaggedRevision->getUser() )->getUserPage()
				)
			);
		}
	}
}

