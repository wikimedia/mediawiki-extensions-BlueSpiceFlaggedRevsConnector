<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\FlaggedRevsRevisionReviewFormAfterDoSubmit;

use BlueSpice\Hook;
use BlueSpice\ReadConfirmation\IMechanism;
use BlueSpice\ReadConfirmation\MechanismFactory;
use ExtensionRegistry;
use RevisionReviewForm;

class SendReadConfirmationOnApprove extends Hook {

	/**
	 * @var RevisionReviewForm
	 */
	protected $revisionReviewForm;

	/**
	 * @var mixed
	 */
	protected $status;

	/**
	 * @param RevisionReviewForm $revisionReviewForm
	 * @param mixed $status - true on success, error string on failure
	 * @return bool
	 */
	public static function callback( RevisionReviewForm $revisionReviewForm, $status ) {
		$hookHandler = new self( $revisionReviewForm, $status );
		return $hookHandler->process();
	}

	/**
	 *
	 * @param RevisionReviewForm $revisionReviewForm
	 * @param mixed $status
	 * @param \IContextSource $context
	 * @param \Config $config
	 */
	public function __construct( RevisionReviewForm $revisionReviewForm, $status, $context, $config ) {
		parent::__construct( $context, $config );
		$this->revisionReviewForm = $revisionReviewForm;
		$this->status = $status;
	}

	/**
	 * @return bool
	 */
	protected function doProcess() {
		$userAgent = $this->getServices()->getService( 'BSUtilityFactory' )
			->getMaintenanceUser()->getUser();
		$this->getReadConfirmationMechanism()->notify(
			$this->revisionReviewForm->getPage(),
			$userAgent
		);
		return true;
	}

	/**
	 * @return IMechanism
	 */
	private function getReadConfirmationMechanism() {
		/** @var MechanismFactory $factory */
		$factory = $this->getServices()->getService(
			'BSReadConfirmationMechanismFactory'
		);

		return $factory->getMechanismInstance();
	}

	/**
	 * @return bool
	 */
	protected function skipProcessing() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceReadConfirmation' ) ) {
			return true;
		}
		return false;
	}
}
