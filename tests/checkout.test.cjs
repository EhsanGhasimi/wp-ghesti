const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

(async () => {
    let method = 'snappay';
    let listener;
    const calls = [];
    const complete = [];
    const context = {
        window: {
            wp: { data: {
                select: () => ({ getActivePaymentMethod: () => method }),
                subscribe: callback => { listener = callback; }
            } },
            wc: { blocksCheckout: {
                extensionCartUpdate: payload => {
                    calls.push(payload.data.payment_method);
                    return new Promise(resolve => complete.push(resolve));
                }
            } }
        }
    };
    vm.runInNewContext(fs.readFileSync('wp-snapppay/assets/blocks.js', 'utf8'), context);
    assert.deepEqual(calls, ['snappay']);
    listener();
    assert.equal(calls.length, 1, 'unchanged selection must not trigger another request');
    method = 'bacs'; listener();
    method = 'cod'; listener();
    assert.equal(calls.length, 1, 'updates must be serialized');
    complete.shift()();
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(calls, ['snappay', 'cod'], 'rapid changes must apply the latest selection');
    complete.shift()();
    await new Promise(resolve => setImmediate(resolve));
    method = 'snappay'; listener();
    assert.deepEqual(calls, ['snappay', 'cod', 'snappay']);
    complete.shift()();
    await new Promise(resolve => setImmediate(resolve));

    let handler;
    let refreshes = 0;
    const body = {};
    const $ = target => ({
        on: (event, selector, callback) => { assert.equal(selector, 'input[name="payment_method"]'); handler = callback; },
        trigger: event => { assert.equal(event, 'update_checkout'); refreshes++; }
    });
    vm.runInNewContext(fs.readFileSync('wp-snapppay/assets/classic.js', 'utf8'), { jQuery: $, document: { body } });
    handler();
    assert.equal(refreshes, 1);
    console.log('PASS: initial method, unchanged selection, rapid switching, return to Snappay, classic refresh.');
})().catch(error => { console.error(error); process.exitCode = 1; });
