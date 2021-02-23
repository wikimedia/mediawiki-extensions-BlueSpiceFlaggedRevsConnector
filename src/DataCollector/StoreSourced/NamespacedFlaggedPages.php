<?php

namespace BlueSpice\FlaggedRevsConnector\DataCollector\StoreSourced;

use BlueSpice\FlaggedRevsConnector\DataCollector\AttributeMapping;
use Config;
use BlueSpice\Data\IRecord;
use BlueSpice\Data\IStore;
use BlueSpice\Data\RecordSet;
use BlueSpice\EntityFactory;
use BlueSpice\ExtendedStatistics\Entity\Snapshot;
use BlueSpice\ExtendedStatistics\DataCollector\StoreSourced;
use BlueSpice\ExtendedStatistics\SnapshotFactory;
use BlueSpice\FlaggedRevsConnector\Data\DataCollector\FlaggedPages\NamespacedRecord as CollectorRecord;
use BlueSpice\FlaggedRevsConnector\Data\FlaggedPages\Store;
use BlueSpice\FlaggedRevsConnector\Data\Record;
use BlueSpice\FlaggedRevsConnector\Entity\Collection\NamespacedFlaggedPages as Collection;
use FlaggedRevsConnector;
use MediaWiki\MediaWikiServices;
use MWException;

class NamespacedFlaggedPages extends StoreSourced\NamespaceCollector {

	/**
	 * @var array
	 */
	protected $collectionItems = [];

	/**
	 * @var array
	 */
	protected $stateMap = [];

	/**
	 * @var array
	 */
	protected $aggregateMap = [];

	/**
	 * @param string $type
	 * @param MediaWikiServices $services
	 * @param Snapshot $snapshot
	 * @param Config|null $config
	 * @param EntityFactory|null $factory
	 * @param IStore|null $store
	 * @param SnapshotFactory|null $snapshotFactory
	 * @param array|null $namespaces
	 * @return static
	 * @throws MWException
	 */
	public static function factory( $type, MediaWikiServices $services, Snapshot $snapshot,
		Config $config = null, EntityFactory $factory = null, IStore $store = null,
		SnapshotFactory $snapshotFactory = null, array $namespaces = null ) {
		if ( !$config ) {
			$config = $snapshot->getConfig();
		}
		if ( !$factory ) {
			$factory = $services->getService( 'BSEntityFactory' );
		}
		if ( !$store ) {
			$context = \RequestContext::getMain();
			$context->setUser(
				$services->getService( 'BSUtilityFactory' )->getMaintenanceUser()->getUser()
			);
			$store = new Store( $context, $services->getDBLoadBalancer() );
		}
		if ( !$snapshotFactory ) {
			$snapshotFactory = $services->getService(
				'BSExtendedStatisticsSnapshotFactory'
			);
		}
		if ( !$namespaces ) {
			$namespaces = StoreSourced\NamespaceCollector::getNamespaces( $snapshot, $services );
		}

		return new static(
			$type,
			$snapshot,
			$config,
			$factory,
			$store,
			$snapshotFactory,
			$namespaces
		);
	}

	protected function __construct( $type, Snapshot $snapshot, Config $config, EntityFactory $factory,
		IStore $store, SnapshotFactory $snapshotFactory, array $namespaces ) {
		parent::__construct( $type, $snapshot, $config, $factory, $store, $snapshotFactory, $namespaces );

		$this->collectionItems = AttributeMapping::$collectionItems;
		$this->stateMap = AttributeMapping::$stateMap;
		$this->aggregateMap = AttributeMapping::$aggregateMap;
	}

