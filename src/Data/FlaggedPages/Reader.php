<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

class Reader extends \BlueSpice\Data\DatabaseReader {

	/**
	 *
	 * @param \BlueSpice\Data\ReaderParams $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->db, $this->getSchema(), $this->context );
	}

	/**
	 *
	 * @return \BlueSpice\FlaggedRevsConnector\Data\Schema
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
