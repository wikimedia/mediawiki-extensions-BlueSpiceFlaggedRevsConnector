<?php

namespace BlueSpice\FlaggedRevsConnector\Notifications;

use BlueSpice\FlaggedRevsConnector\Notifications\PresentationModel;
use ExtensionRegistry;

class Registrator {
	public static function registerNotifications( \BlueSpice\NotificationManager $notificationsManager ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpicePageAssignments' ) ) {
			return;
		}
		$notificationsManager->registerNotification(
			'bs-frc-pageassignments-page-review',
			[
				'category' => 'bs-pageassignments-action-cat',
				'presentation-model' => PresentationModel\PageReview::class
			]
		);
	}
}
