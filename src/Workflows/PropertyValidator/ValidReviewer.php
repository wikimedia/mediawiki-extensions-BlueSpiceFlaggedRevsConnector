<?php

namespace BlueSpice\FlaggedRevsConnector\Workflows\PropertyValidator;

use MediaWiki\Extension\Workflows\IActivity;
use MediaWiki\Extension\Workflows\PropertyValidator\ExistingUser;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;
use Message;

class ValidReviewer extends ExistingUser {
	/** @var PermissionManager */
	private $permissionManager;
	/** @var string[] */
	private $requiredRights = [ 'review', 'validate', 'unreviewedpages', 'movestable' ];

	/**
	 * @param UserFactory $userFactory
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( UserFactory $userFactory, PermissionManager $permissionManager ) {
		parent::__construct( $userFactory );
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function validate( $value, IActivity $activity ) {
		if ( !parent::validate( $value, $activity ) ) {
			return false;
		}
		$user = $this->userFactory->newFromName( $value );
		foreach ( $this->requiredRights as $right ) {
			if ( !$this->permissionManager->userHasRight( $user, $right ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getError( $value ): Message {
		$user = $this->userFactory->newFromName( $value );
		if ( !$user instanceof \User ) {
			return parent::getError( $value );
		}
		return Message::newFromKey(
			'bs-flaggedrevsconnector-workflows-property-validator-valid-reviewer-error'
		)->params(
			$user->getName(),
			count( $this->requiredRights ),
			implode( ', ', $this->requiredRights )
		);
	}
}
