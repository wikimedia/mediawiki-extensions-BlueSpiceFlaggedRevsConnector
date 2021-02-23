<?php

namespace BlueSpice\FlaggedRevsConnector\Data\DataCollector\FlaggedPages;

use BlueSpice\Data\Record as BaseRecord;
use BlueSpice\FlaggedRevsConnector\Entity\Collection\FlaggedPages;

abstract class Record extends BaseRecord {
	const DRAFT = FlaggedPages::ATTR_DRAFT_PAGES;
	const FIRST_DRAFT = FlaggedPages::ATTR_FIRST_DRAFT_PAGES;
	const APPROVED = FlaggedPages::ATTR_APPROVED_PAGES;
	const NOT_ENABLED = FlaggedPages::ATTR_NOT_ENABLED_PAGES;
	const DRAFT_AGGREGATE = FlaggedPages::ATTR_DRAFT_PAGES_AGGREGATED;
	const FIRST_DRAFT_AGGREGATE = FlaggedPages::ATTR_FIRST_DRAFT_PAGES_AGGREGATED;
	const APPROVED_AGGREGATE = FlaggedPages::ATTR_APPROVED_PAGES_AGGREGATED;
	const NOT_ENABLED_AGGREGATE = FlaggedPages::ATTR_NOT_ENABLED_PAGES_AGGREGATED;
}
