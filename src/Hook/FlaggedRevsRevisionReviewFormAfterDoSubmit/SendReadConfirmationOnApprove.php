<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

use BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;
use BlueSpice\FlaggedRevsConnector\ReadConfirmation\Mechanism\PageApproved;
use ExtensionRegistry;

class SendReadConfirmationOnApprove extends FlaggedRevsRevisionReviewFormAfterDoSubmit {

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$this->getServices()->getService( 'BSReadConfirmationMechanismFactory' )
			->getMechanismInstance()
			->notify(
				$this->revisionReviewForm->getPage(),
				$this->revisionReviewForm->getUser()
		);
		return true;
	}

	/**
	 * @return bool
	 */
	protected function skipProcessing() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceReadConfirmation' ) ) {
			return true;
		}
		$machanism = $this->getServices()->getService( 'BSReadConfirmationMechanismFactory' )
			->getMechanismInstance();
		if ( !$machanism instanceof PageApproved ) {
			return true;
		}
		return false;
	}
}
