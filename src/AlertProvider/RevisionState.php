<?php

namespace BlueSpice\FlaggedRevsConnector\AlertProvider;

use BlueSpice\AlertProviderBase;
use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\IAlertProvider;
use Config;
use ExtensionRegistry;
use FlaggableWikiPage;
use FlaggedRevision;
use Language;
use MediaWiki\MediaWikiServices;
use Message;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use Skin;
use Title;
use Wikimedia\Rdbms\LoadBalancer;

class RevisionState extends AlertProviderBase {

	/**
	 *
	 * @var Language
	 */
	protected $lang = null;
	/**
	 *
	 * @var FlaggableWikiPage|null
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
	 * @param FlaggableWikiPage|null $flaggableWikiPage
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
		$html = $this->getStateMessage();
		if ( is_object( $html ) && $html ) {
			return ( $html )->toString();
		}
		if ( $html ) {
			return $html->parse();
		}
		return '';
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
	 * @inheritDoc
	 */
	public static function factory( $skin = null ) {
		$loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' );
		$utils = new Utils( $config );
		$flaggableWikiPage = null;
		$context = $skin->getContext();
		$title = $context->getTitle();
		if ( $title ) {
			if ( !$title->isSpecialPage() ) {
				$flaggableWikiPage = $utils->getFlaggableWikiPage( $context );
			}
		}

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

		$message = $type = '';
		$implicitDraft = false;

		$hasStable = $this->utils->getFlaggableWikiPage( $this->skin->getContext() )
				->getStableRev() instanceof FlaggedRevision;
		$showingStable = $this->utils->isShowingStable( $this->skin->getContext() );
		$inSync = $this->utils->getFlaggableWikiPage( $this->skin->getContext() )->stableVersionIsSynced();
		$pendingCount = (int)$this->utils->getFlaggableWikiPage( $this->skin->getContext() )->getPendingRevCount();

		if ( !$hasStable ) {
			$message = $this->skin->msg( 'bs-flaggedrevsconnector-state-unmarked-desc' );
			$type = IAlertProvider::TYPE_DANGER;
		}

		if ( $showingStable ) {
			return;
		}
		if ( !$showingStable ) {
			if ( $pendingCount > 0 ) {
				$message = $this->skin->msg(
					'bs-flaggedrevsconnector-state-draft-desc',
					$pendingCount,
					$this->skin->getContext()->getLanguage()->date(
						$this->flaggableWikiPage->getStableRev()->getTimestamp()
					)
				);
				$type = IAlertProvider::TYPE_WARNING;
			} elseif ( !$inSync ) {
				$out = $this->skin->getOutput();
				$out->enableOOUI();
				$out->addModuleStyles( 'ext.bluespice.flaggedrevsconnector.alert.styles' );
				$out->addModules( 'ext.bluespice.flaggedrevsconnector.alert' );

				$message = $this->skin->msg(
					'bs-flaggedrevsconnector-state-draft-resources-desc'
				);
				$type = IAlertProvider::TYPE_WARNING;
				$implicitDraft = true;

				$labelWidget = new LabelWidget( [ 'label' => $message->parse() ] );
				$infoBtn = new ButtonWidget( [
					'icon' => 'infoFilled',
					'id' => 'bs-flagged-info-btn',
					'infusable' => true,
					'framed' => false,
					'data' => $this->getChangedFiles()
				] );
				$items['items'] = [ $labelWidget, $infoBtn ];
				$this->revisionStateMessage = new HorizontalLayout( $items );
			}
		}

		if ( $this->skipOnSkins( $this->skin->getSkinName() ) && !$implicitDraft ) {
			return;
		}

		if ( !$implicitDraft ) {
			$this->revisionStateMessage = $message;
		}
		$this->revisionStateMessageType = $type;
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

	/**
	 * @param string $skinName
	 * @return bool
	 */
	protected function skipOnSkins( $skinName ) {
		if ( $skinName !== 'BlueSpiceCalumma'
			&& ExtensionRegistry::getInstance()->isLoaded( 'PageHeader' ) ) {
			// ERM:25780 Remove banner if PageHeader extension provides the status
			// sentence and the skin is not BlueSpiceCalumma.
			// This is not a good way, but the only one it seems :/
			return true;
		}

		return false;
	}

	/**
	 *
	 * @return array
	 */
	protected function getChangedFiles() {
		if ( !$this->utils->isFlaggableNamespace( $this->skin->getTitle() ) ) {
			return [];
		}

		$links = [];
		$flaggablePage = $this->utils->getFlaggableWikiPage( $this->skin->getContext() );
		if ( $flaggablePage->onlyTemplatesOrFilesPending() ) {
			$lastRevID = $flaggablePage->getTitle()->getLatestRevID();

			$services = MediaWikiServices::getInstance();
			$this->loadBalancer = $services->getDBLoadBalancer();
			$this->linkRenderer = $services->getLinkRenderer();
			$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
			$res = $dbr->select( 'flaggedimages', [
				'fi_name', 'fi_img_timestamp'
			], [
				'fi_rev_id' => $lastRevID
			], __METHOD__ );

			foreach ( $res as $row ) {
				$title = Title::makeTitle( NS_FILE, $row->fi_name );
				$rev = MediaWikiServices::getInstance()->getRevisionStore()->getRevisionById(
					$title->getLatestRevID()
				);
				if ( $rev === null ) {
					continue;
				}
				if ( $rev->getTimestamp() === $row->fi_img_timestamp ) {
					continue;
				}
				array_push( $links, $this->linkRenderer->makeLink( $title ) );
			}
		}

		return $links;
	}
}
