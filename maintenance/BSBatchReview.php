<?php

// How to use:
// Create a TXT file containing the page id's which you wish to review separated by line breaks
// Run the script by "BSBatchReview.php --username <Username> --pageids /path/to/txt/file.txt"

/**
 * @ingroup Maintenance
 */

use MediaWiki\MediaWikiServices;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class BSBatchReview extends Maintenance {

	/**
	 * @var array
	 */
	private $errors = [];

	/**
	 * @var MediaWikiServices
	 */
	private $services;

	/**
	 * @var int
	 */
	private $processed = 0;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'username',
			'The user name of the existing user to use as the "reviewer"', true, true );
		$this->addOption( 'pageids',
			'Flat file containing page ids separated by line break', false, true );
		$this->addOption( 'pages',
			'Flat file containing page names separated by line break', false, true );
		$this->addOption( 'namespace',
			'Id of namespace to flag entirely', false, true );
	}

	public function execute() {
		$user = User::newFromName( $this->getOption( 'username' ) );

		$this->services = MediaWikiServices::getInstance();

		$this->autoreview_current( $user );
	}

	/**
	 *
	 * @param User $user
	 * @return void
	 */
	protected function autoreview_current( User $user ) {
		$this->output( "Auto-reviewing...\n" );
		if ( !$user->getID() ) {
			$this->output( "Invalid user specified.\n" );
			return;
		} else {
			$allowedReview = $this->services->getPermissionManager()->userHasRight(
				$user,
				'review'
			);
			if ( !$allowedReview ) {
				$this->output( "User specified (id: {$user->getID()}) does not have \"review\" rights.\n" );
				return;
			}
		}

		$this->output( "Reviewer username: " . $user->getName() . "\n" );
		$GLOBALS['wgUser'] = $user;

		$flags = FlaggedRevs::quickTags();
		$this->outputFlags( $flags );

		$ids = $this->getPageIds();
		sort( $ids );

		$titles = [];
		foreach ( $ids as $id ) {
			$title = Title::newFromId( $id );
			$titles[$title->getPrefixedDBkey()] = $title;
		}
		ksort( $titles );
		foreach ( $titles as $title ) {
			$this->output( "\nReviewing page {$title->getPrefixedDBkey()}" );
			$this->flagStable( $title, $flags, $user );
		}

		$count = count( $ids );
		$this->output( "\nSuccessfully review {$this->processed} out of $count!\n" );

		if ( !empty( $this->errors ) ) {
			$this->output( "\nERRORS:\n" );
			foreach ( $this->errors as $error ) {
				$this->output( "* $error\n" );
			}
		}
	}

	/**
	 *
	 * @return array
	 */
	private function getPageIds() {
		$path = $this->getOption( 'pageids' );
		if ( $path === null ) {
			$path = $this->getOption( 'pages' );
		}
		if ( $path !== null ) {
			return $this->getPageIdsFromFlatFile( $path );
		}

		$namespace = $this->getOption( 'namespace' );
		if ( $namespace !== null ) {
			return $this->getPageIdsNamespace( (int)$namespace );
		}

		return [];
	}

	/**
	 *
	 * @param string $path
	 * @return int[]
	 */
	private function getPageIdsFromFlatFile( $path ) {
		$fileContent = trim( file_get_contents( $path ) );
		$lines = explode( "\n", $fileContent );
		$ids = [];
		foreach ( $lines as $linenumber => $line ) {
			$trimmedLine = trim( $line );
			if ( is_int( $trimmedLine ) ) {
				$title = \Title::newFromID( $trimmedLine );
			} else {
				$title = \Title::newFromText( $trimmedLine );
			}

			if ( $title instanceof \Title === false ) {
				$this->errors[] = "LINE $linenumber: Could not create valid title from"
					. " '$trimmedLine'!";
				continue;
			}

			if ( $title->exists() === false ) {
				$this->errors[] = "LINE $linenumber: Title '{$title->getPrefixedDBkey()}' "
					. " does not exist!";
				continue;
			}
			$ids[] = $title->getArticleID();
		}
		return $ids;
	}

	/**
	 *
	 * @param int $nsId
	 * @return int[]
	 */
	private function getPageIdsNamespace( $nsId ) {
		$dbr = $this->getDB( DB_REPLICA );
		$res = $dbr->select( 'page', 'page_id', [ 'page_namespace' => $nsId ], __METHOD__ );
		$ids = [];
		foreach ( $res as $row ) {
			$ids[] = $row->page_id;
		}
		return $ids;
	}

	/**
	 *
	 * @param array $flags
	 */
	private function outputFlags( $flags ) {
		$tags = FlaggedRevision::flattenRevisionTags( $flags );
		$this->output( "Using tier 'quality' with tags:\n$tags\n" );
	}

	/**
	 *
	 * @param \Title $title
	 * @param array $flags
	 * @param \User $user
	 */
	private function flagStable( $title, $flags, $user ) {
		$lookup = $this->services->getRevisionLookup();
		$deprecatedRevision = $lookup->getRevisionById( $title->getLatestRevID() );

		$form = new \RevisionReviewForm( $user );
		$form->setPage( $title );
		$form->setOldId( $title->getLatestRevID() );
		$form->setAction( RevisionReviewForm::ACTION_APPROVE );

		foreach ( $flags as $tag => $level ) {
			$form->setDim( $tag, $level );
		}

		$article = new \FlaggableWikiPage( $title );
		// Now get the template and image parameters needed
		[ $templateIds, $fileTimeKeys ] =
			\FRInclusionCache::getRevIncludes( $article, $deprecatedRevision, $user );
		// Get version parameters for review submission (flat strings)
		$templateParams = \RevisionReviewForm::getIncludeParams( $templateIds );
		// Set the version parameters...
		$form->setTemplateParams( $templateParams );
		// always OK; uses current templates/files
		$form->bypassValidationKey();

		// all params set
		$form->ready();

		# Try to do the actual review
		$status = $form->submit();
		if ( $status === true ) {
			$this->output( " --> OK" );
			$this->processed++;
		} else {
			$this->output( " --> FAILED: $status" );
			$this->errors[] = "Failed to review {$title->getPrefixedDBkey()}: $status";
		}
	}

}

$maintClass = BSBatchReview::class;
require_once RUN_MAINTENANCE_IF_MAIN;
