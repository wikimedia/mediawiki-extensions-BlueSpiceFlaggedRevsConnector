<?php

namespace BlueSpice\FlaggedRevsConnector;

use BlueSpice\ExtensionFactory;
use BlueSpice\SMWConnector\PropertyValueProvider;
use FlaggedRevs;
use MediaWiki\MediaWikiServices;
use Message;
use SMWDataItem;
use SMWDIBlob;
use Wikimedia\Rdbms\IDatabase;

class DocumentStatePropertyValueProvider extends PropertyValueProvider {

	/**
	 * @return \BlueSpice\SMWConnector\IPropertyValueProvider[]
	 */
	public static function factory() {
		$services = MediaWikiServices::getInstance();
		$extensionFactory = $services->getService( 'BSExtensionFactory' );
		$database = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$propertyValueProvider = new static( $extensionFactory, $database );

		return [ $propertyValueProvider ];
	}

	/**
	 *
	 * @var ExtensionFactory
	 */
	protected $extensionFactory = null;

	/**
	 *
	 * @var IDatabase
	 */
	protected $db = null;

	/**
	 * @param ExtensionFactory $extensionFactory
	 * @param IDatabase $database
	 */
	public function __construct( $extensionFactory, $database ) {
		$this->extensionFactory = $extensionFactory;
		$this->db = $database;
	}

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-flaggedrevsconnector-document-state-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-flaggedrevsconnector-document-state-sesp-desc";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_BLOB;
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_FRCDOCSTATE';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "QM/Document state";
	}

	/**
	 *
	 * @var int
	 */
	private $pageId = -1;

	/**
	 *
	 * @var int
	 */
	private $latestRevisionId = -1;

	/**
	 *
	 * @var int
	 */
	private $stableRevId = -1;

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 * @return null
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$title = $semanticData->getSubject()->getTitle();

		if ( $title === null ) {
			return;
		}
		if ( !FlaggedRevs::inReviewNamespace( $title ) ) {
			return;
		}

		$this->pageId = $title->getArticleID();
		$this->latestRevisionId = $title->getLatestRevID();

		$this->loadStableRevId();

		$value = $this->makeValue( 'unapproved' );
		if ( $this->latestRevisionIsApproved() ) {
			$value = $this->makeValue( 'approved' );
		} elseif ( $this->someRevisionIsApproved() ) {
			$value = $this->makeValue( 'in-revision' );

			// This is ugly, but we need to do this somewhere... And better here than in Review extension
			if ( $this->reviewWorkflowInProgess() ) {
				$value = $this->makeValue( 'approval-requested' );
			}
		}

		$semanticData->addPropertyObjectValue(
			$property, new SMWDIBlob( $value )
		);
	}

	private function loadStableRevId() {
		$value = $this->db->selectField(
			'flaggedpages',
			'fp_stable',
			[
				'fp_page_id' => $this->pageId
			],
			__METHOD__
		);

		$this->stableRevId = $value === false ? -1 : (int)$value;
	}

	private function latestRevisionIsApproved() {
		return $this->stableRevId === $this->latestRevisionId;
	}

	private function someRevisionIsApproved() {
		$revIds = [];
		$res = $this->db->select(
			'revision',
			'rev_id',
			[
				'rev_page' => $this->pageId
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$revIds[] = $row->rev_id;
		}

		return in_array( $this->stableRevId, $revIds );
	}

	private function reviewWorkflowInProgess() {
		$reviewExt = $this->extensionFactory->getExtension( 'BlueSpiceReview' );
		if ( $reviewExt === null ) {
			return false;
		}

		$reviewProcess = \BsReviewProcess::newFromPid( $this->pageId );
		if ( $reviewProcess instanceof \BsReviewProcess === false ) {
			return false;
		}
		if ( $reviewProcess->getStatus( time() ) === 'approved' ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $i18nKeySuffix
	 * @return string
	 */
	private function makeValue( $i18nKeySuffix ) {
		// Give `grep` a chance
		// bs-flaggedrevsconnector-smw-prop-document-state-unapproved
		// bs-flaggedrevsconnector-smw-prop-document-state-approved
		// bs-flaggedrevsconnector-smw-prop-document-state-in-revision
		// bs-flaggedrevsconnector-smw-prop-document-state-approval-requested
		$i18nKey = "bs-flaggedrevsconnector-smw-prop-document-state-$i18nKeySuffix";
		$message = Message::newFromKey( $i18nKey );
		$value = $message->inContentLanguage()->plain();

		return $value;
	}
}
