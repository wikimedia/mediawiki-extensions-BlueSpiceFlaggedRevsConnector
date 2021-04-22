<?php

namespace BlueSpice\FlaggedRevsConnector\EntityConfig\Collection;

use BlueSpice\Data\FieldType;
use BlueSpice\EntityConfig;
use BlueSpice\ExtendedStatistics\Data\Entity\Collection\Schema;
use BlueSpice\ExtendedStatistics\EntityConfig\Collection;
use BlueSpice\FlaggedRevsConnector\Entity\Collection\FlaggedPages as Entity;
use Config;
use MediaWiki\MediaWikiServices;

abstract class FlaggedPages extends EntityConfig {

	/**
	 *
	 * @param Config $config
	 * @param string $key
	 * @param MediaWikiServices $services
	 * @return EntityConfig
	 */
	public static function factory( $config, $key, $services ) {
		$extension = $services->getService( 'BSExtensionFactory' )->getExtension(
			'BlueSpiceExtendedStatistics'
		);
		if ( !$extension ) {
			return null;
		}
		return new static( new Collection( $config ), $key );
	}

	/**
	 *
	 * @return string
	 */
	protected function get_StoreClass() {
		return $this->getConfig()->get( 'StoreClass' );
	}

	/**
	 *
	 * @return array
	 */
	protected function get_PrimaryAttributeDefinitions() {
		return array_filter( $this->get_AttributeDefinitions(), function ( $e ) {
			return isset( $e[Schema::PRIMARY] ) && $e[Schema::PRIMARY] === true;
		} );
	}

	/**
	 *
	 * @return array
	 */
	protected function get_VarMessageKeys() {
		return array_merge( $this->getConfig()->get( 'VarMessageKeys' ), [
			Entity::ATTR_DRAFT_PAGES => 'bs-flaggedrevsconnector-collection-var-draftpages',
			Entity::ATTR_FIRST_DRAFT_PAGES => 'bs-flaggedrevsconnector-collection-var-firstdraftpages',
			Entity::ATTR_APPROVED_PAGES => 'bs-flaggedrevsconnector-collection-var-approvedpages',
			Entity::ATTR_NOT_ENABLED_PAGES => 'bs-flaggedrevsconnector-collection-var-notenabledpages',
		] );
	}

	/**
	 *
	 * @return array
	 */
	protected function get_AttributeDefinitions() {
		$attributes = array_merge( $this->getConfig()->get( 'AttributeDefinitions' ), [
			Entity::ATTR_DRAFT_PAGES => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
				Schema::PRIMARY => true,
			],
			Entity::ATTR_FIRST_DRAFT_PAGES => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
				Schema::PRIMARY => true,
			],
			Entity::ATTR_APPROVED_PAGES => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
				Schema::PRIMARY => true,
			],
			Entity::ATTR_NOT_ENABLED_PAGES => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
				Schema::PRIMARY => true,
			],
			Entity::ATTR_DRAFT_PAGES_AGGREGATED => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
			],
			Entity::ATTR_FIRST_DRAFT_PAGES_AGGREGATED => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
			],
			Entity::ATTR_APPROVED_PAGES_AGGREGATED => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
			],
			Entity::ATTR_NOT_ENABLED_PAGES_AGGREGATED => [
				Schema::FILTERABLE => true,
				Schema::SORTABLE => true,
				Schema::TYPE => FieldType::INT,
				Schema::INDEXABLE => true,
				Schema::STORABLE => true,
			],
		] );
		return $attributes;
	}

}
