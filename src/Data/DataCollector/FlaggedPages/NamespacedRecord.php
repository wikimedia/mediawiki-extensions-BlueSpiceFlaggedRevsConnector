<?php

namespace BlueSpice\FlaggedRevsConnector\Data\DataCollector\FlaggedPages;

use BlueSpice\FlaggedRevsConnector\Entity\Collection\NamespacedFlaggedPages;

class NamespacedRecord extends Record {
	public const NAMESPACE_NAME = NamespacedFlaggedPages::ATTR_NAMESPACE_NAME;
}
