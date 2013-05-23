$( function() {

	var cols	= $('.elem'),
		output	= [];

	$('.item').each( function(v,k) {

		var col		= v % cols.length,
			html	= output[col] || [];

		html[html.length]	= k;
		output[col]			= html;

		$(k).remove();

	});

	cols.each( function(v,k) {

		var str = '';

		$.each(output[v], function(j,w) {
			str += w.outerHTML;
		});

		k.innerHTML = str;

	});

});