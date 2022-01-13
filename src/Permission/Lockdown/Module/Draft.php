<?php

namespace BlueSpice\FlaggedRevsConnector\Permission\Lockdown\Module;

use IContextSource;
use Config;
use Message;
use Title;
use User;
use BlueSpice\Services;
use Wikimedia\Rdbms\LoadBalancer;
use FlaggablePageView;
use FlaggableWikiPage;

class Draft extends \BlueSpice\Permission\Lockdown\Module {

	/**
	 *
	 * @var string[]
	 */
	protected $allowedRequestActions = null;

	/**
	 *
	 * @var int[]
	 */
	protected $frNamespaces = null;

	/**
	 *
	 * @var string[]
	 */
	protected $allowedGroups = null;

	/**
	 *
	 * @var LoadBalancer
	 */
	protected $loadBalancer = null;

	/**
	 *
	 * @var int[]
	 */
	protected $stableRevisionIDs = null;

	/**
	 *
	 * @var FlaggableWikiPage
	 */
	protected $flaggableWikiPage = null;

	/**
	 *
	 * @param Config $config
	 * @param IContextSource $context
	 * @param Services $services
	 * @param LoadBalancer $loadBalancer
	 * @param array $allowedRequestActions
	 * @param array $frNamespaces
	 * @param array $allowedGroups
	 */
	protected function __construct( Config $config, IContextSource $context, Services $services,
		LoadBalancer $loadBalancer, array $allowedRequestActions, array $frNamespaces,
		array $allowedGroups ) {
		parent::__construct( $config, $context, $services );

		$this->loadBalancer = $loadBalancer;
		$this->allowedRequestActions = $allowedRequestActions;
		$this->frNamespaces = $frNamespaces;
		$this->allowedGroups = $allowedGroups;
	}

	/**
	 *
	 * @param Config $config
	 * @param IContextSource $context
	 * @param Services $services
	 * @param LoadBalancer|null $loadBalancer
	 * @param array|null $allowedRequestActions
	 * @param array|null $frNamespaces
	 * @param array|null $allowedGroups
	 * @return \static
	 */
	public static function getInstance( Config $config, IContextSource $context,
		Services $services, LoadBalancer $loadBalancer = null,
		array $allowedRequestActions = null, array $frNamespaces = null,
		array $allowedGroups = null ) {
		if ( !$loadBalancer ) {
			$loadBalancer = $services->getDBLoadBalancer();
		}

		if ( !$allowedRequestActions ) {
			$allowedRequestActions = [
				"history"
			];
		}

		if ( !$frNamespaces ) {
			$frNamespaces = $config->has( 'FlaggedRevsNamespaces' )
				? $config->get( 'FlaggedRevsNamespaces' )
				: $GLOBALS['wgFlaggedRevsNamespaces'];
		}

		if ( !$allowedGroups ) {
			$allowedGroups = $config->get( 'FlaggedRevsConnectorDraftGroups' );
		}

		// always allowed because of reasons...
		$allowedGroups = array_merge( $allowedGroups, [ 'sysop', 'reviewer' ] );
		$allowedGroups = array_unique( $allowedGroups );

		return new static(
			$config,
			$context,
			$services,
			$loadBalancer,
			$allowedRequestActions,
			$frNamespaces,
			$allowedGroups
		);
	}

