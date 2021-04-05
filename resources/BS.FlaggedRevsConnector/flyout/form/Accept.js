Ext.define( 'BS.FlaggedRevsConnector.flyout.form.Accept', {
	extend: 'Ext.form.Panel',
	cls: 'bs-flaggedrevsconnector-flyout-form',
	title: mw.message( 'bs-flaggedrevsconnector-flyout-form-title' ).plain(),
	layout: 'anchor',
	fieldDefaults: {
		anchor: '100%'
	},

	initComponent: function () {
		this.tfComment = new Ext.form.field.Text( {
			emptyText: mw.message( 'bs-flaggedrevsconnector-review-comment-label' ).plain()
		} );

		this.items = [
			this.tfComment
		];

		this.btnConfirm = Ext.create( 'Ext.button.Button', {
			id: this.getId() + '-confirm-btn',
			text: mw.message( 'bs-extjs-confirm' ).plain(),
			handler: this.onBtnConfirmClick,
			flex: 1,
			scope: this
		} );

		this.buttons = [
			this.btnConfirm
		];

		this.callParent( arguments );
	},

	getData: function () {
		return {
			comment: this.tfComment.getValue()
		};
	},

	onBtnConfirmClick: function ( btn, e ) {
		this.fireEvent( 'confirm', this, this.getData() );
	}
} );
