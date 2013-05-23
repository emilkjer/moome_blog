$(function() {

    var opts = {
        pinterest: {
            height: 525
        },
        google: {
            height: 400
        }
    },
    delay = 400;

    $('.social-links li').on('click', function() {
        var link = $(this).find('a'),
            type = link.attr('class').replace('share-', '');
        window.open( link.attr('href'), '_blank', 'width=560,height=' + (opts[type] && opts[type].height || 450) );
        return false;
    });

});