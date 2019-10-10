/*
	Component: ui.render 
 */
$.extend(UI, {
	render: function(options) {
        options = options || {};
		var seg = (options.segmentToOpen || false);
		this.segmentToScrollAtRender = (seg) ? seg : false;

		this.isSafari = (navigator.userAgent.search("Safari") >= 0 && navigator.userAgent.search("Chrome") < 0);
		this.isChrome = (typeof window.chrome != 'undefined');
		this.isFirefox = (typeof navigator.mozApps != 'undefined');

		this.isMac = (navigator.platform == 'MacIntel') ? true : false;
		this.body = $('body');
		// this.firstLoad = (options.firstLoad || false);
		this.initSegNum = 100; // number of segments initially loaded
		this.moreSegNum = 25;
		this.numOpenedSegments = 0;
		this.maxMinutesBeforeRerendering = 60;
		this.loadingMore = false;
		this.noMoreSegmentsAfter = false;
		this.noMoreSegmentsBefore = false;

		this.undoStack = [];
		this.undoStackPosition = 0;
		this.nextUntranslatedSegmentIdByServer = 0;
		this.checkUpdatesEvery = 180000;
		this.goingToNext = false;
        this.setGlobalTagProjection();
		this.tagModesEnabled = (typeof options.tagModesEnabled != 'undefined')? options.tagModesEnabled : true;
		if(this.tagModesEnabled && !this.enableTagProjection) {
			UI.body.addClass('tagModes');
		} else {
			UI.body.removeClass('tagModes');
		}

        /**
         * Global Translation mismatches array definition.
         */
        this.translationMismatches = [];
        /**
         * Global Warnings array definition.
         */
        this.globalWarnings = [];

		this.readonly = (this.body.hasClass('archived')) ? true : false;


        this.setTagLockCustomizeCookie(true);
        this.debug = false;
		UI.detectStartSegment();
		options.openCurrentSegmentAfter = !!((!seg) && (!this.firstLoad));


		if ( UI.firstLoad ) {

			this.lastUpdateRequested = new Date();

			// setTimeout(function() {
			// 	UI.getUpdates();
			// }, UI.checkUpdatesEvery);

		}

		CatToolActions.renderSubHeader();
		this.renderQualityReportButton();
        return UI.getSegments(options);

	},

	renderQualityReportButton: function() {
		CatToolActions.renderQualityReportButton();
		if ( config.secondRevisionsCount ) {
			UI.reloadQualityReport();
		}
	}
});

