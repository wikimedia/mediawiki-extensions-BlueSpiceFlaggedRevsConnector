<?php

namespace BlueSpice\FlaggedRevsConnector\Notifications\PresentationModel;

use BlueSpice\EchoConnector\EchoEventPresentationModel;

class PageReview extends EchoEventPresentationModel {
	/**
	 * Gets appropriate messages keys and params
	 * for header message
	 *
	 * @return array
	 */
	public function getHeaderMessageContent() {
		$bundleKey = '';
		$bundleParams = [];

		$headerKey = 'bs-flaggedrevsconnector-notification-pageassignments-pagereview-summary';
		$headerParams = ['agent', 'title', 'title'];

		if( $this->distributionType == 'email' ) {
			$headerKey = 'bs-flaggedrevsconnector-notification-pageassignments-pagereview-subject';
			$headerParams = ['agent', 'title', 'title'];
		}

		return [
			'key' => $headerKey,
			'params' => $headerParams,
			'bundle-key' => $bundleKey,
			'bundle-params' => $bundleParams
		];
	}

	/**
	 * Gets appropriate message key and params for
	 * web notification message
	 *
	 * @return array
	 */
	public function getBodyMessageContent() {
		$bodyKey = 'bs-flaggedrevsconnector-notification-pageassignments-pagereview-body';
		$bodyParams = ['agent', 'title', 'title'];

		if( $this->distributionType == 'email' ) {
			$bodyKey = 'bs-flaggedrevsconnector-notification-pageassignments-pagereview-body';
			$bodyParams = ['agent', 'title', 'title'];
		}

		return [
			'key' => $bodyKey,
			'params' => $bodyParams
		];
	}

	public function getBodyMessage() {
		$content = $this->getBodyMessageContent();
		$msg = $this->msg( $content['key'] );
		if( empty( $content['params'] ) ) {
			return $msg;
		}

		foreach( $content['params'] as $param ) {
			$this->paramParser->parseParam( $msg, $param );
		}

		return $msg;
	}

	public function getSecondaryLinks() {
		if ( $this->isBundled() ) {
			// For the bundle, we don't need secondary actions
			return [];
		}

		return [$this->getAgentLink()];
	}
}
