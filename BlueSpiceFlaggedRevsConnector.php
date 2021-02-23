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

/**
 * Base class for FlaggedRevsConnector extension
 * @package BlueSpice_pro
 * @subpackage FlaggedRevsConnector
 */
class FlaggedRevsConnector extends Extension {
	const STATE_NOT_ENABLED = 'notenabled';
	const STATE_UNMARKED = 'unmarked';
	const STATE_DRAFT = 'draft';
	const STATE_STABLE = 'stable';

	/**
	 * extension.json callback
	 */
	public static function onRegistration() {
		global $wgHooks;

		if( isset($wgHooks['ArticleUpdateBeforeRedirect']) && is_array($wgHooks['ArticleUpdateBeforeRedirect']) ) {
			$i = array_search('FlaggedRevsHooksUI::injectPostEditURLParams', $wgHooks['ArticleUpdateBeforeRedirect']);
			unset( $wgHooks['ArticleUpdateBeforeRedirect'][$i] ); //removes function for Preferences tab rendering from the $wgHooks array.
			unset( $i );
			array_values( $wgHooks['ArticleUpdateBeforeRedirect'] ); //restores index consistency
		}

		$GLOBALS['wgExtensionFunctions'][] = function() {
			global
			$wgSimpleFlaggedRevsUI,
			$wgFlaggedRevsTags, $wgFlaggedRevValues,
			$wgFlaggedRevsComments, $wgFlaggedRevsLowProfile;

			$wgSimpleFlaggedRevsUI = false;
			$wgFlaggedRevsTags = [
				'accuracy' => [ 'levels' => 1, 'quality' => 1, 'pristine' => 2 ] //We only have one tag with zero levels
			];
			$wgFlaggedRevValues = 1;
			$wgFlaggedRevsComments = true;  //As we have a own form now we save comments now without having the ugly textbox in the standard form
			$wgFlaggedRevsLowProfile = false; //Displays box even on stables
			//PW: TODO: Connect to templates/files-version-functionallity
			//$wgFlaggedRevsHandleIncludes = FR_INCLUDES_CURRENT; //Always use the current version of templates/files
			//Add "Draft" to Top-Menu


			global $wgHooks;

			//UniversalExport & Bookshelf
			$oFRCBookshelf = new FRCBookshelf();
			$oFRCUEModulePDF = new FRCUEModulePDF();
			$wgHooks['BSUEModulePDFBeforeAddingStyleBlocks'][] = [ $oFRCUEModulePDF, 'onBSUEModulePDFBeforeAddingStyleBlocks' ];
			$wgHooks['BSUEModulePDFgetPage'][] = [ $oFRCUEModulePDF, 'onBSUEModulePDFgetPage' ];
			$wgHooks['BSUEModulePDFbeforeGetPage'][] = [ $oFRCUEModulePDF, 'onBSUEModulePDFbeforeGetPage' ];
			$wgHooks['BSBookshelfExportBeforeArticles'][] = [ $oFRCBookshelf, 'onBSBookshelfExportBeforeArticles' ];

			// Hooks for SmartList (former InfoBox) (mode="flaggedrevisions")
			$oFRCInfobox = new FRCInfobox();
			$wgHooks['BSSmartListCustomMode'][] = [ $oFRCInfobox, 'onBSInfoBoxCustomMode' ];
			$wgHooks['BSSmartListBeforeEntryViewAddData'][] = [ $oFRCInfobox, 'onBSInfoBoxBeforeEntryViewAddData' ];

			// Add Settings to the NamespaceManager
			$oFRCNamespaceManager = new FRCNamespaceManager();
			$wgHooks['NamespaceManager::getMetaFields'][] = [ $oFRCNamespaceManager, 'onGetMetaFields' ];
			$wgHooks['BSApiNamespaceStoreMakeData'][] = [ $oFRCNamespaceManager, 'onGetNamespaceData' ];
			$wgHooks['NamespaceManager::editNamespace'][] = [$oFRCNamespaceManager, 'onEditNamespace'];
			$wgHooks['NamespaceManager::writeNamespaceConfiguration'][] = [ $oFRCNamespaceManager, 'onWriteNamespaceConfiguration' ];

			//SuperList
			$oFRCSuperList = new FRCSuperList();
			$wgHooks['WikiExplorer::getFieldDefinitions'][] = [ $oFRCSuperList, 'onSuperListGetFieldDefinitions' ];
			$wgHooks['WikiExplorer::getColumnDefinitions'][] = [ $oFRCSuperList, 'onSuperListGetColumnDefinitions' ];
			$wgHooks['WikiExplorer::queryPagesWithFilter'][] = [ $oFRCSuperList, 'onSuperListQueryPagesWithFilter' ];
			$wgHooks['WikiExplorer::buildDataSets'][] = [ $oFRCSuperList, 'onSuperListBuildDataSets' ];

			//PageAssignments
			$oFRCPageAssignments = new FRCPageAssignments();
			$GLOBALS['wgHooks']['BSApiExtJSStoreBaseBeforePostProcessData'][] = [ $oFRCPageAssignments, 'onBSApiExtJSStoreBaseBeforePostProcessData' ];
			$GLOBALS['wgHooks']['BSPageAssignmentsOverview'][] = [ $oFRCPageAssignments, 'onBSPageAssignmentsOverview' ];
			$GLOBALS['wgHooks']['APIAfterExecute'][] = [ $oFRCPageAssignments, 'onAPIAfterExecute' ];

			//SemanticMediaWiki
			$oFRCSemanticMediaWiki = new FRCSemanticMediaWiki();
			$GLOBALS['wgHooks']['APIAfterExecute'][] = [ $oFRCSemanticMediaWiki, 'onAPIAfterExecute' ];
		};
	}

