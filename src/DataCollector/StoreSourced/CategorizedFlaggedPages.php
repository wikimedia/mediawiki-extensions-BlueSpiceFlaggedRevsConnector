<?php

namespace BlueSpice\FlaggedRevsConnector\DataCollector\StoreSourced;

use BlueSpice\FlaggedRevsConnector\DataCollector\AttributeMapping;
use Config;
use BlueSpice\FlaggedRevsConnector\Data\Record as MainStoreRecord;
use BlueSpice\Data\IRecord;
use BlueSpice\Data\IStore;
use BlueSpice\Data\RecordSet;
use BlueSpice\EntityFactory;
use BlueSpice\ExtendedStatistics\Entity\Snapshot;
use BlueSpice\ExtendedStatistics\DataCollector\StoreSourced;
use BlueSpice\ExtendedStatistics\SnapshotFactory;
use BlueSpice\FlaggedRevsConnector\Data\DataCollector\FlaggedPages\CategorizedRecord
	as CollectorRecord;
use BlueSpice\FlaggedRevsConnector\Data\FlaggedPages\Store;
use BlueSpice\FlaggedRevsConnector\Data\Record;
use BlueSpice\FlaggedRevsConnector\Entity\Collection\CategorizedFlaggedPages as Collection;
use BlueSpice\Services;
use FlaggedRevsConnector;
use MWException;
use BlueSpice\Data\Categories\Store as CategoryStore;
use LoadBalancer;

class CategorizedFlaggedPages extends StoreSourced\CategoryCollector {

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
	 * @param Services $services
	 * @param Snapshot $snapshot
	 * @param Config|null $config
	 * @param EntityFactory|null $factory
	 * @param IStore|null $store
	 * @param SnapshotFactory|null $snapshotFactory
	 * @param CategoryStore|null $categoryStore
	 * @param LoadBalancer|null $lb
	 * @return static
	 * @throws MWException
	 */
	public static function factory( $type, Services $services, Snapshot $snapshot,
		Config $config = null, EntityFactory $factory = null, IStore $store = null,
		SnapshotFactory $snapshotFactory = null, CategoryStore $categoryStore = null,
		LoadBalancer $lb = null ) {
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
		if ( !$categoryStore ) {
			$categoryStore = StoreSourced\CategoryCollector::getCategoryStore( $services );
		}

		if ( !$lb ) {
			$lb = Services::getInstance()->getDBLoadBalancer();
		}

		return new static(
			$type,
			$snapshot,
			$config,
			$factory,
			$store,
			$snapshotFactory,
			$categoryStore,
			$lb
		);
	}

	/**
	 *
	 * @param string $type
	 * @param Snapshot $snapshot
	 * @param Config $config
	 * @param EntityFactory $factory
	 * @param IStore $store
	 * @param SnapshotFactory $snapshotFactory
	 * @param CategoryStore $categoryStore
	 * @param LoadBalancer $lb
	 */
	protected function __construct( $type, Snapshot $snapshot, Config $config, EntityFactory $factory,
		IStore $store, SnapshotFactory $snapshotFactory, CategoryStore $categoryStore,
		LoadBalancer $lb ) {
		parent::__construct(
			$type,
			$snapshot,
			$config,
			$factory,
			$store,
			$snapshotFactory,
			$categoryStore,
			$lb
		);

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
		$categoryNames = array_keys( $this->getValidCategories() );
		foreach ( $this->collectionItems as $item ) {
			$$item = array_fill_keys( $categoryNames, 0 );
		}

		foreach ( $res->getRecords() as $record ) {
			$title = \Title::makeTitle(
				$record->get( MainStoreRecord::PAGE_NAMESPACE ),
				$record->get( MainStoreRecord::PAGE_TITLE )
			);
			if ( !$title instanceof \Title ) {
				continue;
			}

			$state = $record->get( Record::REVISION_STATE_RAW );
			$state = $state ?: FlaggedRevsConnector::STATE_NOT_ENABLED;

			$collectionItem = $this->stateMap[$state];
			$collectionAggItem = $this->aggregateMap[$state];

			$categories = $this->getCategoriesForTitle( $title );
			foreach ( $categories as $dbKey => $category ) {
				${$collectionItem}[$dbKey] ++;
				${$collectionAggItem}[$dbKey] ++;
			}
		}

		$lastCollection = $this->getLastCollection();
		foreach ( $lastCollection as $collection ) {
			$categoryName = $collection->get( Collection::ATTR_CATEGORY_NAME );
			foreach ( $this->aggregateMap as $state => $aggItem ) {
				// phpcs:ignore MediaWiki.Usage.InArrayUsage.Found
				if ( !in_array( $categoryName, array_keys( $$aggItem ) ) ) {
					array_fill_keys( [ $categoryName ], 0 );
				}
				$changeVar = $this->stateMap[$state];
				${$changeVar}[$categoryName] = ${$aggItem}[$categoryName]
					- $collection->get( $aggItem, 0 );
			}
		}

		foreach ( $categoryNames as $name ) {
			$values = [];
			foreach ( $this->collectionItems as $item ) {
				$values[$item] = ${$item}[$name];
			}
			$data[] = new CollectorRecord( (object)array_merge( [
				CollectorRecord::CATEGORY_NAME => $name
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
			Collection::ATTR_CATEGORY_NAME => $record->get(
				CollectorRecord::CATEGORY_NAME
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

	/**
	 *
	 * @return string
	 */
	protected function getCollectionClass() {
		return Collection::class;
	}
}
