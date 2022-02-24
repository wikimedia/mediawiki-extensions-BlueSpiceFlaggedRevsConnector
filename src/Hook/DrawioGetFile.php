<?php

namespace BlueSpice\FlaggedRevsConnector\Hook;

use BlueSpice\Hook;
use Config;
use File;
use IContextSource;
use User;

abstract class DrawioGetFile extends Hook {
	/** @var File */
	protected $file;
	/** @var bool */
	protected $isLatestStable;
	/** @var User */
	protected $user;

	/**
	 * @param File &$file
	 * @param bool &$latestIsStable
	 * @param User $user
	 * @return bool
	 */
	public static function callback( File &$file, &$latestIsStable, User $user ) {
		$className = static::class;
		$hookHandler = new $className(
			null,
			null,
			$file,
			$latestIsStable,
			$user
		);
		return $hookHandler->process();
	}

	/**
	 *
	 * @param IContextSource $context
	 * @param Config $config
	 * @param File &$file
	 * @param bool &$latestIsStable
	 * @param User $user
	 */
	public function __construct( $context, $config, File &$file, &$latestIsStable, User $user ) {
		parent::__construct( $context, $config );

		$this->file =& $file;
		$this->isLatestStable =& $latestIsStable;
		$this->user = $user;
	}
}
