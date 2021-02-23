<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

class Reader extends \BlueSpice\Data\DatabaseReader {

	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->db, $this->getSchema(), $this->context );
	}

	public function getSchema() {
		return new \BlueSpice\FlaggedRevsConnector\Data\Schema();
	}

	public function makeSecondaryDataProvider() {
		return new SecondaryDataProvider();
	}

}
