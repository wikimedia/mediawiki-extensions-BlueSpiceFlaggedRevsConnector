Ext.define( 'BS.FlaggedRevsConnector.flyout.Base', {
	extend: 'BS.flyout.TwoColumnsBase',
	requires: [ 'BS.FlaggedRevsConnector.flyout.form.Accept' ],
	flagInfo: null,
	makeCenterTwoItems: function () {
		return [];
	},

	makeCenterOneItems: function () {
		if ( this.flagInfo.userCanReview !== 1 ) {
			return [];
		}

		if ( !this.frcAcceptForm ) {
			this.frcAcceptForm = Ext.create( 'BS.FlaggedRevsConnector.flyout.form.Accept', {} );
			this.frcAcceptForm.on( 'confirm', this.acceptPage, this );
		}

		return [
			this.frcAcceptForm
		];
	},

	makeTopPanelItems: function () {

		var htmlCnt =
			"<div class='bs-flaggedrevsconnector-status-" + this.flagInfo.state + "'>" +
				this.getStateMessage() +
			'</div>',
			panels = [ {
				title: mw.message( 'bs-flaggedrevsconnector-flyout-state-intro' ).plain(),
				html: htmlCnt
			} ];

		if ( this.showPendingChangesHint() ) {
			var url = mw.util.getUrl( mw.config.get( 'wgPageName' ), {
					stable: 0
				} ),
				pending =
				mw.message(
					'bs-flaggedrevsconnector-state-pending-desc',
					url
				).parse(),
				$alert = $( '<div class="alert alert-info" role="alert">' );
			$alert.append( pending );

			panels.push( {
				title: mw.message( 'bs-flaggedrevsconnector-state-pending' ).plain(),
				html: $( '<div>' ).append( $alert ).html()
			} );
		}

		this.flagInfo.resource_changes = this.flagInfo.resource_changes || {};
		if ( Object.keys( this.flagInfo.resource_changes ).length ) {
			var $resourceChanges = $( '<ul>' );
			for ( var title in this.flagInfo.resource_changes ) {
				if ( !Object.prototype.hasOwnProperty.call( this.flagInfo.resource_changes, title ) ) {
					continue;
				}
				var $link = this.flagInfo.resource_changes[ title ];
				$resourceChanges.append( $( '<li>' ).html( $link ) );
			}

			panels.push( {
				title: mw.message( 'bs-flaggedrevsconnector-resource-changes' ).plain(),
				html: $( '<div>' ).append( $resourceChanges ).html()
			} );
		}

		return panels;
	},

	getStateMessage: function () {
		var $status = $( '<div class="alert" role="alert">' );

		if ( this.flagInfo.state === 'stable' ) {
			$status
				.addClass( 'alert-success' )
				.append(
					mw.message( 'bs-flaggedrevsconnector-state-stable' ).plain()
				);
		}

		if ( this.flagInfo.state === 'draft' ) {
			$status
				.addClass( 'alert-warning' )
				.append(
					mw.message( 'bs-flaggedrevsconnector-state-draft' ).plain()
				);
		}

		if ( this.flagInfo.state === 'unmarked' ) {
			$status
				.addClass( 'alert-danger' )
				.append(
					mw.message( 'bs-flaggedrevsconnector-state-unmarked' ).plain()
				);
		}

		return $( '<div>' ).append( $status ).html();
	},

	acceptPage: function ( form, data ) {
		var me = this,
			api = new mw.Api();
		api.postWithToken( 'csrf', {
			action: 'review',
			revid: mw.config.get( 'wgRevisionId' ),
			flag_accuracy: 1,
			comment: data.comment
		} )
			.fail( function ( response, xhr ) {
				bs.util.alert(
					'bs-frc-review-failure',
					{
						textMsg: 'bs-flaggedrevsconnector-response-failure'
					}
				);
				mw.log( me.getId(), response, xhr );
				me.setLoading( false );
			} )
			.done( function ( response, xhr ) {
				mw.notify(
					mw.msg( 'bs-flaggedrevsconnector-response-success' ),
					{
						title: mw.msg( 'bs-extjs-title-success' )
					}
				);

				document.location.href = mw.util.getUrl(
					mw.config.get( 'wgPageName' )
				);
			} );
	},

	showPendingChangesHint: function () {
		return this.flagInfo.pendingchanges && this.flagInfo.state === 'stable';
	}
} );
