$(function() {

    var opts = {
        pinterest: {
            height: 525
        },
        google: {
            height: 400
        }
    },
    delay = 400,
    mq;

    var toggleMenu = function(which) {
        var shareEl = this.closest('div.share_content');
        if (!which) {
            which = (shareEl.find('.kshare').css('display') !== 'block') ? 'open' : 'close';
        }
        if (which === 'open') {
            shareEl.find('.kshare').data('hidden', false).fadeIn('fast');
            shareEl.find('.share_button').addClass('active');
            this.data('in', true);
        } else if (which === 'close') {
            var closeMenu = function() {
                if (!shareEl.find('.share_button').data('in') && !shareEl.find('.kshare').data('in') && !shareEl.find('.kshare').data('hidden')) {
                    shareEl.find('.kshare').data('hidden', true).fadeOut('fast');
                    shareEl.find('.share_button').removeClass('active');
                }
            }
            this.data('in', false);
            if (mq) {
                window.setTimeout(function() { closeMenu(); }, delay);
            } else {
                closeMenu();
            }
        }
    }

    var initHandlers = function() {
        $(document).off('.kshare').on('mouseenter.kshare mouseleave.kshare click.kshare', '.share_button, .kshare', function(e) {
            var _el = $(this);
            switch(e.type) {
                case 'click' :
                    if (!mq) { toggleMenu.call(_el); }
                    break;
                case 'mouseenter' :
                    if (mq) { toggleMenu.call(_el, 'open'); }
                    break;
                case 'mouseleave' :
                    if (mq) { toggleMenu.call(_el, 'close'); }
                    break;
            }

            return false;
        });
    }

    $('.kshare-items li').on('click', function() {
        var link = $(this).find('a'),
            type = link.attr('class').replace('share-', '');
        window.open( link.attr('href'), '_blank', 'width=560,height=' + (opts[type] && opts[type].height || 450) );
        return false;
    });

    $(window).on('resize', function() {
        mq = window.matchMedia("(min-width: 960px)").matches;
        initHandlers();
    }).trigger('resize');

    initHandlers();

});