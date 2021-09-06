<?php

namespace BlueSpice\FlaggedRevsConnector\Workflows\Activity;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\UtilityFactory;
use Exception;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Workflows\Activity\ExecutionStatus;
use MediaWiki\Workflows\Activity\GenericActivity;
use MediaWiki\Workflows\Definition\ITask;
use MediaWiki\Workflows\Exception\WorkflowExecutionException;
use MediaWiki\Workflows\ISpecialLogLogger;
use MediaWiki\Workflows\WorkflowContext;
use Message;
use MWTimestamp;
use Title;
use User;

/**
 * Required data:
 * - WorkflowDefinitionContext: pageId, revision
 * - Properties: comment (optional)
 * Outputs:
 * - timestamp
 */
class ApprovePageActivity extends GenericActivity {
	/** @var Utils */
	private $util;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var Title */
	private $title;
	/** @var RevisionRecord */
	private $revision;
	/** @var User */
	private $user;

	/**
	 *
	 * @param Utils $utils
	 * @param RevisionStore $revisionStore
	 * @param UtilityFactory $utilityFactory
	 * @param ITask $task
	 * @param ISpecialLogLogger $logger
	 */
	public function __construct(
		Utils $utils, RevisionStore $revisionStore,
		UtilityFactory $utilityFactory, ITask $task, ISpecialLogLogger $logger
	) {
		parent::__construct( $task, $logger );
		$this->util = $utils;
		$this->revisionStore = $revisionStore;
		$this->user = $utilityFactory->getMaintenanceUser()->getUser();
	}

	/**
	 * @param array $data
	 * @param WorkflowContext $context
	 * @return ExecutionStatus
	 * @throws WorkflowExecutionException
	 */
	public function execute( $data, WorkflowContext $context ): ExecutionStatus {
		$this->setPageData( $context, $data );
		$this->assertApprovable();
		$comment = $data['comment'] ?? '';

		$this->doApprove( $comment );
		return new ExecutionStatus( static::STATUS_COMPLETE, [ 'timestamp' => MWTimestamp::now( TS_MW ) ] );
	}

	/**
	 *
	 * @param WorkflowContext $context
	 * @param array $data
	 * @return void
	 * @throws WorkflowExecutionException
	 */
	private function setPageData( WorkflowContext $context, $data ) {
		$revisionId = $data['revision'] ?? $context->getDefinitionContext()->getItem( 'revision' );
		if (
			!$context->getDefinitionContext()->getItem( 'pageId' ) ||
			!$revisionId
		) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'bs-flaggedrevsconnector-wfactivity-error-context-data-missing'
				)->text(),  $this->getTask()
			);
		}

		$title = Title::newFromID( $context->getDefinitionContext()->getItem( 'pageId' ) );
		if ( !$title instanceof Title || !$title->exists() ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'bs-flaggedrevsconnector-wfactivity-error-context-invalid-title'
				)->text(),  $this->getTask()
			);
		}
		$this->title = $title;

		$revision = $this->revisionStore->getRevisionById( (int)$revisionId );
		if ( $revision === null ) {
			$revision = $this->revisionStore->getRevisionById( $title->getLatestRevID() );
		}
		if ( $revision->getPageId() !== $title->getArticleID() ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'bs-flaggedrevsconnector-wfactivity-error-title-rev-mismatch'
				)->text(),  $this->getTask()
			);
		}
		$this->revision = $revision;
	}

	/**
	 *
	 * @return bool
	 */
	private function assertApprovable() {
		return $this->util->isFlaggableNamespace( $this->title );
	}

	/**
	 *
	 * @return ExecutionStatus|null
	 */
	public function probe(): ?ExecutionStatus {
		return new ExecutionStatus( static::STATUS_COMPLETE );
	}

	/**
	 *
	 * @param string $comment
	 * @return void
	 */
	private function doApprove( string $comment ) {
		try {
			$status = $this->util->approveRevision(
				$this->user,
				$this->title,
				$this->revision,
				$comment
			);
			if ( $status !== true ) {
				throw new Exception( Message::newFromKey(
					'bs-flaggedrevsconnector-wfactivity-error-cannot-approve'
				)->text() );
			}
		} catch ( Exception $ex ) {
			throw new WorkflowExecutionException( $ex->getMessage(), $this->task );
		}
	}
}