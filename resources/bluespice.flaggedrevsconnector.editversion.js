// Override displayed revision with latest (current) revision id,
// in order to edit the latest draft
mw.config.set( 'wgRevisionId', mw.config.get( 'wgCurRevisionId' ) );
window.wgRevisionId = mw.config.get( 'wgCurRevisionId' );
