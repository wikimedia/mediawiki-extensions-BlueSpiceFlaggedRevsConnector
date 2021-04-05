( function ( mw, $, bs ) {
	bs.util.registerNamespace( 'bs.flaggedrevsconnector' );

	bs.flaggedrevsconnector.flyoutCallback = function ( $body, data ) {
		var dfd = $.Deferred();
		Ext.create( 'BS.FlaggedRevsConnector.flyout.Base', {
			renderTo: $body[ 0 ],
			flagInfo: data
		} );

		dfd.resolve();
		return dfd.promise();
	};

}( mediaWiki, jQuery, blueSpice ) );
