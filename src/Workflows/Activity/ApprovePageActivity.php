<?php

namespace BlueSpice\FlaggedRevsConnector\Workflows\Activity;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\SecondaryDataUpdater;
use BlueSpice\UtilityFactory;
use Exception;
use MediaWiki\Extension\Workflows\Activity\ExecutionStatus;
use MediaWiki\Extension\Workflows\Activity\GenericActivity;
use MediaWiki\Extension\Workflows\Definition\ITask;
use MediaWiki\Extension\Workflows\Exception\WorkflowExecutionException;
use MediaWiki\Extension\Workflows\WorkflowContext;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
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
	/** @var UserFactory */
	private $userFactory;
	/** @var Title */
	private $title;
	/** @var RevisionRecord */
	private $revision;
	/** @var User */
	private $maintenanceUser;
	/** @var User */
	private $user;
	/** @var SecondaryDataUpdater */
	private $dataUpdater;

	/**
	 *
	 * @param Utils $utils
	 * @param RevisionStore $revisionStore
	 * @param UtilityFactory $utilityFactory
	 * @param UserFactory $userFactory
	 * @param SecondaryDataUpdater $dataUpdater
	 * @param ITask $task
	 *
	 * @throws \MWException
	 */
	public function __construct(
		Utils $utils, RevisionStore $revisionStore,
		UtilityFactory $utilityFactory, UserFactory $userFactory,
		SecondaryDataUpdater $dataUpdater, ITask $task
	) {
		parent::__construct( $task );
		$this->util = $utils;
		$this->revisionStore = $revisionStore;
		$this->userFactory = $userFactory;
		$this->maintenanceUser = $utilityFactory->getMaintenanceUser()->getUser();
		$this->dataUpdater = $dataUpdater;
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
		if ( !$context->getDefinitionContext()->getItem( 'pageId' ) ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'bs-flaggedrevsconnector-wfactivity-error-context-data-missing'
				)->text(), $this->getTask()
			);
		}

		$title = Title::newFromID( $context->getDefinitionContext()->getItem( 'pageId' ) );
		$revisionId = $data['revision'] ?? $context->getDefinitionContext()->getItem( 'revision' );
		if ( !$revisionId ) {
			$revisionId = $title->getLatestRevID();
		}
		if ( !$title instanceof Title || !$title->exists() ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'bs-flaggedrevsconnector-wfactivity-error-context-invalid-title'
				)->text(), $this->getTask()
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
				)->text(), $this->getTask()
			);
		}
		$this->revision = $revision;

		if ( isset( $data['user'] ) ) {
			// If user is explicitly set, use that. Definition is responsible
			// to make sure this user can approve pages (use propertyValidator)
			$this->user = $this->userFactory->newFromName( $data['user'] );
			if ( !( $this->user instanceof User ) || !$this->user->isRegistered() ) {
				throw new WorkflowExecutionException(
					Message::newFromKey(
						'bs-flaggedrevsconnector-wfactivity-error-provided-user', $data['user']
					)->text(), $this->getTask()
				);
			}
		} elseif ( $context->isRunningAsBot() ) {
			// If we are running a workflow as a bot (no user interaction), use maintenance user
			$this->user = $this->maintenanceUser;
		}

		if ( !( $this->user instanceof User ) ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'bs-flaggedrevsconnector-wfactivity-error-no-user'
				)->text(), $this->getTask()
			);
		}
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
			$this->title->invalidateCache();
			$this->dataUpdater->run( $this->title );
		} catch ( Exception $ex ) {
			throw new WorkflowExecutionException( $ex->getMessage(), $this->task );
		}
	}
}
