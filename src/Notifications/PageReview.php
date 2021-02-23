<?php

namespace BlueSpice\FlaggedRevsConnector\Notifications;

use BlueSpice\BaseNotification;

class PageReview extends BaseNotification {
	protected $target;

	public function __construct( $agent, $title, $target ) {
		parent::__construct( 'bs-frc-pageassignments-page-review', $agent, $title );

		$this->target = $target;
		$this->addAffectedUsersFromTarget();
		$this->extra['assignment-sources'] = $target->getAssignedUserIDs();
	}

	public function getParams() {
		return [
			'titlelink' => true
		];
	}

	protected function addAffectedUsersFromTarget() {
		$affectedUsers = [];
		foreach( $this->target->getAssignedUserIDs() as $userId ) {
			$affectedUsers[] = $userId;
		}

		$this->addAffectedUsers( $affectedUsers );
	}
}
