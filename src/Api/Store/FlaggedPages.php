<?php
namespace BlueSpice\FlaggedRevsConnector\Api\Store;

use BlueSpice\Context;
use BlueSpice\FlaggedRevsConnector\Data\FlaggedPages\Store;

class FlaggedPages extends \BlueSpice\Api\Store {

	protected function makeDataStore() {
		$class = $this->getConfig()->get( 'FlaggedRevsConnectorFlaggedPagesStore' );
		return new $class(
			new Context( $this->getContext(), $this->getConfig() ),
			\MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer()
		);
	}
}
