<?php

namespace BlueSpice\FlaggedRevsConnector\Notifications;

use BlueSpice\NotificationManager;
use ExtensionRegistry;

class Registrator {
	/**
	 *
	 * @param NotificationManager $notificationsManager
	 * @return void
	 */
	public static function registerNotifications( NotificationManager $notificationsManager ) {
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
