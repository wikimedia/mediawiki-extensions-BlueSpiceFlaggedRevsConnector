( function( mw, $, bs ) {
	Ext.onReady( function(){
		Ext.create( 'BS.FlaggedRevsConnector.grid.FlaggedPages', {
			renderTo: 'bs-flaggedrevsconnector-flagged-pages-grid'
		} );
	} );

} )( mediaWiki, jQuery, blueSpice );
