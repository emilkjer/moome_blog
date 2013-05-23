$(function() {
	$(window).on('resize',resizeView);
	function resizeView() {
		$('#container').css({
			top: ( $('header.main').css('position') === 'fixed' ) ? $('header').height() : '0',
			paddingBottom: ( $('footer.main').css('position') === 'fixed' ) ? $('footer').outerHeight(true) + 10 : '0'
		});
	}
	resizeView();
});