<script>

$( function() {

	var kLazyLoad = $K.lazyLoad,
		kLazyLoadInit = $K.lazyLoadInit,
		kPageLoading = false,
		kColNext = 0,
		kColLength = 4,
		themeLazyLoad = function(override) {
			if (override) { kLazyLoad(); }
		},
		themeLazyLoadInit = function(override) {
			$K.lazyLoadInit();
			setTimeout( function() { $K.lazyLoad(true); }, 100);
		}

	$K.lazyLoad = themeLazyLoad;
	$K.lazyLoadInit = kLazyLoadInit;

	var updateGrid = function() {
		kPageLoading = false;
		$('#wrapper .item').each(function(i,item) {
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
			longestCol.find('.item:last').css('display','none');
			if ( longestCol.outerHeight(true) !== shortestCol.outerHeight(true)) {
				longestCol.find('.item:last').appendTo(shortestCol);
			}
			longestCol.find('.item:last').css('display','block');
			themeLazyLoad(true);
		},50);
	}

	$(window).off('.rjs').on('scroll.rjs', function() {
		themeLazyLoad(true);
		if (kPageLoading) { return false; }
		if ( $(document).scrollTop() + $('#grid').offset().top > ($('#grid').offset().top + $('#grid').height()) - $(window).height()*3 || $('.k-lazy-loading').length < 15 ) {
			kPageLoading = true;
			$K.infinity.next();
		}
	});

	$K.infinity.check = $.noop;

	$(window).on('k-infinite-loaded', updateGrid).trigger('k-infinite-loaded');
	$(window).on('k-resize', function() {
		themeLazyLoadInit();
	});

	themeLazyLoadInit();

});

</script>