<?php
namespace BlueSpice\FlaggedRevsConnector\Api\Store;

use BlueSpice\Context;

class FlaggedPages extends \BlueSpice\Api\Store {

	protected function makeDataStore() {
		$class = $this->getConfig()->get( 'FlaggedRevsConnectorFlaggedPagesStore' );
		return new $class(
			new Context( $this->getContext(), $this->getConfig() ),
			$this->getServices()->getDBLoadBalancer()
		);
	}
}
