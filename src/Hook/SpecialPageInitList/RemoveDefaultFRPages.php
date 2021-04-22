<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\SpecialPageInitList;

use BlueSpice\Hook\SpecialPageInitList;

class RemoveDefaultFRPages extends SpecialPageInitList {
	/** @var string[] */
	private $toRemove = [
		'RevisionReview',
		'Stabilization',
		'ConfiguredPages',
		'PendingChanges',
		'ProblemChanges',
		'QualityOversight',
		'ReviewedPages',
		'ReviewedVersions',
		'StablePages',
		'UnreviewedPages',
		'ValidationStatistics',
	];

	protected function doProcess() {
		foreach ( $this->toRemove as $page ) {
			if ( isset( $this->list[$page] ) ) {
				unset( $this->list[$page] );
			}
		}

		return true;
	}
}