	/**
	 *
	 * @return RecordSet
	 */
	protected function doCollect() {
		$res = parent::doCollect();
		$data = [];
		// Init vars
		$canonicals = array_values( $this->namespaces );
		foreach ( $this->collectionItems as $item ) {
			$$item = array_fill_keys( $canonicals, 0 );
		}

		foreach ( $res->getRecords() as $record ) {
			$pageNamespace = (int)$record->get( Record::PAGE_NAMESPACE );
			if ( !isset( $this->namespaces[$pageNamespace] ) ) {
				// removed or broken ns
				continue;
			}
			$nsName = $this->namespaces[$pageNamespace];

			$state = $record->get( Record::REVISION_STATE_RAW );
			$state = $state ?: FlaggedRevsConnector::STATE_NOT_ENABLED;

			$collectionItem = $this->stateMap[$state];
			$collectionAggItem = $this->aggregateMap[$state];
			${$collectionItem}[$nsName] ++;
			${$collectionAggItem}[$nsName] ++;
		}

		$lastCollection = $this->getLastCollection();
		foreach ( $lastCollection as $collection ) {
			$nsName = $collection->get( Collection::ATTR_NAMESPACE_NAME );
			foreach ( $this->aggregateMap as $state => $aggItem ) {
				if ( !in_array( $nsName, array_keys( $$aggItem ) ) ) {
					array_fill_keys( [ $nsName ], 0 );
				}
				if ( !isset( ${$changeVar}[$nsName] ) ) {
					${$changeVar}[$nsName] = 0;
				}
				$changeVar = $this->stateMap[$state];
				${$changeVar}[$nsName] = ${$aggItem}[$nsName]
					- $collection->get( $aggItem, 0 );
			}
		}

		foreach ( $this->namespaces as $idx => $canonicalName ) {
			$values = [];
			foreach ( $this->collectionItems as $item ) {
				$values[$item] = ${$item}[$canonicalName];
			}
			$data[] = new CollectorRecord( (object)array_merge( [
				CollectorRecord::NAMESPACE_NAME => $canonicalName
			], $values ) );
		}

		return new RecordSet( $data );
	}

	/**
	 *
	 * @return array
	 */
	protected function getFilter() {
		return array_merge( parent::getFilter(), [] );
	}

	/**
	 *
	 * @return array
	 */
	protected function getSort() {
		return [];
	}

	/**
	 *
	 * @param IRecord $record
	 * @return \stdClass
	 */
	protected function map( IRecord $record ) {
		return (object)[
			Collection::ATTR_TYPE => Collection::TYPE,
			Collection::ATTR_NAMESPACE_NAME => $record->get(
				CollectorRecord::NAMESPACE_NAME
			),
			Collection::ATTR_FIRST_DRAFT_PAGES => $record->get(
				CollectorRecord::FIRST_DRAFT
			),
			Collection::ATTR_DRAFT_PAGES => $record->get(
				CollectorRecord::DRAFT
			),
			Collection::ATTR_APPROVED_PAGES => $record->get(
				CollectorRecord::APPROVED
			),
			Collection::ATTR_NOT_ENABLED_PAGES => $record->get(
				CollectorRecord::NOT_ENABLED
			),
			Collection::ATTR_FIRST_DRAFT_PAGES_AGGREGATED => $record->get(
				CollectorRecord::FIRST_DRAFT_AGGREGATE
			),
			Collection::ATTR_DRAFT_PAGES_AGGREGATED => $record->get(
				CollectorRecord::DRAFT_AGGREGATE
			),
			Collection::ATTR_APPROVED_PAGES_AGGREGATED => $record->get(
				CollectorRecord::APPROVED_AGGREGATE
			),
			Collection::ATTR_NOT_ENABLED_PAGES_AGGREGATED => $record->get(
				CollectorRecord::NOT_ENABLED_AGGREGATE
			),
			Collection::ATTR_TIMESTAMP_CREATED => $this->snapshot->get(
				Snapshot::ATTR_TIMESTAMP_CREATED
			),
			Collection::ATTR_TIMESTAMP_TOUCHED => $this->snapshot->get(
				Snapshot::ATTR_TIMESTAMP_TOUCHED
			),
		];
	}

	protected function getCollectionClass() {
		return Collection::class;
	}
}
