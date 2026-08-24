/**
 * Shared loading helpers are intentionally no-ops.
 * SK Federations uses the browser's default loading indicator only.
 */
(function () {
    var noop = function () {};

    window.LoadingScreen = {
        init: noop,
        create: noop,
        show: noop,
        hide: noop,
        hideImmediate: noop,
        showWithDelay: noop,
    };

    window.showLoading = noop;
    window.hideLoading = noop;
})();
