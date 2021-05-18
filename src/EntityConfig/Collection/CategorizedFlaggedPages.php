<?php

namespace BlueSpice\FlaggedRevsConnector\EntityConfig\Collection;

use BlueSpice\Data\FieldType;
use BlueSpice\ExtendedStatistics\Data\Entity\Collection\Schema;
use BlueSpice\FlaggedRevsConnector\Entity\Collection\CategorizedFlaggedPages as Entity;

class CategorizedFlaggedPages extends FlaggedPages {
	/**
	 *
	 * @return string
	 */
	protected function get_TypeMessageKey() {
		return 'bs-flaggedrevsconnector-collection-type-flaggedpages-cat';
	}

	/**
	 *
	 * @return array
	 */
	protected function get_VarMessageKeys() {
		return array_merge( parent::get_VarMessageKeys(), [
			Entity::ATTR_CATEGORY_NAME => 'bs-flaggedrevsconnector-collection-var-categoryname'
		] );
	}

	/**
	 *
	 * @return string[]
	 */
	protected function get_Modules() {
		return array_merge( $this->getConfig()->get( 'Modules' ), [
			'ext.bluespice.flaggedrevsconnector.collection.flaggedpages.cat',
		] );
	}

	/**
	 *
	 * @return string
	 */
	protected function get_EntityClass() {
		return Entity::class;
	}

	/**
	 *
	 * @return array
	 */
	protected function get_AttributeDefinitions() {
		$attributes = array_merge( parent::get_AttributeDefinitions(), [
			Entity::ATTR_CATEGORY_NAME => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::STRING,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
			]
		] );
		return $attributes;
	}

}
