Ext.define( 'BS.FlaggedRevsConnector.grid.FlaggedPages', {
	extend: 'Ext.grid.Panel',
	requires: [ 'BS.store.BSApi' ],
	plugins: 'gridfilters',
	wasLoaded: false,

	initComponent: function () {
		this.columns = this.makeColumns();
		this.store = this.makeStore();

		this.bbar = this.makeBBar();

		$( document ).trigger( 'BSFlaggedRevsConnectorGridInit', [ this ] );
		this.callParent( arguments );
	},

	makeColumns: function () {
		var availableStates = mw.config.get( 'bsgFlaggedRevConnectorAvailableStates' ),
			defaultValue = [];
		// Long syntax for IE...
		for ( var state in availableStates ) {
			if ( state !== 'notenabled' ) {
				defaultValue.push( availableStates[ state ] );
			}
		}

		var categoryFilter = {
			type: 'string'
		};

		if ( mw.util.getParamValue( 'category' ) !== null ) {
			categoryFilter.value = mw.util.getParamValue( 'category' );
		}

		if ( mw.util.getParamValue( 'state' ) !== null ) {
			var explicitState = mw.util.getParamValue( 'state' );
			defaultValue = [ availableStates[ explicitState ] ];
		}

		return [
			{
				text: mw.message( 'bs-flaggedrevsconnector-grid-page-id' ).text(),
				dataIndex: 'page_id',
				sortable: true,
				hidden: true,
				filter: {
					type: 'numeric'
				}
			}, {
				text: mw.message( 'bs-flaggedrevsconnector-grid-page-title' ).text(),
				dataIndex: 'page_title',
				sortable: true,
				flex: 1,
				filterable: true,
				filter: {
					type: 'string'
				},
				renderer: function ( value, metaData, record, rowIndex, colIndex, store, view ) {
					return record.get( 'page_link' );
				}
			}, {
				text: mw.message( 'bs-flaggedrevsconnector-grid-rev-state' ).text(),
				dataIndex: 'revision_state',
				sortable: true,
				filterable: true,
				width: 200,
				filter: {
					type: 'list',
					options: Object.values( availableStates ),
					value: defaultValue
				}
			}, {
				text: mw.message( 'bs-flaggedrevsconnector-grid-page-categories' ).text(),
				dataIndex: 'page_categories',
				sortable: true,
				filterable: true,
				width: 200,
				filter: categoryFilter,
				renderer: function ( value, metaData, record, rowIndex, colIndex, store, view ) {
					return record.get( 'page_categories_links' ).join( ', ' );
				}
			}, {
				text: mw.message( 'bs-flaggedrevsconnector-grid-pending-revs' ).text(),
				dataIndex: 'revs_since_stable',
				hidden: true
			}
		];
	},

	makeStore: function () {
		return new BS.store.BSApi( {
			apiAction: 'bs-flaggedpages-store',
			sorters: [ {
				property: 'page_title',
				direction: 'ASC'
			} ],
			remoteFilter: true,
			proxy: {
				extraParams: {
					limit: 25
				}
			},
			remoteSort: true,
			pageSize: 25,
			listeners: {
				beforeLoad: function () {
					if ( this.wasLoaded ) {
						return;
					}
					this.mask( mw.message( 'bs-extjs-loading' ).text() );
					this.wasLoaded = true;
				}.bind( this ),
				load: function () {
					this.unmask();
				}.bind( this )
			}
		} );
	},

	makeBBar: function () {
		return new Ext.PagingToolbar( {
			store: this.store,
			displayInfo: true
		} );
	},

	getHTMLTable: function () {
		var dfd = $.Deferred(),
			store = this.makeStore(),
			proxy = store.getProxy();
		proxy.extraParams.limit = 999999;
		store.setProxy( proxy );
		store.load( { callback: function ( records, operation, success ) {
			if ( !operation.success ) {
				return dfd.reject( operation );
			}
			var $table = $( '<table>' ),
				$row = $( '<tr>' ),
				$cell = $( '<td>' );
			$cell.append(
				mw.message( 'bs-flaggedrevsconnector-grid-page-id' ).text()
			);
			$row.append( $cell );

			$cell = $( '<td>' );
			$cell.append(
				mw.message( 'bs-flaggedrevsconnector-grid-page-title' ).plain()
			);
			$row.append( $cell );

			$cell = $( '<td>' );
			$cell.append(
				mw.message( 'bs-flaggedrevsconnector-grid-page-namespace' ).plain()
			);
			$row.append( $cell );

			$cell = $( '<td>' );
			$cell.append(
				mw.message( 'bs-flaggedrevsconnector-grid-rev-state' ).plain()
			);
			$row.append( $cell );

			$cell = $( '<td>' );
			$cell.append(
				mw.message( 'bs-flaggedrevsconnector-grid-pending-revs' ).plain()
			);
			$row.append( $cell );

			$cell = $( '<td>' );
			$cell.append(
				mw.message( 'bs-flaggedrevsconnector-grid-page-categories' ).plain()
			);
			$row.append( $cell );

			$table.append( $row );

			for ( var rid = 0; rid < records.length; rid++ ) {
				var record = records[ rid ];
				$row = $( '<tr>' );

				$cell = $( '<td>' );
				$cell.append( record.data.page_id );
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append( record.data.page_title );
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append( record.data.page_namespace );
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append( record.data.revision_state );
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append( record.data.revs_since_stable );
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append( record.data.page_categories_links.join( ', ' ) );
				$row.append( $cell );

				$table.append( $row );
			}

			dfd.resolve( '<table>' + $table.html() + '</table>' );
		} } );

		return dfd;
	}
} );
