<?php

namespace BlueSpice\FlaggedRevsConnector\PageInfoElement;

use BlueSpice\FlaggedRevsConnector\Utils;
use Config;
use IContextSource;
use PageHeader\PageInfo;

abstract class FlaggedPageElement extends PageInfo {

	/** @var Utils|null */
	public $utils = null;

	/**
	 *
	 * @param IContextSource $context
	 * @param Config $config
	 * @param Utils $utils
	 */
	public function __construct( IContextSource $context, Config $config, Utils $utils ) {
		parent::__construct( $context, $config );
		$this->utils = $utils;
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldShow( $context ) {
		if ( !$this->utils->userCanAccessDrafts( $this->context->getUser() ) ) {
			return false;
		}

		if ( !$this->utils->isFlaggableNamespace( $this->context->getTitle() ) ) {
			return false;
		}

		return true;
	}
}
