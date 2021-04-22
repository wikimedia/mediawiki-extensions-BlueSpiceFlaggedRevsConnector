<?php

namespace BlueSpice\FlaggedRevsConnector\Entity\Collection;

use BlueSpice\ExtendedStatistics\Entity\Collection;

abstract class FlaggedPages extends Collection {
	public const ATTR_DRAFT_PAGES = 'draftpages';
	public const ATTR_FIRST_DRAFT_PAGES = 'firstdraftpages';
	public const ATTR_APPROVED_PAGES = 'approvedpages';
	public const ATTR_NOT_ENABLED_PAGES = 'notenabledpages';
	public const ATTR_DRAFT_PAGES_AGGREGATED = 'draftpagesaggregated';
	public const ATTR_FIRST_DRAFT_PAGES_AGGREGATED = 'firstdraftpagesaggregated';
	public const ATTR_APPROVED_PAGES_AGGREGATED = 'approvedpagesaggregated';
	public const ATTR_NOT_ENABLED_PAGES_AGGREGATED = 'notenabledpagesaggregated';
}
