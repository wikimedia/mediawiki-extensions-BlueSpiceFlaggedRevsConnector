<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use MWStake\MediaWiki\Component\DataStore\DatabaseReader;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class Reader extends DatabaseReader {

	/**
	 *
	 * @param ReaderParams $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->db, $this->getSchema(), $this->context );
	}

	/**
	 *
	 * @return Schema
	 */
	public function getSchema() {
		return new \BlueSpice\FlaggedRevsConnector\Data\Schema();
	}

	/**
	 *
	 * @return SecondaryDataProvider
	 */
	public function makeSecondaryDataProvider() {
		return new SecondaryDataProvider();
	}

}
