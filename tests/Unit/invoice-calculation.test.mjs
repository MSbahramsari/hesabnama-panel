import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateRialLine } from '../../resources/js/invoice-calculation.js';

test('truncates the reported invoice VAT without rounding up', () => {
    assert.deepEqual(calculateRialLine('1', '1,818,181,818', '10', '0'), {
        subtotal: 1818181818n, discount: 0n, tax: 181818181n, total: 1999999999n,
    });
});

test('preserves eight decimal inputs and fractional tax rates', () => {
    assert.deepEqual(calculateRialLine('10', '1.99999999', '7.25', '1.99'), {
        subtotal: 19n, discount: 1n, tax: 1n, total: 19n,
    });
    assert.equal(calculateRialLine('۰٫۰۰۰۰۰۰۰۱', '۱۰۰٬۰۰۰٬۰۰۰', '0', '').subtotal, 1n);
});

test('does not lose precision beyond the binary float safe integer range', () => {
    assert.equal(calculateRialLine('1', '9007199254740993', '0', '').total, 9007199254740993n);
});

test('sums truncated rows rather than rounding a grand fractional VAT total', () => {
    const first = calculateRialLine('1', '1818181818', '10', '0');
    const second = calculateRialLine('1', '1818181818', '10', '0');
    assert.equal(first.tax + second.tax, 363636362n);
    assert.equal(first.total + second.total, 3999999998n);
});
