<?php

namespace BlueSpice\FlaggedRevsConnector\Notifications;

use BlueSpice\FlaggedRevsConnector\Notifications\PresentationModel;

class Registrator {
	public static function registerNotifications( \BlueSpice\NotificationManager $notificationsManager ) {
		$notificationsManager->registerNotification(
			'bs-frc-pageassignments-page-review',
			[
				'category' => 'bs-pageassignments-action-cat',
				'presentation-model' => PresentationModel\PageReview::class
			]
		);
	}
}
