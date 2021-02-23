<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use BlueSpice\FlaggedRevsConnector\Data\Record;
use FlaggedRevsConnector;

class PrimaryDataProvider implements \BlueSpice\Data\IPrimaryDataProvider {

	/**
	 *
	 * @var \BlueSpice\Data\Record[]
	 */
	protected $data = [];

	/**
	 *
	 * @var \Wikimedia\Rdbms\IDatabase
	 */
	protected $db = null;

	/**
	 *
	 * @var \IContextSource
	 */
	protected $context = null;

	/**
	 *
	 * @param \Wikimedia\Rdbms\IDatabase $db
	 */
	public function __construct( $db, $context ) {
		$this->db = $db;
		$this->context = $context;
	}

	/**
	 *
	 * @param \BlueSpice\Data\ReaderParams $params
	 */
	public function makeData( $params ) {
		$this->data = [];

		$enabledNamespaces = $this->context->getConfig()->get( 'FlaggedRevsNamespaces' );
		$conds = [];
		foreach( $enabledNamespaces as $ns ) {
			$conds[] = "page_namespace = $ns";
		}

		$res = $this->db->select(
			'page',
			'*',
			implode( ' OR ', $conds ),
			__METHOD__
		);

		foreach( $res as $row ) {
			$title = \Title::newFromRow( $row );

			$flaggablePage = \FlaggableWikiPage::getTitleInstance(
				$title
			);

			$state = FlaggedRevsConnector::STATE_DRAFT;
			$stableRevID = $flaggablePage->getStable();
			if ( !$stableRevID ) {
				$state = FlaggedRevsConnector::STATE_UNMARKED;
			}
			if ( $stableRevID === $title->getLatestRevID() ) {
				$state = FlaggedRevsConnector::STATE_STABLE;
			}
			$stateMessage = wfMessage("bs-flaggedrevsconnector-state-$state" );

			$this->appendRowToData( new Record( (object) [
				Record::PAGE_ID => $title->getArticleID(),
				Record::PAGE_TITLE => $title->getPrefixedText(),
				Record::REVISION_STATE => $stateMessage->plain(),
				Record::REVISIONS_SINCE_STABLE => $flaggablePage->getPendingRevCount()
			] ) );
		}

		return $this->data;
	}

	protected function appendRowToData( Record $record ) {
		$this->data[] = $record;
	}
}