	public static function setupFlaggedRevsConnector(){
		global $wgHooks;
		$wgHooks['SkinTemplateNavigation'][] = "FlaggedRevsConnector::onSkinTemplateNavigation";
	}

	protected $mFlagInfo = array( );

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

		$aFlagInfo = array(
			'state' => 'notreviewable',
			'user-can-review' => false
		);

		$bResult = false;
		if ( !in_array( $oCurrentTitle->getNamespace(), $wgFlaggedRevsNamespaces )
			|| FRCReview::onCheckPageIsReviewable( $oCurrentTitle, $bResult ) === false ) {
			\Hooks::run('BSFlaggedRevsConnectorCollectFlagInfo', array( $oCurrentTitle, &$aFlagInfo ) );
			$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
			return $aFlagInfo;
		}

		if ( $oCurrentTitle->userCan( 'review' ) )
			$aFlagInfo[ 'user-can-review' ] = true;

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->selectRow(
			'flaggedpages',
			'*',
			[ 'fp_page_id' => $oCurrentTitle->getArticleID() ],
			__METHOD__
		);

		if ( $res === false ) {
			$aFlagInfo[ 'state' ] = 'unmarked';
			\Hooks::run('BSFlaggedRevsConnectorCollectFlagInfo', array( $oCurrentTitle, &$aFlagInfo ) );
			$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
			return $aFlagInfo;
		}

		if ( $res->fp_stable == $wgOut->getRevisionId() ) {
			$aFlagInfo[ 'state' ] = 'stable';
			$aFlagInfo[ 'user-can-review' ] = false;

			//case include has a newer version than the version which was reviewed
			global $wgRequest, $wgFlaggedRevsHandleIncludes;
			if ( $wgFlaggedRevsHandleIncludes != FR_INCLUDES_CURRENT )
				if ( $wgRequest->getVal( 'stable', 1 ) == 0 || $wgRequest->getVal( 'oldid' ) && $wgRequest->getVal( 'diff', 'cur' ) ) {
					$aFlagInfo[ 'state' ] = 'draft';
					if ( $oCurrentTitle->userCan( 'review' ) )
						$aFlagInfo[ 'user-can-review' ] = true;
				}

			\Hooks::run('BSFlaggedRevsConnectorCollectFlagInfo', array( $oCurrentTitle, &$aFlagInfo ) );
			$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
			return $aFlagInfo;
		}

		$aFlagInfo[ 'state' ] = 'draft';

		\Hooks::run('BSFlaggedRevsConnectorCollectFlagInfo', array( $oCurrentTitle, &$aFlagInfo ) );
		$this->mFlagInfo[ $oCurrentTitle->getArticleID() ] = $aFlagInfo;
		return $aFlagInfo;
	}

	function onReorderActionTabs( &$aContentActions, &$aActionsNotInMoreMenu ) {
		if ( $aContentActions[ 'current' ] ) {
			$aContentActions[ 'current' ][ 'text' ] = wfMessage( 'bs-flaggedrevsconnector-state-draft' )->plain();
			$aActionsNotInMoreMenu[ ] = 'current';
		}
		return true;
	}

	public static function onSkinTemplateNavigation( Skin $skin, array &$links ) {
		if (isset($links['views']['current'])){
			$links['namespaces']['current'] = $links['views']['current'];
			$links['namespaces']['current']['text'] = wfMessage("bs-flaggedrevsconnector-state-draft")->plain();
			unset($links['views']['current']);
		}
		return true;
	}
}
