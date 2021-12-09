<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\ImgAuthBeforeStream;

use BlueSpice\Hook\ImgAuthBeforeStream;
use DateTime;
use DateTimeZone;
use File;
use MediaWiki\MediaWikiServices;
use Title;

class LockdownDraft extends ImgAuthBeforeStream {
	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		// make new file title, as this may got modified by NSFileRepo i.e.
		$title = Title::makeTitleSafe( NS_FILE, $this->name );
		if ( !$title || $title->getNamespace() !== NS_FILE ) {
			return true;
		}
		if ( !in_array( $title->getNamespace(), $this->getNamespaceWhitelist() ) ) {
			return true;
		}
		$services = MediaWikiServices::getInstance();
		$groupInters = array_intersect(
			$this->getGroupWhitelist(),
			$services->getUserGroupManager()->getUserEffectiveGroups(
				$this->getContext()->getUser(),
				UserGroupManager::READ_NORMAL,
				true
			)
		);

		if ( count( $groupInters ) > 0 ) {
			return true;
		}

		$repo = $services->getRepoGroup()->getRepo( 'local' );
		$bits = explode( '!', $this->name, 2 );
		$archive = substr( $this->path, 0, 9 ) === '/archive/'
			|| substr( $this->path, 0, 15 ) === '/thumb/archive/';

		if ( $archive && count( $bits ) == 2 ) {
			$file = $repo->newFromArchiveName( $bits[1], $this->name );
		} else {
			$file = $repo->newFile( $this->name );
		}
		if ( !$file ) {
			return true;
		}
		$file->load();
		if ( !$file->getTimestamp() ) {
			return true;
		}

		if ( !$this->isDraft( $file ) ) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * @param File $file
	 * @return bool
	 */
	protected function isDraft( File $file ) {
		$res = $this->getServices()->getDBLoadBalancer()->getConnection( DB_REPLICA )->selectField(
			'flaggedrevs',
			'MAX(fr_img_timestamp)',
			[
				'fr_page_id' => $file->getTitle()->getArticleId(),
				// Show all with some degree of stability
				'fr_quality > 0'
			],
			__METHOD__
		);
		if ( !$res ) {
			// could be first draft, but we do not lock down first draft!
			false;
		}
		$flagDate = DateTime::createFromFormat(
			'YmdHis',
			$res,
			new DateTimeZone( 'UTC' )
		);
		$reqTimestamp = DateTime::createFromFormat(
			'YmdHis',
			$file->getTimestamp(),
			new DateTimeZone( 'UTC' )
		);

		return $reqTimestamp > $flagDate;
	}

	/**
	 *
	 * @return bool
	 */
	protected function doProcess() {
		$this->result = [ 'img-auth-accessdenied', 'img-auth-badtitle', $this->name ];
		return false;
	}

	/**
	 *
	 * @return int[]
	 */
	private function getNamespaceWhitelist() {
		return $this->getConfig()->has( 'FlaggedRevsNamespaces' )
			? $this->getConfig()->get( 'FlaggedRevsNamespaces' )
			: $GLOBALS['wgFlaggedRevsNamespaces'];
	}

	/**
	 *
	 * @return string[]
	 */
	private function getGroupWhitelist() {
		$allowedGroups = array_merge(
			$this->getConfig()->get( 'FlaggedRevsConnectorDraftGroups' ),
			// always allowed because of reasons...
			[ 'sysop', 'reviewer' ]
		);
		return array_unique( $allowedGroups );
	}

}
