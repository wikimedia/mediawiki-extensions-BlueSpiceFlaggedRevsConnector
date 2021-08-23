<?php

use BlueSpice\FlaggedRevsConnector\Utils;
use MediaWiki\MediaWikiServices;

return [
	'BSFlaggedRevsConnectorUtils' => function ( MediaWikiServices $services ) {
		return new Utils( $services->getConfigFactory()->makeConfig( 'bsg' ) );
	}
];
