<?php

$maintPath = ( getenv( 'MW_INSTALL_PATH' ) !== false
			  ? getenv( 'MW_INSTALL_PATH' )
			  : __DIR__ . '/../..' ) . '/BlueSpiceMaintenance/maintenance/BSBatchFileProcessorBase.php';
if ( !file_exists( $maintPath ) ) {
	echo "Please set the environment variable MW_INSTALL_PATH "
		. "to your MediaWiki installation.\n";
	exit( 1 );
}
require_once $maintPath;

class BSImportStableFlags extends BSBatchFileProcessorBase {

	/**
	 *
	 * @var DerivativeContext
	 */
	protected $context = null;

	public function __construct() {
		$this->addOption( 'src', 'The Import-XML file that contains flag-data', true, true );
	}

	public function execute() {
		$this->context = new DerivativeContext( \RequestContext::getMain() );
		$dummyUser = User::newFromName( 'WikiSysop' );
		$this->context->setUser( $dummyUser );
		// FlaggedRevs uses $wgUser ...
		$GLOBALS['wgUser'] = $dummyUser;

		$path = $this->getOption( 'src' );
		$dom = new DOMDocument();
		$dom->load( $path );

		$stabledates = $dom->getElementsByTagName( 'stabledate' );
		foreach ( $stabledates as $stabledate ) {
			$titletext = $this->getTitleText( $stabledate );
			$title = Title::newFromText( $titletext );
			if ( $title instanceof Title === false ) {
				$this->error( "Could not create title from '$titletext'!" );
				continue;
			}
			$timestamp = $stabledate->nodeValue;

			$this->output( "Setting '{$title->getPrefixedDBkey()}' stable date to $timestamp" );
			$this->flagStable( $title, $timestamp );
		}
	}

	/**
	 * Paths:
	 * - page/revision/stabledate
	 * - page/title
	 * @param DOMElement $stabledate
	 * @return string
	 */
	protected function getTitleText( $stabledate ) {
		$page = $stabledate->parentNode->parentNode;
		$title = $page->getElementsByTagName( 'title' )->item( 0 );

		return $title->nodeValue;
	}

	/**
	 *
	 * @param Title $title
	 * @param string $timestamp
	 */
	protected function flagStable( $title, $timestamp ) {
		$this->context->setRequest(
			$this->makeDerivativeRequest( $title->getLatestRevID() )
		);
		$api = new ApiMain( $this->context, true );

		$api->execute();
		$data = $api->getResult()->getResultData();

		if ( isset( $data['review']['result'] )
			&& $data['review']['result'] === 'Success' ) {
			$this->output( 'Success!' );
		} else {
			$this->output( 'Failed!' );
		}
	}

	/**
	 *
	 * @param int $revId
	 * @return \DerivativeRequest
	 */
	protected function makeDerivativeRequest( $revId ) {
		return new DerivativeRequest(
			$this->context->getRequest(),
			[
				'action' => 'review',
				'revid' => $revId,
				'flag_accuracy' => 1,
				'comment' => "Autoreviewd by " . __CLASS__,
				'token' => $this->context->getUser()->getEditToken()
			]
		);
	}

}

$maintClass = 'BSImportStableFlags';
require_once RUN_MAINTENANCE_IF_MAIN;
