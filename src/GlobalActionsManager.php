<?php

namespace BlueSpice\FlaggedRevsConnector;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;
use SpecialPage;

class GlobalActionsManager extends RestrictedTextLink {

	/**
	 *
	 */
	public function __construct() {
		parent::__construct( [] );
	}

	/**
	 *
	 * @return string
	 */
	public function getId() : string {
		return 'ga-bs-flaggedrevsconnector';
	}

	/**
	 *
	 * @return string[]
	 */
	public function getPermissions() : array {
		$permissions = [
			"review"
		];
		return $permissions;
	}

	/**
	 * @return string
	 */
	public function getHref() : string {
		$tool = SpecialPage::getTitleFor( 'QMOverview' );
		return $tool->getLocalURL();
	}

	/**
	 * @return Message
	 */
	public function getText() : Message {
		return Message::newFromKey( 'bs-flaggedrevsconnector-global-actions-entry' );
	}

	/**
	 * @return Message
	 */
	public function getTitle() : Message {
		return Message::newFromKey( 'bs-qmoverview-desc' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel() : Message {
		return Message::newFromKey( 'bs-flaggedrevsconnector-global-actions-entry' );
	}
}
