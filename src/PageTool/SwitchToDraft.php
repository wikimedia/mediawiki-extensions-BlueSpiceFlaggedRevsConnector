<?php

namespace BlueSpice\FlaggedRevsConnector\PageTool;

use BlueSpice\PageTool\Base;
use MediaWiki\MediaWikiServices;
use MediaWiki\Linker\LinkRenderer;
use FlaggableWikiPage;
use Message;
use BlueSpice\FlaggedRevsConnector\Utils;

class SwitchToDraft extends Base {

	/**
	 * @var LinkRenderer
	 */
	protected $linkRenderer = null;

	/**
	 * @return string
	 */
	protected function doGetHtml() {
		if ( !$this->userMayAccessDrafts() ) {
			return '';
		}

		$this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$flaggableWikiPage = FlaggableWikiPage::getTitleInstance( $this->context->getTitle() );
		$showingStable = \FlaggablePageView::singleton()->showingStable();
		$hasDrafts = $flaggableWikiPage->getPendingRevCount() > 0;
		$stableQuality = $flaggableWikiPage->getStableRev()
			? $flaggableWikiPage->getStableRev()->getQuality()
			: 0;
		$stableRev = $flaggableWikiPage->getStableRev()
			? $flaggableWikiPage->getStableRev()->getRevId()
			: 0;
		$latestRevision = $this->context->getTitle()->getLatestRevID();

		if ( $showingStable && $hasDrafts ) {
			return $this->makeSwitchToDraftLink();
		} elseif ( $stableRev && $stableRev !== $latestRevision && $stableQuality > 0 ) {
			return $this->makeSwitchToStableLink();
		}
		return '';
	}

	/**
	 *
	 * @return int
	 */
	public function getPosition() {
		return 30;
	}

	private function makeSwitchToDraftLink() {
		$linkTextMsg = new Message( 'bs-flaggedrevsconnector-pagetool-switchtodraft-draft-text' );
		$linkTitleMsg = new Message( 'bs-flaggedrevsconnector-pagetool-switchtodraft-draft-title' );
		$link = $this->linkRenderer->makeLink(
			$this->context->getTitle(),
			$linkTextMsg->text(),
			[
				'title' => $linkTitleMsg->text(),
				'class' => 'page-tool-text'
			],
			[
				'stable' => 0
			]
		);

		return $link;
	}

	private function makeSwitchToStableLink() {
		$linkTextMsg = new Message( 'bs-flaggedrevsconnector-pagetool-switchtodraft-stable-text' );
		$linkTitleMsg = new Message( 'bs-flaggedrevsconnector-pagetool-switchtodraft-stable-title' );
		$link = $this->linkRenderer->makeLink(
			$this->context->getTitle(),
			$linkTextMsg->text(),
			[
				'title' => $linkTitleMsg->text(),
				'class' => 'page-tool-text'
			], [
				'stable' => 1
			]
		);

		return $link;
	}

	/**
	 *
	 * @return bool
	 */
	private function userMayAccessDrafts() {
		$utils = new Utils( $this->config );
		return $utils->userCanAccessDrafts( $this->context->getUser() );
	}

}
