<?php

/**
 * FlaggedRevsConnector extension for BlueSpice
 *
 * Adds support for FlaggedRevs to a range of other extensions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * This file is part of BlueSpice MediaWiki
 * For further information visit https://bluespice.com
 *
 * @author     Robert Vogel <vogel@hallowelt.com>
 * @author     Patric Wirth
 * @package    BlueSpice_pro
 * @subpackage FlaggedRevsConnector
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */

use BlueSpice\Extension;
use MediaWiki\MediaWikiServices;

/**
 * Base class for FlaggedRevsConnector extension
 * @package BlueSpice_pro
 * @subpackage FlaggedRevsConnector
 */
// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class FlaggedRevsConnector extends Extension {
	public const STATE_NOT_ENABLED = 'notenabled';
	public const STATE_UNMARKED = 'unmarked';
	public const STATE_DRAFT = 'draft';
	public const STATE_STABLE = 'stable';
	public const STATE_IMPLICIT_DRAFT = 'implicit-draft';

	/**
	 * extension.json callback
	 */
	public static function onRegistration() {
		global $wgHooks;

		require_once __DIR__ . '/includes/Defines.php';

		if ( isset( $wgHooks['ArticleUpdateBeforeRedirect'] ) && is_array( $wgHooks['ArticleUpdateBeforeRedirect'] ) ) {
			$i = array_search(
				'FlaggedRevsHooksUI::injectPostEditURLParams',
				$wgHooks['ArticleUpdateBeforeRedirect']
			);
			// removes function for Preferences tab rendering from the $wgHooks array.
			unset( $wgHooks['ArticleUpdateBeforeRedirect'][$i] );
			unset( $i );
			// restores index consistency
			array_values( $wgHooks['ArticleUpdateBeforeRedirect'] );
		}
		$GLOBALS['wgExtensionFunctions'][] = static function () {
			if ( !isset( $GLOBALS['wgHooks']['CategoryPageView'] )
				|| !is_array( $GLOBALS['wgHooks']['CategoryPageView'] ) ) {
				return;
			}
			// remove links to now unlisted specialpages on category pages
			$i = array_search(
				'FlaggedRevsUIHooks::onCategoryPageView',
				$GLOBALS['wgHooks']['CategoryPageView']
			);
			if ( $i !== false ) {
				unset( $GLOBALS['wgHooks']['CategoryPageView'][$i] );
				unset( $i );
				// restores index consistency
				array_values( $GLOBALS['wgHooks']['CategoryPageView'] );
			}
		};

		// If not already set, assign reviewer role to reviewer group
		if ( !isset( $GLOBALS['bsgGroupRoles']['reviewer']['reviewer'] ) ) {
			$GLOBALS['bsgGroupRoles']['reviewer']['reviewer'] = true;
		}

		$GLOBALS['wgExtensionFunctions'][] = static function () {
			global
			$wgSimpleFlaggedRevsUI,
			$wgFlaggedRevsTags, $wgFlaggedRevValues,
			$wgFlaggedRevsComments, $wgFlaggedRevsLowProfile;

			$wgSimpleFlaggedRevsUI = false;
			$wgFlaggedRevsTags = [
				// We only have one tag with zero levels
				'accuracy' => [ 'levels' => 1, 'quality' => 1, 'pristine' => 2 ]
			];
			$wgFlaggedRevValues = 1;
			// As we have a own form now we save comments now without having the ugly textbox in the standard form
			$wgFlaggedRevsComments = true;
			// Displays box even on stables
			$wgFlaggedRevsLowProfile = false;
			// PW: TODO: Connect to templates/files-version-functionallity
			//$wgFlaggedRevsHandleIncludes = FR_INCLUDES_CURRENT; //Always use the current version of templates/files
			//Add "Draft" to Top-Menu

			global $wgHooks;

			// UniversalExport & Bookshelf
			$oFRCBookshelf = new FRCBookshelf();
			$oFRCUEModulePDF = new FRCUEModulePDF();
			$wgHooks['BSUEModulePDFBeforeAddingStyleBlocks'][] = [
				$oFRCUEModulePDF,
				'onBSUEModulePDFBeforeAddingStyleBlocks'
			];
			$wgHooks['BSUEModulePDFgetPage'][] = [ $oFRCUEModulePDF, 'onBSUEModulePDFgetPage' ];
			$wgHooks['BSUEModulePDFbeforeGetPage'][] = [ $oFRCUEModulePDF, 'onBSUEModulePDFbeforeGetPage' ];
			$wgHooks['BSBookshelfExportBeforeArticles'][] = [
				$oFRCBookshelf,
				'onBSBookshelfExportBeforeArticles'
			];

			// Hooks for SmartList (former InfoBox) (mode="flaggedrevisions")
			$oFRCInfobox = new FRCInfobox();
			$wgHooks['BSSmartListCustomMode'][] = [ $oFRCInfobox, 'onBSInfoBoxCustomMode' ];
			$wgHooks['BSSmartListBeforeEntryViewAddData'][] = [
				$oFRCInfobox,
				'onBSInfoBoxBeforeEntryViewAddData'
			];

			// Add Settings to the NamespaceManager
			$oFRCNamespaceManager = new FRCNamespaceManager();
			$wgHooks['NamespaceManager::getMetaFields'][] = [
				$oFRCNamespaceManager,
				'onGetMetaFields'
			];
			$wgHooks['BSApiNamespaceStoreMakeData'][] = [
				$oFRCNamespaceManager,
				'onGetNamespaceData'
			];
			$wgHooks['NamespaceManager::editNamespace'][] = [
				$oFRCNamespaceManager,
				'onEditNamespace'
			];
			$wgHooks['NamespaceManager::writeNamespaceConfiguration'][] = [
				$oFRCNamespaceManager,
				'onWriteNamespaceConfiguration'
			];

			// SuperList
			$oFRCSuperList = new FRCSuperList();
			$wgHooks['WikiExplorer::getFieldDefinitions'][] = [
				$oFRCSuperList,
				'onSuperListGetFieldDefinitions'
			];
			$wgHooks['WikiExplorer::getColumnDefinitions'][] = [
				$oFRCSuperList,
				'onSuperListGetColumnDefinitions'
			];
			$wgHooks['WikiExplorer::queryPagesWithFilter'][] = [
				$oFRCSuperList,
				'onSuperListQueryPagesWithFilter'
			];
			$wgHooks['WikiExplorer::buildDataSets'][] = [ $oFRCSuperList, 'onSuperListBuildDataSets' ];

			// PageAssignments
			$oFRCPageAssignments = new FRCPageAssignments();
			$GLOBALS['wgHooks']['BSApiExtJSStoreBaseBeforePostProcessData'][] = [
				$oFRCPageAssignments,
				'onBSApiExtJSStoreBaseBeforePostProcessData'
			];
			$GLOBALS['wgHooks']['BSPageAssignmentsOverview'][] = [
				$oFRCPageAssignments,
				'onBSPageAssignmentsOverview'
			];
			$GLOBALS['wgHooks']['APIAfterExecute'][] = [ $oFRCPageAssignments, 'onAPIAfterExecute' ];

			// SemanticMediaWiki
			$oFRCSemanticMediaWiki = new FRCSemanticMediaWiki();
			$GLOBALS['wgHooks']['APIAfterExecute'][] = [ $oFRCSemanticMediaWiki, 'onAPIAfterExecute' ];
		};
	}

	protected $mFlagInfo = [];

	/**
	 * Returns info about the flag state of the article
	 * // TODO RBV (25.07.12 14:55): Re-work logic to avoid multiple wfRunHooks
	 * @param Title $oCurrentTitle
	 * @return array
	 */
	public function collectFlagInfo( $oCurrentTitle ) {
		global $wgFlaggedRevsNamespaces, $wgOut;
		if ( isset( $this->mFlagInfo[ $oCurrentTitle->getArticleID() ] ) ) {
			return $this->mFlagInfo[ $oCurrentTitle->getArticleID() ];
		}

		$aFlagInfo = [
			'state' => 'notreviewable',
			'user-can-review' => false
		];

		$bResult = false;
		if ( !in_array( $oCurrentTitle->getNamespace(), $wgFlaggedRevsNamespaces )
			|| FRCReview::onCheckPageIsReviewable( $oCurrentTitle, $bResult ) === false ) {
			MediaWikiServices::getInstance()->getHookContainer()->run(
				'BSFlaggedRevsConnectorCollectFlagInfo',
				[
					$oCurrentTitle,
					&$aFlagInfo
				]
			);
			$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
			return $aFlagInfo;
		}

		$userCan = MediaWikiServices::getInstance()->getPermissionManager()->userCan(
			'review',
			RequestContext::getMain()->getUser(),
			$oCurrentTitle
		);
		if ( $userCan ) {
			$aFlagInfo[ 'user-can-review' ] = true;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->selectRow(
			'flaggedpages',
			'*',
			[ 'fp_page_id' => $oCurrentTitle->getArticleID() ],
			__METHOD__
		);

		if ( $res === false ) {
			$aFlagInfo[ 'state' ] = 'unmarked';
			MediaWikiServices::getInstance()->getHookContainer()->run(
				'BSFlaggedRevsConnectorCollectFlagInfo',
				[
					$oCurrentTitle,
					&$aFlagInfo
				]
			);
			$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
			return $aFlagInfo;
		}

		if ( $res->fp_stable == $wgOut->getRevisionId() ) {
			$aFlagInfo[ 'state' ] = 'stable';
			$aFlagInfo[ 'user-can-review' ] = false;

			// case include has a newer version than the version which was reviewed
			global $wgRequest, $wgFlaggedRevsHandleIncludes;
			if ( $wgFlaggedRevsHandleIncludes != FR_INCLUDES_CURRENT ) {
				if ( $wgRequest->getVal( 'stable', 1 ) == 0 || $wgRequest->getVal( 'oldid' )
					&& $wgRequest->getVal( 'diff', 'cur' ) ) {
					$aFlagInfo[ 'state' ] = 'draft';
					if ( $userCan ) {
						$aFlagInfo[ 'user-can-review' ] = true;
					}
				}
			}

			MediaWikiServices::getInstance()->getHookContainer()->run(
				'BSFlaggedRevsConnectorCollectFlagInfo',
				[
					$oCurrentTitle,
					&$aFlagInfo
				]
			);
			$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
			return $aFlagInfo;
		}

		$aFlagInfo[ 'state' ] = 'draft';

		MediaWikiServices::getInstance()->getHookContainer()->run(
			'BSFlaggedRevsConnectorCollectFlagInfo',
			[
				$oCurrentTitle,
				&$aFlagInfo
			]
		);
		$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
		return $aFlagInfo;
	}

}
