<?php

namespace BlueSpice\FlaggedRevsConnector\Hook\MessageCacheGet;

use BlueSpice\Hook\MessageCacheGet;

class ApplyBusinessVocabulary extends MessageCacheGet {

	protected function skipProcessing() {
		return !isset( $this->getKeyMap()[$this->lckey] );
	}

	protected function doProcess() {
		$this->lckey = $this->getKeyMap()[$this->lckey];
	}

	/**
	 *
	 * @return array
	 */
	private function getKeyMap() {
		return [
			"action-review" => "bsfrc-action-review",
			"revreview-check-flag-p" => "bsfrc-revreview-check-flag-p",
			"revreview-check-flag-p-title" => "bsfrc-revreview-check-flag-p-title",
			"revreview-check-flag-u" => "bsfrc-revreview-check-flag-u",
			"rrevreview-check-flag-u-title" => "bsfrc-revreview-check-flag-u-title",
			"revreview-check-flag-y" => "bsfrc-revreview-check-flag-y",
			"revreview-check-flag-y-title" => "bsfrc-revreview-check-flag-y-title",
			"revreview-hist-draft" => "bsfrc-revreview-hist-draft",
			"revreview-hist-pending" => "bsfrc-revreview-hist-pending",
			"revreview-hist-quality" => "bsfrc-revreview-hist-quality",
			"revreview-hist-basic" => "bsfrc-revreview-hist-basic",
			"revreview-hist-quality-user" => "bsfrc-revreview-hist-quality-user",
			"revreview-hist-basic-user" => "bsfrc-revreview-hist-basic-user",
			"revreview-hist-quality-auto" => "bsfrc-revreview-hist-quality-auto",
			"revreview-hist-basic-auto" => "bsfrc-revreview-hist-basic-auto",
			"revreview-hist-pending-difflink" => "bsfrc-revreview-hist-pending-difflink",
			"review-edit-diff" => "bsfrc-review-edit-diff",
			"revreview-diff-toggle-title" => "bsfrc-revreview-diff-toggle-title",
			"revreview-log-toggle-show" => "bsfrc-revreview-log-toggle-show",
			"revreview-log-toggle-hide" => "bsfrc-revreview-log-toggle-hide",
			"revreview-log-toggle-title" => "bsfrc-revreview-log-toggle-title",
			"revreview-log-details-title" => "bsfrc-revreview-log-details-title",
			"review-logentry-app" => "bsfrc-review-logentry-app",
			"review-logentry-dis" => "bsfrc-review-logentry-dis",
			"review-logentry-diff" => "bsfrc-review-logentry-diff",
			"revreview-accuracy-1" => "bsfrc-revreview-accuracy-1",
			"revreview-accuracy-2" => "bsfrc-revreview-accuracy-2",
			"revreview-accuracy-3" => "bsfrc-revreview-accuracy-3",
			"revreview-basic" => "bsfrc-revreview-basic",
			"revreview-basic-i" => "bsfrc-revreview-basic-i",
			"revreview-basic-old" => "bsfrc-revreview-basic-old",
			"revreview-basic-same" => "bsfrc-revreview-basic-same",
			"revreview-basic-source" => "bsfrc-revreview-basic-source",
			"revreview-current" => "bsfrc-revreview-current",
			"revreview-draft-title" => "bsfrc-revreview-draft-title",
			"revreview-submitedit-title" => "bsfrc-revreview-submitedit-title",
			"revreview-edited" => "bsfrc-revreview-edited",
			"revreview-newest-basic" => "bsfrc-revreview-newest-basic",
			"revreview-newest-basic-i" => "bsfrc-revreview-newest-basic-i",
			"revreview-newest-quality" => "bsfrc-revreview-newest-quality",
			"revreview-newest-quality-i" => "bsfrc-revreview-newest-quality-i",
			"revreview-pending-basic" => "bsfrc-revreview-pending-basic",
			"revreview-pending-quality" => "bsfrc-revreview-pending-quality",
			"revreview-pending-nosection" => "bsfrc-revreview-pending-nosection",
			"revreview-noflagged" => "bsfrc-revreview-noflagged",
			"revreview-quality" => "bsfrc-revreview-quality",
			"revreview-quality-i" => "bsfrc-revreview-quality-i",
			"revreview-quality-old" => "bsfrc-revreview-quality-old",
			"revreview-quality-same" => "bsfrc-revreview-quality-same",
			"revreview-quality-source" => "bsfrc-revreview-quality-source",
			"revreview-quality-title" => "bsfrc-revreview-quality-title",
			"revreview-quick-basic" => "bsfrc-revreview-quick-basic",
			"revreview-quick-basic-old" => "bsfrc-revreview-quick-basic-old",
			"revreview-quick-basic-same" => "bsfrc-revreview-quick-basic-same",
			"revreview-quick-none" => "bsfrc-revreview-quick-none",
			"revreview-quick-quality" => "bsfrc-revreview-quick-quality",
			"revreview-quick-quality-old" => "bsfrc-revreview-quick-quality-old",
			"revreview-quick-quality-same" => "bsfrc-revreview-quick-quality-same",
			"revreview-quick-see-basic" => "bsfrc-revreview-quick-see-basic",
			"revreview-quick-see-quality" => "bsfrc-revreview-quick-see-quality",
			"revreview-basic-title" => "bsfrc-revreview-basic-title",
			"revreview-visibility-synced" => "bsfrc-revreview-visibility-synced",
			"revreview-visibility-outdated" => "bsfrc-revreview-visibility-outdated",
			"revreview-visibility-nostable" => "bsfrc-revreview-visibility-nostable",
			"right-autoreview" => "bsfrc-right-autoreview",
			"right-autoreviewrestore" => "bsfrc-right-autoreviewrestore",
			"right-movestable" => "bsfrc-right-movestable",
			"right-review" => "bsfrc-right-review",
			"right-stablesettings" => "bsfrc-right-stablesettings",
			"right-validate" => "bsfrc-right-validate",
			"right-unreviewedpages" => "bsfrc-right-unreviewedpages",
			"stable-logentry-config" => "bsfrc-stable-logentry-config",
			"stable-logentry-modify" => "bsfrc-stable-logentry-modify",
			"stable-logentry-reset" => "bsfrc-stable-logentry-reset",
			"stable-log-restriction" => "bsfrc-stable-log-restriction",
			"stable-logpagetext" => "bsfrc-stable-logpagetext",
			"revreview-filter-stable" => "bsfrc-revreview-filter-stable",
			"revreview-statusfilter" => "bsfrc-revreview-statusfilter",
			"revreview-filter-reapproved" => "bsfrc-revreview-filter-reapproved",
			"revreview-filter-unapproved" => "bsfrc-revreview-filter-unapproved",
			"revreview-lev-basic" => "bsfrc-revreview-lev-basic",
			"revreview-lev-quality" => "bsfrc-revreview-lev-quality",
			"revreview-def-stable" => "bsfrc-revreview-def-stable",
			"revreview-restrictfilter" => "bsfrc-revreview-restrictfilter",
			"revreview-reviewlink" => "bsfrc-revreview-reviewlink",
			"revreview-reviewlink-title" => "bsfrc-revreview-reviewlink-title",
			"revreview-unreviewedpage" => "bsfrc-revreview-unreviewedpage",
			"tooltip-ca-current" => "bsfrc-tooltip-ca-current",
			"tooltip-ca-stable" => "bsfrc-tooltip-ca-stable",
			"tooltip-ca-default" => "bsfrc-tooltip-ca-default",
			"flaggedrevs-protect-legend" => "bsfrc-flaggedrevs-protect-legend",
			"flaggedrevs-categoryview" => "bsfrc-flaggedrevs-categoryview",
			"revreview-locked-title" => "bsfrc-revreview-locked-title",
			"revreview-unlocked-title" => "bsfrc-revreview-unlocked-title",
			"revreview-locked" => "bsfrc-revreview-locked",
			"revreview-unlocked" => "bsfrc-revreview-unlocked",
			"validationpage" => "bsfrc-validationpage",
			"revreview-editnotice" => "bsfrc-revreview-editnotice",
			"copyrightwarning2" => "bsfrc-copyrightwarning2",
		];
	}
}
