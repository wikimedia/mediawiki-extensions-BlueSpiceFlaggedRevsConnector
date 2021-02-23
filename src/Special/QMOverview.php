<?php

namespace BlueSpice\FlaggedRevsConnector\Special;

use FlaggedRevsConnector;

class QMOverview extends \BlueSpice\SpecialPage {

	public function __construct() {
		parent::__construct( 'QMOverview', 'review' );
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->addHTML( \Html::element( 'div', [
			'id' => 'bs-flaggedrevsconnector-flagged-pages-grid'
		] ) );

		$this->getOutput()->addModules( 'ext.bs.flaggedrevsconnector.flaggedpages' );

		// There does not seem to be a way to fetch all available status in ExtJS,
		// it will always only shows ones from the currently loaded set
		$availableStates = [
			wfMessage(
				"bs-flaggedrevsconnector-state-" . FlaggedRevsConnector::STATE_UNMARKED
			)->text(),
			wfMessage(
				"bs-flaggedrevsconnector-state-" . FlaggedRevsConnector::STATE_DRAFT
			)->text(),
			wfMessage(
				"bs-flaggedrevsconnector-state-" . FlaggedRevsConnector::STATE_STABLE
			)->text()
		];
		$this->getOutput()->addJsConfigVars(
			'bsgFlaggedRevConnectorAvailableStates',
			$availableStates
		);

	}

}