	/**
	 *
	 * @param Title $title
	 * @param User $user
	 * @return bool
	 */
	public function applies( Title $title, User $user ) {
		if ( $title instanceof Title === false ) {
			// I.e. CLI
			return false;
		}
		if ( !$title->exists() ) {
			return false;
		}
		if ( !in_array( $title->getNamespace(), $this->frNamespaces ) ) {
			return false;
		}
		if ( !$this->getContext()->getTitle() ) {
			// in cli i.e. bail out, cause there is not Title set by default
			return false;
		}
		// Flagged revs mehtods form FlaggedPageView only works with current context
		// cause of bad singelton pattern. So this only applies when the Article,
		// that is is requested for in userCan is currently showen. This means, that
		// whenever userCan is requested for the users, they can still see it on
		// other places like lists and in the search i.e.
		// This was already the case before the update to the new permission
		// lockdown
		if ( !$title->equals( $this->getContext()->getTitle() ) ) {
			return false;
		}
		$groupInters = array_intersect(
			$this->allowedGroups,
			$this->getUserGroups( $user )
		);
		if ( count( $groupInters ) > 0 ) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @return bool
	 */
	public function mustLockdown( Title $title, User $user, $action ) {
		if ( $action !== 'read' ) {
			return false;
		}
		$requAction = $this->context->getRequest()->getVal( 'action', 'view' );
		if ( in_array( $requAction, $this->allowedRequestActions ) ) {
			return false;
		}
		$diffId = $this->context->getRequest()->getInt( 'diff', 0 );
		if ( $diffId > 0 && !in_array( $diffId, $this->getStableVersions( $title ) ) ) {
			return true;
		}
		if ( $this->isRequestedRevStable( $title ) ) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @return Message
	 */
	public function getLockdownReason( Title $title, User $user, $action ) {
		$actionMsg = $this->msg( "right-$action" );
		return $this->msg(
			'bs-flaggedrevsconnector-draft-lockdown-reason',
			$actionMsg->exists() ? $actionMsg : $action,
			count( $this->allowedGroups ),
			implode( ', ', $this->allowedGroups )
		);
	}

	/**
	 *
	 * @param Title $title
	 * @return int[]
	 */
	protected function getStableVersions( Title $title ) {
		if ( $this->stableRevisionIDs !== null ) {
			return $this->stableRevisionIDs;
		}
		$this->stableRevisionIDs = [];
		$res = $this->loadBalancer->getConnection( DB_REPLICA )->select(
			'flaggedrevs',
			'fr_rev_id',
			[
				'fr_page_id' => $title->getArticleId(),
				// Show all with some degree of stability
				'fr_quality > 0'
			],
			__METHOD__
		);
		foreach ( $res as $row ) {
			$this->stableRevisionIDs[] = $row->fr_rev_id;
		}

		return $this->stableRevisionIDs;
	}

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	protected function isRequestedRevStable( Title $title ) {
		$pdfStablePageId = $this->context->getRequest()->getInt( 'pdfstablepageid', -1 );
		// Do explicit, global webrequest independent checks on this title, as it is requested by
		// PageContentProvider from the server side, rather than by a user from a client.
		if ( $pdfStablePageId !== $title->getArticleID() ) {
			return $this->isStableVersion( $title );
		}
		if ( $this->context->getRequest()->getInt( 'stable', 0 ) === 1 ) {
			return true;
		}
		$oldId = $this->context->getRequest()->getInt( 'oldid', 0 )
			?: $this->context->getRequest()->getInt( 'stableid', 0 );
		if ( $oldId === 0 ) {
			return $this->isStableVersion( $title );
		}
		$direction = $this->context->getRequest()->getVal( 'direction', '' );
		if ( $direction === 'next' ) {
			$oldId = $title->getNextRevisionID( $oldId );
		} elseif ( $direction === 'prev' ) {
			$oldId = $title->getPreviousRevisionID( $oldId );
		}
		if ( !$oldId ) {
			return false;
		}

		if ( in_array( $oldId, $this->getStableVersions( $title ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	protected function isStableVersion( Title $title ) {
		$flaggableWikiPage = $this->getFlaggableWikiPage( $title );
		// Editing stable versions should be available to
		// all users with edit permission not only reviewers
		if ( $this->context->getRequest()->getVal( 'action', 'view' ) === 'edit'
			&& !$flaggableWikiPage->getPendingRevCount() ) {
			return true;
		}

		$view = $this->getFlaggablePageView( $title );
		$flaggedRev = $flaggableWikiPage->getStableRev();
		if ( $flaggedRev === null ) {
			return false;
		}
		return $view->showingStable() && $flaggedRev->getQuality() > 0;
	}

	/**
	 *
	 * @param Title $title
	 * @return FlaggableWikiPage
	 */
	protected function getFlaggableWikiPage( Title $title ) {
		if ( $this->flaggableWikiPage !== null ) {
			return $this->flaggableWikiPage;
		}
		$this->flaggableWikiPage = FlaggableWikiPage::getTitleInstance( $title );
		return $this->flaggableWikiPage;
	}

	/**
	 *
	 * @param Title $title
	 * @return FlaggablePageView
	 */
	protected function getFlaggablePageView( Title $title ) {
		$view = FlaggablePageView::singleton();
		// Provide proper object when called for multiple pages in one request
		// (e.g. book export)
		$view->clear();
		return $view;
	}

}
