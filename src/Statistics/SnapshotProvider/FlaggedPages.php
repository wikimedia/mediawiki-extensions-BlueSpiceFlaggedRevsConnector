<?php

namespace BlueSpice\FlaggedRevsConnector\Statistics\SnapshotProvider;

use BlueSpice\ExtendedStatistics\ISnapshotProvider;
use BlueSpice\ExtendedStatistics\Snapshot;
use BlueSpice\ExtendedStatistics\SnapshotDate;
use Config;
use MWNamespace;
use Title;
use Wikimedia\Rdbms\LoadBalancer;

class FlaggedPages implements ISnapshotProvider {
	/** @var LoadBalancer */
	private $loadBalancer;
	/** @var Config */
	private $config;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param Config $config
	 */
	public function __construct( LoadBalancer $loadBalancer, Config $config ) {
		$this->loadBalancer = $loadBalancer;
		$this->config = $config;
	}

	/**
	 * @param SnapshotDate $date
	 * @return Snapshot
	 */
	public function generateSnapshot( SnapshotDate $date ): Snapshot {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$enabledNs = $this->config->get( 'FlaggedRevsNamespaces' );

		$res = $db->select(
			[ 'page', 'flaggedrevs', 'categorylinks' ],
			[ 'COUNT(page_title) as pages', 'page_namespace', 'fr_rev_id', 'cl_to' ],
			[
				'page_namespace IN (' . $db->makeList( $enabledNs ) . ')'
			],
			__METHOD__,
			[
				'GROUP BY' => 'page_namespace, fr_rev_id, cl_to'
			],
			[
				'flaggedrevs' => [
					"LEFT OUTER JOIN", [ 'page_id=fr_page_id', 'page_latest=fr_rev_id' ]
				],
				'categorylinks' => [
					"LEFT OUTER JOIN", [ 'page_id=cl_from' ]
				]
			]
		);

		$draft = 0;
		$stable = 0;
		$namespaces = [];
		$categories = [];
		foreach ( $res as $row ) {
			$pageCount = (int)$row->pages;
			if ( (int)$row->page_namespace === 0 ) {
				$namespace = '-';
			} else {
				$namespace = MWNamespace::getCanonicalName( $row->page_namespace );
			}
			$category = $row->cl_to;
			$approved = (bool)$row->fr_rev_id;

			$approved ? $stable++ : $draft++;
			if ( !isset( $namespaces[$namespace] ) ) {
				$namespaces[$namespace] = [ 'draft' => 0, 'stable' => 0 ];
			}
			$stable ?
				$namespaces[$namespace]['stable'] += $pageCount :
				$namespaces[$namespace]['draft'] += $pageCount;
			if ( $category ) {
				if ( !isset( $categories[$category] ) ) {
					$categories[$category] = [ 'draft' => 0, 'stable' => 0 ];
				}
				$stable ?
					$categories[$category]['stable'] += $pageCount :
					$categories[$category]['draft'] += $pageCount;
			}
		}

		return new Snapshot( $date, $this->getType(), [
			'draft' => $draft,
			'stable' => $stable,
			'namespace' => $namespaces,
			'categories' => $categories,
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function aggregate(
		array $snapshots, $interval = Snapshot::INTERVAL_DAY, $date = null
	): Snapshot {
		$lastSnapshot = array_pop( $snapshots );
		return new Snapshot(
			$date ?? new SnapshotDate(), $this->getType(), $lastSnapshot->getData(), $interval
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getType() {
		return 'frc-flaggedpages';
	}

	/**
	 * @param Snapshot $snapshot
	 * @return array|void|null
	 */
	public function getSecondaryData( Snapshot $snapshot ) {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$enabledNs = $this->config->get( 'FlaggedRevsNamespaces' );

		$res = $db->select(
			[ 'page', 'flaggedrevs', 'categorylinks' ],
			[ 'page_id', 'page_title', 'page_namespace', 'fr_rev_id', 'GROUP_CONCAT( cl_to ) as cats' ],
			[
				'page_namespace IN (' . $db->makeList( $enabledNs ) . ')'
			],
			__METHOD__,
			[
				'GROUP BY' => 'page_id,fr_rev_id'
			],
			[
				'flaggedrevs' => [
					"LEFT OUTER JOIN", [ 'page_id=fr_page_id', 'page_latest=fr_rev_id' ]
				],
				'categorylinks' => [
					"LEFT OUTER JOIN", [ 'page_id=cl_from' ]
				]
			]
		);

		$data = [];
		foreach ( $res as $row ) {
			$title = Title::newFromRow( $row );
			$data[$title->getPrefixedDBkey()] = [
				'id' => (int)$row->page_id,
				'n' => (int)$row->page_namespace,
				'c' => is_string( $row->cats ) ? explode( ',', $row->cats ) : [],
				's' => (bool)$row->fr_rev_id,
			];
		}

		return $data;
	}
}
