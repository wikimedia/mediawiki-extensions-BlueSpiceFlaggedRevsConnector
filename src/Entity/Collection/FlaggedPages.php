<?php

namespace BlueSpice\FlaggedRevsConnector\Entity\Collection;

use BlueSpice\ExtendedStatistics\Entity\Collection;

abstract class FlaggedPages extends Collection {
	const ATTR_DRAFT_PAGES = 'draftpages';
	const ATTR_FIRST_DRAFT_PAGES = 'firstdraftpages';
	const ATTR_APPROVED_PAGES = 'approvedpages';
	const ATTR_NOT_ENABLED_PAGES = 'notenabledpages';
	const ATTR_DRAFT_PAGES_AGGREGATED = 'draftpagesaggregated';
	const ATTR_FIRST_DRAFT_PAGES_AGGREGATED = 'firstdraftpagesaggregated';
	const ATTR_APPROVED_PAGES_AGGREGATED = 'approvedpagesaggregated';
	const ATTR_NOT_ENABLED_PAGES_AGGREGATED = 'notenabledpagesaggregated';
}
