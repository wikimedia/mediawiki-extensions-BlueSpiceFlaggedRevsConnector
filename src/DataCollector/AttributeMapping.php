<?php

namespace BlueSpice\FlaggedRevsConnector\DataCollector;

use BlueSpice\FlaggedRevsConnector\Entity\Collection\FlaggedPages as Collection;
use FlaggedRevsConnector;

class AttributeMapping {

	/**
	 * @var array
	 */
	public static $collectionItems = [
		Collection::ATTR_FIRST_DRAFT_PAGES,
		Collection::ATTR_DRAFT_PAGES,
		Collection::ATTR_APPROVED_PAGES,
		Collection::ATTR_NOT_ENABLED_PAGES,
		Collection::ATTR_FIRST_DRAFT_PAGES_AGGREGATED,
		Collection::ATTR_DRAFT_PAGES_AGGREGATED,
		Collection::ATTR_APPROVED_PAGES_AGGREGATED,
		Collection::ATTR_NOT_ENABLED_PAGES_AGGREGATED,
	];

	/**
	 * @var array
	 */
	public static $stateMap = [
		FlaggedRevsConnector::STATE_DRAFT => Collection::ATTR_DRAFT_PAGES,
		FlaggedRevsConnector::STATE_UNMARKED => Collection::ATTR_FIRST_DRAFT_PAGES,
		FlaggedRevsConnector::STATE_STABLE => Collection::ATTR_APPROVED_PAGES,
		FlaggedRevsConnector::STATE_NOT_ENABLED => Collection::ATTR_NOT_ENABLED_PAGES
	];

	/**
	 * @var array
	 */
	public static $aggregateMap = [
		FlaggedRevsConnector::STATE_DRAFT => Collection::ATTR_DRAFT_PAGES_AGGREGATED,
		FlaggedRevsConnector::STATE_UNMARKED => Collection::ATTR_FIRST_DRAFT_PAGES_AGGREGATED,
		FlaggedRevsConnector::STATE_STABLE => Collection::ATTR_APPROVED_PAGES_AGGREGATED,
		FlaggedRevsConnector::STATE_NOT_ENABLED => Collection::ATTR_NOT_ENABLED_PAGES_AGGREGATED
	];
}
