( function ( mw, $, bs ) {
	bs.util.registerNamespace( 'bs.flaggedrevsconnector.report' );

	bs.flaggedrevsconnector.report.FlaggedPagesReport = function ( cfg ) {
		bs.flaggedrevsconnector.report.FlaggedPagesReport.parent.call( this, cfg );
	};

	OO.inheritClass(
		bs.flaggedrevsconnector.report.FlaggedPagesReport,
		bs.aggregatedStatistics.report.ReportBase
	);

	bs.flaggedrevsconnector.report.FlaggedPagesReport.static.label =
		mw.message( 'bs-flaggedrevsconnector-statistics-report-flagged-pages' ).text();

	bs.flaggedrevsconnector.report.FlaggedPagesReport.prototype.getFilters = function () {
		return [
			new bs.aggregatedStatistics.filter.IntervalFilter(),
			new bs.aggregatedStatistics.filter.NamespaceCategoryFilter( {
				onlyContentNamespaces: true
			} )
		];
	};

	bs.flaggedrevsconnector.report.FlaggedPagesReport.prototype.getChart = function () {
		return new bs.aggregatedStatistics.charts.Groupchart();
	};

	bs.flaggedrevsconnector.report.FlaggedPagesReport.prototype.getAxisLabels = function () {
		return {
			stable: mw.message( 'bs-flaggedrevsconnector-statistics-report-flagged-pages-axis-stable' ).text(),
			draft: mw.message( 'bs-flaggedrevsconnector-statistics-report-flagged-pages-axis-draft' ).text()
		};
	};

}( mediaWiki, jQuery, blueSpice ) );
