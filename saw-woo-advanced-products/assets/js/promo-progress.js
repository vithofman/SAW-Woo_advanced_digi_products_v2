(function ($) {
    'use strict';

    window.sawwapPromoProgress = function (selector, value, max) {
        var $bar = $(selector);
        if (!$bar.length || max <= 0) {
            return;
        }

        var percent = Math.min(100, Math.max(0, (value / max) * 100));
        $bar.css('width', percent + '%');
        $bar.attr('aria-valuenow', percent);
    };
})(jQuery);
