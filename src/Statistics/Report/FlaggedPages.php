<?php

namespace BlueSpice\FlaggedRevsConnector\Statistics\Report;

use BlueSpice\ExtendedStatistics\ClientReportHandler;
use BlueSpice\ExtendedStatistics\IReport;
use MWNamespace;

class FlaggedPages implements IReport {

	/**
	 * @inheritDoc
	 */
	public function getSnapshotKey() {
		return 'frc-flaggedpages';
	}

	/**
	 * @inheritDoc
	 */
	public function getClientData( $snapshots, array $filterData, $limit = 20 ): array {
		$dataset = null;
		if ( isset( $filterData['namespaces'] ) && !empty( $filterData['namespaces'] ) ) {
			$dataset = 'namespace';
			$filterValues = $filterData['namespaces'];
			$filterValues = array_map( static function ( $id ) {
				if ( (int)$id === 0 ) {
					return '-';
				}
				return MWNamespace::getCanonicalName( $id );
			}, $filterValues );
		}
		if ( isset( $filterData['categories'] ) && !empty( $filterData['categories'] ) ) {
			$dataset = 'categories';
			$filterValues = $filterData['categories'];
		}

		$processed = [];
		foreach ( $snapshots as $snapshot ) {
			$data = $snapshot->getData();
			if ( $dataset === null ) {
				$processed[] = [
					'name' => $snapshot->getDate()->forGraph(),
					'draft' => $data['draft'],
					'stable' => $data['stable'],
				];
				continue;
			}
			$data = $data[$dataset];
			foreach ( $data as $key => $details ) {
				if ( !in_array( $key, $filterValues ) ) {
					continue;
				}
				$processed[] = [
					'name' => $snapshot->getDate()->forGraph(),
					'draft' => $details['draft'],
					'stable' => $details['stable'],
				];
			}
		}

		return $processed;
	}

	/**
	 * @inheritDoc
	 */
	public function getClientReportHandler(): ClientReportHandler {
		return new ClientReportHandler(
			[ 'ext.bluespice.flaggedrevsconnector.statistics' ],
			'bs.flaggedrevsconnector.report.FlaggedPagesReport'
		);
	}
}
