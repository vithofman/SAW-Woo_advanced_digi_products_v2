(function ($) {
    'use strict';

    $(function () {
        // Minimal scaffold for PDP interactions. Full UX will be ported from Oxygen snippet.
        $('.bozp-gallery-thumb').on('click', function (event) {
            event.preventDefault();
            var target = $(this).data('target');
            if (target) {
                $('.bozp-gallery-main').attr('src', target);
            }
        });
    });
})(jQuery);
