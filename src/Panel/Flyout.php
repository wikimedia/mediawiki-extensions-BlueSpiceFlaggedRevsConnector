<?php

namespace BlueSpice\FlaggedRevsConnector\Panel;

use BlueSpice\Calumma\Panel\BasePanel;
use BlueSpice\Calumma\IFlyout;
use FlaggableWikiPage;
use BlueSpice\Services;
use BlueSpice\FlaggedRevsConnector\Utils;
use FormatJson;
use Html;
use MediaWiki\MediaWikiServices;
use Message;
use QuickTemplate;
use Title;

class Flyout extends BasePanel implements IFlyout {
	/** @var \Wikimedia\Rdbms\LoadBalancer  */
	private $loadBalancer = null;
	/** @var \MediaWiki\Linker\LinkRenderer  */
	private $linkRenderer = null;

	public function __construct( QuickTemplate $skintemplate ) {
		parent::__construct( $skintemplate );

		$services = MediaWikiServices::getInstance();
		$this->loadBalancer = $services->getDBLoadBalancer();
		$this->linkRenderer = $services->getLinkRenderer();
	}

	public function getHtmlId() {
		return 'bs-flaggedrevs-flyout';
	}

	/**
	 * @return \Message
	 */
	public function getFlyoutTitleMessage() {
		return wfMessage( 'bs-flaggedrevsconnector-flyout-title' );
	}

	/**
	 * @return \Message
	 */
	public function getFlyoutIntroMessage() {
		return wfMessage( 'bs-flaggedrevsconnector-flyout-intro' );
	}

	/**
	 * @return \Message
	 */
	public function getTitleMessage() {
		return wfMessage( 'bs-flaggedrevsconnector-nav-link-title' );
	}

	/**
	 *
	 * @return array
	 */
	public function getContainerData() {
		$flagInfo = $this->getFlagInfo();
		$flagInfo['pendingchanges'] = false;
		if( $this->hasPendingChanges() ) {
			$flagInfo['pendingchanges'] = true;
			if ( $this->getFlaggableWikiPage()->onlyTemplatesOrFilesPending() ) {
				$flagInfo['resource_changes'] = FormatJson::encode(
					$this->getResourceChangesLinks()
				);
			}
		}

		return $flagInfo;
	}

	/**
	 * @return array
	 */
	private function getResourceChangesLinks() {
		$wp = $this->getFlaggableWikiPage();
		$lastRevID = $wp->getTitle()->getLatestRevID();
		$links = [];
		$this->makeTemplateResourceLinks( $lastRevID, $links );
		$this->makeFileResourceLinks( $lastRevID, $links );

		return $links;
	}

	/**
	 * @param int $lastRevID
	 * @param array &$links
	 */
	private function makeTemplateResourceLinks( $lastRevID, &$links ) {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $dbr->select( 'flaggedtemplates', [
			'ft_namespace', 'ft_title', 'ft_tmp_rev_id'
		], [
			'ft_rev_id' => $lastRevID
		], __METHOD__ );

		foreach ( $res as $row ) {
			$title = Title::makeTitle( $row->ft_namespace, $row->ft_title );
			if ( $title->getLatestRevID() === (int) $row->ft_tmp_rev_id ) {
				continue;
			}
			$links[$title->getPrefixedDBkey()] = $this->linkRenderer->makeLink( $title, null, [], [
				'diff' => $title->getLatestRevID(),
				'oldid' => $row->ft_tmp_rev_id
			] );
		}
	}

	/**
	 * @param int $lastRevID
	 * @param array &$links
	 */
	private function makeFileResourceLinks( $lastRevID, &$links ) {
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
			if ( $rev->getTimestamp() === $row->fi_img_timestamp ) {
				continue;
			}
			$links[$title->getPrefixedDBkey()] = $this->linkRenderer->makeLink( $title );
		}
	}

	/**
	 * @return string
	 */
	public function getBody() {
		if( !$this->hasPendingChanges() ) {
			return '';
		}

		$this->fetchDraftRevisionsAfterCurrentStable();
		$links = $this->makeDraftAuthorLinks();

		$numLinks = count( $links );
		if( $numLinks > 3 ) {
			$links = array_slice( $links, 0, 3 );
			$links[] = '...';
		}

		if ( $numLinks === 0 ) {
			$hint =  Message::newFromKey(
				'bs-flaggedrevsconnector-flyout-body-hint-draft-implicit'
			)->text();
		}
		else {
			$hint = Message::newFromKey(
				'bs-flaggedrevsconnector-flyout-body-hint-draft',
				count( $this->draftRevisionsAfterCurrentStable ),
				implode( ', ', $links )
			)->text();
		}

		return \Html::rawElement(
			'div',
			[
				'class' => 'flyout-body-hint drafts'
			],
			$hint
		);
	}

	/**
	 * @var \MediaWiki\Storage\RevisionRecord
	 */
	protected $draftRevisionsAfterCurrentStable = [];

	/**
	 *
	 */
	protected function fetchDraftRevisionsAfterCurrentStable() {
		if( !$this->getFlaggableWikiPage()->revsArePending() ) {
			return;
		}
		$lookup = Services::getInstance()->getRevisionLookup();
		$next = $lookup->getRevisionById(
			$this->getFlaggableWikiPage()->getStable()
		);
		while( $next = $lookup->getNextRevision( $next ) ) {
			$this->draftRevisionsAfterCurrentStable[] = $next;
		}
	}

	/**
	 * @return string[]
	 */
	protected function makeDraftAuthorLinks() {
		$usernames = [];
		foreach( $this->draftRevisionsAfterCurrentStable as $revisionRecord ) {
			$userIdentity = $revisionRecord->getUser();
			$usernames[ $userIdentity->getId() ] = $userIdentity->getName();
		}

		$linkRenderer = Services::getInstance()->getLinkRenderer();
		$links = [];
		foreach( $usernames as $username ) {
			$target = Title::makeTitle( NS_USER , $username );
			$links[] = $linkRenderer->makeLink( $target, $username );
		}

		return $links;
	}

	/**
	 *
	 * @return string
	 */
	public function getTriggerCallbackFunctionName() {
		return 'bs.flaggedrevsconnector.flyoutCallback';
	}

	/**
	 *
	 * @return string[]
	 */
	public function getTriggerRLDependencies() {
		return [ 'ext.bluespice.flaggedRevsConnector.flyout' ];
	}

	/**
	 *
	 * @return array
	 */
	protected function getFlagInfo() {
		$frc = Services::getInstance()->getService( 'BSExtensionFactory' )->getExtension(
			'BlueSpiceFlaggedRevsConnector'
		);
		return $frc->collectFlagInfo( $this->skintemplate->getSkin()->getTitle() );
	}

	/**
	 *
	 * @var FlaggableWikiPage
	 */
	protected $flaggableWikiPage = null;

	/**
	 *
	 * @return \FlaggableWikiPage
	 */
	protected function getFlaggableWikiPage() {
		if( $this->flaggableWikiPage === null ) {
			$this->flaggableWikiPage = FlaggableWikiPage::getTitleInstance(
				$this->skintemplate->getSkin()->getTitle()
			);
		}

		return $this->flaggableWikiPage;
	}

	/**
	 *
	 * @return bool
	 */
	protected function hasPendingChanges() {
		$config = Services::getInstance()->getConfigFactory()->makeConfig( 'bsg' );
		$utils = new Utils( $config );
		if( !$utils->userCanAccessDrafts( $this->skintemplate->getSkin()->getUser() ) ) {
			return false;
		}

		return !$this->getFlaggableWikiPage()->stableVersionIsSynced();
	}
}
