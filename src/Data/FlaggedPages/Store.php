<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use Wikimedia\Rdbms\ILoadBalancer;

class Store implements \BlueSpice\Data\IStore {
	/**
	 *
	 * @var \IContextSource
	 */
	protected $context = null;

	/**
	 *
	 * @param \IContextSource $context
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( $context, $loadBalancer ) {
		$this->context = $context;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 *
	 * @return Reader
	 */
	public function getReader() {
		return new Reader( $this->loadBalancer, $this->context );
	}

	/**
	 *
	 * @throws Exception
	 */
	public function getWriter() {
		throw new Exception( 'This store does not support writing!' );
	}

}
