(function () {
    'use strict';
    if (!window.wp || !window.wp.data || !window.wc || !window.wc.blocksCheckout) return;
    var last;
    var busy = false;
    var queued;
    function flush() {
        if (busy || queued === undefined) return;
        var method = queued;
        queued = undefined;
        busy = true;
        window.wc.blocksCheckout.extensionCartUpdate({
            namespace: 'wp-snapppay',
            data: { payment_method: method }
        }).catch(function () {
            // Permit a retry on the next store change; WooCommerce displays API errors.
            last = undefined;
        }).finally(function () {
            busy = false;
            flush();
        });
    }
    function sync() {
        var store = window.wp.data.select('wc/store/payment');
        if (!store || !store.getActivePaymentMethod) return;
        var method = store.getActivePaymentMethod() || '';
        if (method === last) return;
        last = method;
        queued = method;
        flush();
    }
    window.wp.data.subscribe(sync);
    sync();
})();
