$( function() {

	var kLazyLoad = $K.lazyLoad,
		kLazyLoadInit = $K.lazyLoadInit,
		kPageLoading = false,
		kColNext = 0,
		kColLength = parseInt( $('#grid').attr('class').replace('col-',''), 10 ),
		regaleLazyLoad = function(override) {
			if (override) { kLazyLoad(); }
		},
		regaleLazyLoadInit = function(override) {
			$K.lazyLoadInit();
			setTimeout( function() { $K.lazyLoad(true); }, 100);
		}

	$K.lazyLoad = regaleLazyLoad;
	$K.lazyLoadInit = kLazyLoadInit;

	(function() {
		var colContainer = '';
		for ( var i = 0, len = kColLength; i < len; i++ ) {
			colContainer += $('#grid').html();
		}
		$('#grid').html(colContainer);
	})();

	var updateGrid = function() {
		kPageLoading = false;
		$('#container > .item').each(function(i,item) {
			$(item).appendTo($('.column:eq('+kColNext+')'));
			kColNext = (kColNext+1 >= kColLength) ? 0 : kColNext+1;
		});
		window.setTimeout(function() {
			var longestCol, shortestCol;
			$('.column').each(function(i,column) {
				if (!longestCol || $(column).outerHeight(true) > longestCol.outerHeight(true)) {
					longestCol = $(column);
				}
				if (!shortestCol || $(column).outerHeight(true) < shortestCol.outerHeight(true)) {
					shortestCol = $(column);
				}
			});
			var lastItem = longestCol.find('.item:last');
			lastItem.css('display','none');
			window.setTimeout(function() {
				if (longestCol.outerHeight(true) - shortestCol.outerHeight(true) > shortestCol.outerHeight(true)/3) {
					lastItem.appendTo(shortestCol);
				}
				lastItem.css('display','block');
				regaleLazyLoad(true);
			},1);
		},50);
	}

	$(window).off('.rjs').on('scroll.rjs', function() {
		regaleLazyLoad(true);
		if (kPageLoading) { return false; }
		if ( $(document).scrollTop() + $('#grid').offset().top > ($('#grid').offset().top + $('#grid').height()) - $(window).height()*3 || $('.k-lazy-loading').length < 15 ) {
			kPageLoading = true;
			$K.infinity.next();
		}
	});

	$K.infinity.check = $.noop;

	$(window).on('k-infinite-loaded', updateGrid).trigger('k-infinite-loaded');
	$(window).on('k-resize', function() {
		regaleLazyLoadInit();
	});

	regaleLazyLoadInit();

});