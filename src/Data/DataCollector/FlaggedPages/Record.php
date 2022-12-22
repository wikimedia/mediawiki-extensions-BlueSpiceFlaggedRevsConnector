<?php

namespace BlueSpice\FlaggedRevsConnector\Data\DataCollector\FlaggedPages;

use BlueSpice\FlaggedRevsConnector\Entity\Collection\FlaggedPages;

abstract class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const DRAFT = FlaggedPages::ATTR_DRAFT_PAGES;
	public const FIRST_DRAFT = FlaggedPages::ATTR_FIRST_DRAFT_PAGES;
	public const APPROVED = FlaggedPages::ATTR_APPROVED_PAGES;
	public const NOT_ENABLED = FlaggedPages::ATTR_NOT_ENABLED_PAGES;
	public const DRAFT_AGGREGATE = FlaggedPages::ATTR_DRAFT_PAGES_AGGREGATED;
	public const FIRST_DRAFT_AGGREGATE = FlaggedPages::ATTR_FIRST_DRAFT_PAGES_AGGREGATED;
	public const APPROVED_AGGREGATE = FlaggedPages::ATTR_APPROVED_PAGES_AGGREGATED;
	public const NOT_ENABLED_AGGREGATE = FlaggedPages::ATTR_NOT_ENABLED_PAGES_AGGREGATED;
}
