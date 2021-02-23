<?php

namespace BlueSpice\FlaggedRevsConnector\Panel;

use BlueSpice\Calumma\Panel\BasePanel;
use BlueSpice\Calumma\IFlyout;
use FlaggableWikiPage;
use BlueSpice\Services;
use BlueSpice\FlaggedRevsConnector\Utils;
use Html;
use Message;

class Flyout extends BasePanel implements IFlyout {

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
	 * @return bool
	 */
	public function getContainerData() {
		$flagInfo = $this->getFlagInfo();
		$flagInfo['pendingchanges'] = false;
		if( $this->hasPendingChanges() ) {
			$flagInfo['pendingchanges'] = true;
		}
		return $flagInfo;
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
			$target = \Title::makeTitle( NS_USER , $username );
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
