<?php

namespace BlueSpice\FlaggedRevsConnector;

use Config;
use FlaggablePageView;
use FlaggableWikiPage;
use IContextSource;
use MediaWiki\MediaWikiServices;
use Title;
use BlueSpice\Services;
use User;

class Utils {

	/**
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 *
	 * @param Config $config
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 *
	 * @param User $user
	 * @return bool
	 */
	public function userCanAccessDrafts( $user ) {
		$permittedGroups = $this->config->get( 'FlaggedRevsConnectorDraftGroups' );
		$currentUserGroups = $user->getEffectiveGroups();
		$groupIntersect = array_intersect( $permittedGroups, $currentUserGroups );

		if ( empty( $groupIntersect ) ) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function isFlaggableNamespace( Title $title ) {
		global $wgFlaggedRevsNamespaces;

		$frc = Services::getInstance()->getService( 'BSExtensionFactory' )->getExtension(
			'BlueSpiceFlaggedRevsConnector'
		);
		$flagInfo = $frc->collectFlagInfo( $title );

		if ( !in_array( $title->getNamespace(), $wgFlaggedRevsNamespaces ) ) {
			return false;
		}

		if ( $flagInfo['state'] == 'notreviewable' ) {
			return false;
		}

		return true;
	}

	/**
	 * @param IContextSource $context
	 * @return FlaggableWikiPage
	 */
	public function getFlaggableWikiPage( $context ) {
		return FlaggableWikiPage::getTitleInstance( $context->getTitle() );
	}

	/**
	 * @param IContextSource $context
	 * @return bool
	 */
	public function isShowingStable( $context ) {
		// FR does check if stable is requested in the request,
		// but not if draft is
		$request = $context->getRequest();
		$draftByRequest = $request->getBool( 'stable', true ) === false;
		if ( $draftByRequest ) {
			return false;
		}

		return FlaggablePageView::singleton()->showingStable() ||
			$this->currentPageIsStable( $context );
	}

	/**
	 * @param Title $title
	 * @return int
	 */
	public function getApprovedRevisionId( Title $title ) {
		$row = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_REPLICA )
			->selectRow(
				'flaggedpages',
				[ 'fp_stable' ],
				[ 'fp_page_id' => $title->getArticleID() ],
				__METHOD__
			);

		if ( !$row ) {
			return null;
		}

		return (int)$row->fp_stable;
	}

	/**
	 * @param IContextSource $context
	 * @return bool
	 */
	private function currentPageIsStable( $context ) {
		$article = FlaggablePageView::globalArticleInstance();
		if ( $article === null ) {
			return false;
		}
		$stableRev = $article->getStableRev();
		if ( !$stableRev ) {
			return false;
		}
		return $stableRev->getRevId() === $this->getCurrentPageRevId( $context );
	}

	/**
	 * @param IContextSource $context
	 * @return bool|int
	 */
	private function getCurrentPageRevId( $context ) {
		$request = $context->getRequest();
		$id = $request->getVal( 'oldid', $request->getVal( 'stableid', null ) );
		if ( $id === null ) {
			$title = $context->getTitle();
			if ( $title instanceof Title ) {
				return $title->getLatestRevID();
			}
		}

		return (int)$id;
	}

}
