(function ($) {
    'use strict';

    window.sawwapVideoProgress = function (endpoint, data) {
        if (!endpoint) {
            return Promise.resolve();
        }

        return $.post(endpoint, data).fail(function () {
            window.console && window.console.warn('SAW-WAP video progress sync failed.');
        });
    };
})(jQuery);
