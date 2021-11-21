<?php

namespace BlueSpice\FlaggedRevsConnector\Data\FlaggedPages;

use FlaggedRevision;
use Title;
use Wikimedia\Rdbms\IDatabase;

/**
 * Class which works with title FlaggedRevs data without loading all information about title.
 * All necessary for methods FlaggedRevs data is got as methods args, so it can be pre-loaded in
 * case of large grids.
 * Any new FlaggedRevs methods used for bulk pages processing - can be added here.
 *
 * @see \BlueSpice\FlaggedRevsConnector\Data\FlaggedPages\PrimaryDataProvider::getFlaggedRevsOutput
 */
class FlaggedPageData {

	/**
	 * Current processing title
	 *
	 * @var Title
	 */
	private $title;

	/**
	 * Database connection to use
	 *
	 * @var IDatabase
	 */
	private $db;

	/**
	 * @param Title $title
	 * @param IDatabase $db
	 */
	public function __construct( Title $title, IDatabase $db ) {
		$this->title = $title;
		$this->db = $db;
	}

	/**
	 * Get the stable revision
	 *
	 * @param int $stableRevId
	 * @return FlaggedRevision|null
	 */
	public function getStableRev( $stableRevId ) {
		$stableRev = FlaggedRevision::newFromTitle( $this->title, $stableRevId );

		return $stableRev;
	}

	/**
	 * Get number of revs since the stable revision
	 *
	 * @param int $stableRevId ID of stable revision
	 * @return int|bool <tt>false</tt> if there are no pending revisions, integer otherwise
	 */
	public function getPendingRevCount( $stableRevId ) {
		$stableRev = $this->getStableRev( $stableRevId );
		if ( !$stableRev ) {
			// none
			return 0;
		}

		$stableRevTimestamp = $this->db->timestamp( $stableRev->getRevTimestamp() );

		$pendingRevCount = $this->db->selectField(
			'revision',
			'COUNT(*)',
			[
				'rev_page' => $this->title->getArticleID(),
				'rev_timestamp > ' . $this->db->addQuotes( $stableRevTimestamp )
			],
			__METHOD__
		);

		return $pendingRevCount;
	}

}
