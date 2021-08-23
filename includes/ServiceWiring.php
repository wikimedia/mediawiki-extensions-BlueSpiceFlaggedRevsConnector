<?php

use BlueSpice\FlaggedRevsConnector\Utils;
use MediaWiki\MediaWikiServices;

return [
	'BSFlaggedRevsConnectorUtils' => static function ( MediaWikiServices $services ) {
		return new Utils( $services->getConfigFactory()->makeConfig( 'bsg' ) );
	}
];
