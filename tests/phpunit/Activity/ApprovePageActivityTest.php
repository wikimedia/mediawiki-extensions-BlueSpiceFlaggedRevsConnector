<?php

namespace BlueSpice\FlaggedRevsConnector\Tests\Activity;

use BlueSpice\FlaggedRevsConnector\Utils;
use BlueSpice\FlaggedRevsConnector\Workflows\Activity\ApprovePageActivity;
use MediaWiki\MediaWikiServices;
use MediaWiki\Workflows\Activity\ExecutionStatus;
use MediaWiki\Workflows\Definition\DefinitionContext;
use MediaWiki\Workflows\Definition\Element\Task;
use MediaWiki\Workflows\ISpecialLogLogger;
use MediaWiki\Workflows\WorkflowContext;
use MediaWikiIntegrationTestCase;
use Title;

/**
 * @covers \BlueSpice\FlaggedRevsConnector\Workflows\Activity\ApprovePageActivity
 * @group Database
 * @group Broken
 */
class ApprovePageActivityTest extends MediaWikiIntegrationTestCase {
	/** @var Title */
	private $title;
	/** @var Utils|\PHPUnit\Framework\MockObject\MockObject */
	private $utils;

	protected function setUp(): void {
		parent::setUp();

		$res = $this->insertPage( 'DummyApprovalTest' );
		$this->title = $res['title'];

		// Broken: this does not actually set the global
		$this->setMwGlobals( [
			'wgFlaggedRevsNamespaces' => [ NS_MAIN ]
		] );
		$this->utils = $this->createMock( Utils::class );
	}

	/**
	 *
	 * @covers \BlueSpice\FlaggedRevsConnector\Workflows\Activity\ApprovePageActivity::execute
	 *
	 */
	public function testExecute() {
		$context = new WorkflowContext(
			new DefinitionContext( [
				'pageId' => $this->title->getArticleID(),
				'revision' => $this->title->getLatestRevID()
			] )
		);
		$task = new Task( 'Approve1', 'Approve page', [], [], 'automaticTask' );
		$services = MediaWikiServices::getInstance();
		$spclLogLoggerMock = $this->createMock( ISpecialLogLogger::class );
		$spclLogLoggerMock->expects( $this->once() )->method( 'addEntry' );
		$activity = new ApprovePageActivity(
			$services->getService( 'BSFlaggedRevsConnectorUtils' ),
			$services->getRevisionStore(),
			$services->getService( 'BSUtilityFactory' ),
			$task,
			$spclLogLoggerMock
		);

		$this->assertNotEquals( $this->title->getLatestRevID(), $this->utils->getApprovedRevisionId( $this->title ) );
		$status = $activity->execute( [
			'comment' => 'Dummy comment',
		], $context );

		$this->assertInstanceOf(
			ExecutionStatus::class, $status, 'Activity should return an ExecutionStatus'
		);
		// Broken: Utils fail to retrieve latest stable version
		$this->assertEquals( $this->title->getLatestRevID(), $this->utils->getApprovedRevisionId( $this->title ) );
	}

	public function needsDB() {
		return true;
	}
}
