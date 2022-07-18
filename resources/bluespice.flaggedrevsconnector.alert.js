( function( mw, $ ) {

	$( function () {
		var $alert = $( '.alert.alert-warning' );

		if ( $alert.length < 1 ) {
			return;
		}
		var btn = OO.ui.infuse( '#bs-flagged-info-btn');

		var infoBtn = new OO.ui.PopupButtonWidget( {
			framed: false,
			icon: 'infoFilled',
			title: mw.message( 'bs-flaggedrevsconnector-state-draft-info-btn-title' ).text(),
			popup: {
				$content: getPopupContent( btn.data ),
				padded: true,
				align: 'force-left'
			}
		} );
		$( '#bs-flagged-info-btn' ).html( infoBtn.$element );

	} );

	function getPopupContent ( data ) {
		var $content = $( '<div>' );
		$content.append( $( '<p>' ).text( mw.message( 'bs-flaggedrevsconnector-state-draft-info-btn-popup-title' ).text() ) );
		var $list = $( '<ul>' ).addClass( 'bsfrc-files-list' );
		for ( var item in data ) {
			$list.append( $( '<li>' ).append( data[item] ) );
		}

		$content.append( $list );
		return $content;
	}
})( mediaWiki, jQuery );
