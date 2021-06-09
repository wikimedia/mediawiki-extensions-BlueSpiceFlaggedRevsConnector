<?php

namespace BlueSpice\FlaggedRevsConnector;

use Config;
use Exception;
use FlaggablePageView;
use FlaggableWikiPage;
use FlaggedRevs;
use FRInclusionCache;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use RevisionReviewForm;
use Title;
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
		$currentUserGroups = MediaWikiServices::getInstance()
			->getUserGroupManager()
			->getEffectiveGroups( $user );
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

		$frc = MediaWikiServices::getInstance()->getService( 'BSExtensionFactory' )->getExtension(
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
	 * @return int Stable revision id or `-1` if "first draft"
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
			return -1;
		}

		return (int)$row->fp_stable;
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @param RevisionRecord $revision
	 * @param string $comment
	 * @return bool
	 * @throws Exception
	 */
	public function approveRevision(
		User $user, Title $title, RevisionRecord $revision, $comment = ''
	) {
		// Construct submit form...
		$form = new PermissionLessReviewForm( $user );
		$form->setPage( $title );
		$form->setOldId( $revision->getId() );
		$form->setApprove( true );
		$form->setUnapprove( false );
		$form->setComment( $comment );
		// The flagging parameters have the form 'flag_$name'.
		// Extract them and put the values into $form->dims
		foreach ( FlaggedRevs::getTags() as $tag ) {
			if ( FlaggedRevs::binaryFlagging() ) {
				$form->setDim( $tag, 1 );
			}
		}

		$article = new FlaggableWikiPage( $title );
		// Get the file version used for File: pages
		$file = $article->getFile();
		if ( $file ) {
			$fileVer = [ 'time' => $file->getTimestamp(), 'sha1' => $file->getSha1() ];
		} else {
			$fileVer = null;
		}
		// Now get the template and image parameters needed
		list( $templateIds, $fileTimeKeys ) =
			FRInclusionCache::getRevIncludes( $article, $revision, $user );
		// Get version parameters for review submission (flat strings)
		list( $templateParams, $imageParams, $fileParam ) =
			RevisionReviewForm::getIncludeParams( $templateIds, $fileTimeKeys, $fileVer );
		// Set the version parameters...
		$form->setTemplateParams( $templateParams );
		$form->setFileParams( $imageParams );
		$form->setFileVersion( $fileParam );
		// always OK; uses current templates/files
		$form->bypassValidationKey();

		// all params set
		$form->ready();

		# Try to do the actual review
		return $form->submit();
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
