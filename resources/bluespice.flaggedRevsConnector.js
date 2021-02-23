/**
 * FlaggedRevsConnector extension
 */

$( document ).on( 'click', '#bs-frc-flagnow, .bs-frc-review', function( e ) {
	e.preventDefault();
	e.stopPropagation();

	var $sbtItem = $( this ).parents( '*[data-user-can-review]' );
	var allowed = $sbtItem.length > 0
		&& $sbtItem.data( 'user-can-review' ) !== false
		&& $sbtItem.data( 'user-can-review' ) !== 0;
	if ( !allowed ) {
		return false;
	}

	mw.loader.using( 'ext.bluespice.extjs' ).done( function() {
		Ext.require( 'BS.FlaggedRevsConnector.dialog.Review', function() {
			var dlg = new BS.FlaggedRevsConnector.dialog.Review( {
				id: 'bs-frc-review-dialog',
				revId: mw.config.get( 'wgRevisionId' )
			});

			dlg.show();
		});
	});

	return false;
});
