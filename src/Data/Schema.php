<?php

namespace BlueSpice\FlaggedRevsConnector\Data;

use BlueSpice\Data\FieldType;

class Schema extends \BlueSpice\Data\Schema {
	public function __construct() {
		parent::__construct( [
			Record::PAGE_ID => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::INT
			],
			Record::PAGE_TITLE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::REVISION_STATE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::REVISIONS_SINCE_STABLE => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::INT
			],
			Record::PAGE_CATEGORIES => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			]
		] );
	}
}
