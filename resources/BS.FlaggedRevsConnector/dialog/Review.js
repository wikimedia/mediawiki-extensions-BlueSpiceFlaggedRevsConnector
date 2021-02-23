Ext.define( 'BS.FlaggedRevsConnector.dialog.Review', {
	extend: 'MWExt.Dialog',
	requires: [ 'Ext.form.field.Text', 'Ext.panel.Panel' ],

	title: mw.message('bs-flaggedrevsconnector-review-heading').text(),
	modal: true,
	closeAction: 'destroy',

	revId: null, //Injected by constructor config

	makeItems: function() {
		this.tfComment = new Ext.form.field.Text({
			fieldLabel: mw.message('bs-flaggedrevsconnector-review-comment-label').text()
		});
		return [
			{
				xtype: 'panel',
				padding: '0 0 10 0',
				html: mw.message('bs-flaggedrevsconnector-review-help' ).text()
			},
			this.tfComment
		];
	},

	onBtnOKClick: function() {
		var me = this;
		this.setLoading(true);

		var api = new mw.Api();
		api.postWithToken( 'csrf', {
			action: 'review',
			revid: this.revId,
			flag_accuracy: 1,
			comment: this.tfComment.getValue()
		})
		.fail(function( response, xhr ){
			bs.util.alert(
				'bs-frc-review-failure',
				{
					textMsg: 'bs-flaggedrevsconnector-response-failure'
				}
			);
			mw.log( me.getId(), response, xhr );
			me.setLoading(false);
		})
		.done( function( response, xhr ) {
			me.fireEvent( 'ok', me, me.getData() );
			me.setLoading(false);
			me.close();

			mw.notify( mw.msg( 'bs-flaggedrevsconnector-response-success' ), { title: mw.msg( 'bs-extjs-title-success' ) } );
			document.location.href = mw.util.getUrl(
				mw.config.get( 'wgPageName' )
			);
		});
	}
});
