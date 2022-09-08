/**
 * FlaggedRevsConnector extension
 */

function openReviewDialog ( $sbtItem ) {
	var allowed = $sbtItem.length > 0 &&
		$sbtItem.data( 'user-can-review' ) !== false &&
		$sbtItem.data( 'user-can-review' ) !== 0;
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
}

$( '#bs-frc-review-link' ).on( 'click', function( e ) {
	e.preventDefault();
	e.stopPropagation();

	return openReviewDialog( $( this ) );
});

$( document ).on( 'keydown', function( e ) {
	var $el = $( '#bs-frc-review-link' );
	if ( $el.length !== 0 && $el.is( ':visible' ) ) {
		if ( e.keyCode === 13 ) {
			return openReviewDialog( $( '#bs-frc-review-link' ) );
		}
	}
});


mw.hook( 'readconfirmation.check.request.before' ).add( function ( data ) {
	data.isStableRevision = true;
	data.stableRevId = mw.config.get( 'wgStableRevisionId' );
	if ( window.location.href.includes( 'stable=0' ) || data.stableRevId === 0 ) {
		data.isStableRevision = false;
	}
});
