/**
 * FlaggedRevsConnector extension
 */

$( document ).on( 'click', '#bs-frc-flagnow, .bs-frc-review', function ( e ) {
	e.preventDefault();
	e.stopPropagation();

	var $sbtItem = $( this ).parents( '*[data-user-can-review]' ),
		allowed = $sbtItem.length > 0 &&
			$sbtItem.data( 'user-can-review' ) !== false &&
			$sbtItem.data( 'user-can-review' ) !== 0;
	if ( !allowed ) {
		return false;
	}

	mw.loader.using( 'ext.bluespice.extjs' ).done( function () {
		Ext.require( 'BS.FlaggedRevsConnector.dialog.Review', function () {
			var dlg = new BS.FlaggedRevsConnector.dialog.Review( {
				id: 'bs-frc-review-dialog',
				revId: mw.config.get( 'wgRevisionId' )
			} );

			dlg.show();
		} );
	} );

	return false;
} );

mw.hook( 'readconfirmation.check.request.before' ).add( function ( data ) {
	data.isStableRevision = true;
	data.stableRevId = mw.config.get( 'wgStableRevisionId' );
	if ( window.location.href.includes( 'stable=0' ) || data.stableRevId === 0 ) {
		data.isStableRevision = false;
	}
} );
