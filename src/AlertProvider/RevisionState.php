<?php

namespace BlueSpice\FlaggedRevsConnector\AlertProvider;

use BlueSpice\AlertProviderBase;
use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\IAlertProvider;
use Config;
use FlaggableWikiPage;
use FlaggedRevision;
use Language;
use MediaWiki\MediaWikiServices;
use Message;
use Skin;
use Wikimedia\Rdbms\LoadBalancer;

class RevisionState extends AlertProviderBase {

	/**
	 *
	 * @var Language
	 */
	protected $lang = null;
	/**
	 *
	 * @var FlaggableWikiPage
	 */
	protected $flaggableWikiPage = null;

	/**
	 *
	 * @var Message
	 */
	protected $revisionStateMessage = null;

	/**
	 *
	 * @var string
	 */
	protected $revisionStateMessageType = null;

	/** @var Utils */
	protected $utils;

	/**
	 *
	 * @param Skin $skin
	 * @param LoadBalancer $loadBalancer
	 * @param Config $config
	 * @param FlaggableWikiPage $flaggableWikiPage
	 * @param Utils $utils
	 */
	public function __construct( $skin, $loadBalancer, $config, $flaggableWikiPage, $utils ) {
		$this->flaggableWikiPage = $flaggableWikiPage;
		$this->utils = $utils;
		parent::__construct( $skin, $loadBalancer, $config );
	}

	/**
	 *
	 * @return string
	 */
	public function getHTML() {
		return $this->getStateMessage() ? $this->getStateMessage()->parse() : '';
	}

	/**
	 *
	 * @return string
	 */
	public function getType() {
		return $this->getStateMessageType() ? $this->getStateMessageType() : '';
	}

	/**
	 *
	 * @return Message|null
	 */
	protected function getStateMessage() {
		if ( $this->revisionStateMessage instanceof Message ) {
			return $this->revisionStateMessage;
		}
		$this->initFromContext();
		return $this->revisionStateMessage;
	}

	/**
	 *
	 * @return string|null
	 */
	protected function getStateMessageType() {
		if ( $this->revisionStateMessageType !== null ) {
			return $this->revisionStateMessageType;
		}
		$this->initFromContext();
		return $this->revisionStateMessageType;
	}

	/**
	 *
	 * @param Skin $skin
	 * @return IAlertProvider
	 */
	public static function factory( $skin ) {
		$loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' );
		$utils = new Utils( $config );
		$flaggableWikiPage = $utils->getFlaggableWikiPage( $skin->getContext() );

		return new static(
			$skin,
			$loadBalancer,
			$config,
			$flaggableWikiPage,
			$utils
		);
	}

	protected function initFromContext() {
		if ( $this->skipForContextReasons() ) {
			return;
		}

		if ( !$this->utils->isFlaggableNamespace( $this->skin->getTitle() ) ) {
			return;
		}

		$hasStable = $this->utils->getFlaggableWikiPage( $this->skin->getContext() )
				->getStableRev() instanceof FlaggedRevision;
		$showingStable = $this->utils->isShowingStable( $this->skin->getContext() );
		$inSync = $this->utils->getFlaggableWikiPage( $this->skin->getContext() )->stableVersionIsSynced();
		$pendingCount = (int)$this->utils->getFlaggableWikiPage( $this->skin->getContext() )->getPendingRevCount();
		$userCanSeeDrafts = $this->utils->userCanAccessDrafts( $this->getUser() );

		if ( !$hasStable ) {
			$this->revisionStateMessage =
				$this->skin->msg( 'bs-flaggedrevsconnector-state-unmarked-desc' );
			$this->revisionStateMessageType = IAlertProvider::TYPE_DANGER;
		}

		if ( $showingStable ) {
			if ( !$inSync && $pendingCount === 0 && $userCanSeeDrafts ) {
				$this->revisionStateMessage = $this->skin->msg(
					'bs-flaggedrevsconnector-state-implicit-draft-desc'
				);
			} else {
				$this->revisionStateMessage =
					$this->skin->msg( 'bs-flaggedrevsconnector-state-stable-desc' );
			}
			$this->revisionStateMessageType = IAlertProvider::TYPE_SUCCESS;
		}

		if ( !$showingStable ) {
			if ( $pendingCount > 0 ) {
				$this->revisionStateMessage = $this->skin->msg(
					'bs-flaggedrevsconnector-state-draft-desc',
					$pendingCount,
					$this->skin->getContext()->getLanguage()->date(
						$this->flaggableWikiPage->getStableRev()->getTimestamp()
					)
				);
				$this->revisionStateMessageType = IAlertProvider::TYPE_WARNING;
				return;
			}

			if ( !$inSync ) {
				$this->revisionStateMessage = $this->skin->msg(
					'bs-flaggedrevsconnector-state-draft-resources-desc'
				);
				$this->revisionStateMessageType = IAlertProvider::TYPE_WARNING;
			}
		}
	}

	/**
	 *
	 * @return bool
	 */
	protected function skipForContextReasons() {
		if ( !$this->flaggableWikiPage ) {
			return true;
		}
		if ( !$this->skin->getTitle()->exists() ) {
			return true;
		}

		$currentAction = $this->skin->getRequest()->getVal( 'action', 'view' );
		if ( $currentAction === 'history' ) {
			return true;
		}

		return false;
	}

}
