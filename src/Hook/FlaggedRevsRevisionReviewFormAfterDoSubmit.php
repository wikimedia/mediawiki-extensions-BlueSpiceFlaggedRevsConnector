<?php

namespace BlueSpice\FlaggedRevsConnector\Hook;

use BlueSpice\Hook;
use Config;
use IContextSource;
use RevisionReviewForm;

abstract class FlaggedRevsRevisionReviewFormAfterDoSubmit extends Hook {

	/**
	 * @var RevisionReviewForm
	 */
	protected $revisionReviewForm;

	/**
	 * @var mixed
	 */
	protected $status;

	/**
	 * @param RevisionReviewForm $revisionReviewForm
	 * @param mixed $status - true on success, error string on failure
	 * @return bool
	 */
	public static function callback( RevisionReviewForm $revisionReviewForm, $status ) {
		$hookHandler = new static( $revisionReviewForm, $status );
		return $hookHandler->process();
	}

	/**
	 *
	 * @param RevisionReviewForm $revisionReviewForm
	 * @param mixed $status
	 * @param IContextSource|null $context
	 * @param Config|null $config
	 */
	public function __construct( RevisionReviewForm $revisionReviewForm, $status, $context = null, $config = null ) {
		parent::__construct( $context, $config );
		$this->revisionReviewForm = $revisionReviewForm;
		$this->status = $status;
	}
}
