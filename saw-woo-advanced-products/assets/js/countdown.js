(function ($) {
    'use strict';

    window.sawwapCountdown = function (selector, targetDate) {
        var $el = $(selector);
        if (!$el.length || !targetDate) {
            return;
        }

        function updateCountdown() {
            var now = new Date().getTime();
            var diff = new Date(targetDate).getTime() - now;
            if (diff <= 0) {
                $el.text('00:00:00');
                clearInterval(interval);
                return;
            }

            var hours = Math.floor(diff / (1000 * 60 * 60));
            var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((diff % (1000 * 60)) / 1000);
            $el.text(String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0'));
        }

        updateCountdown();
        var interval = setInterval(updateCountdown, 1000);
    };
})(jQuery);
