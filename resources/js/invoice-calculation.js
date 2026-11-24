export const normalizeInvoiceNumber = (value) => String(value ?? '')
    .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
    .replace(/[,٬\s]/g, '')
    .replace(/٫/g, '.')
    .replace(/[^0-9.]/g, '');

const scaledValue = (value, scale = 0) => {
    const normalized = normalizeInvoiceNumber(value);
    if (!/^\d*(\.\d*)?$/.test(normalized)) return 0n;
    const [whole = '', fraction = ''] = normalized.split('.');
    return BigInt((whole || '0') + fraction.padEnd(scale, '0').slice(0, scale));
};

export const calculateRialLine = (quantity, unitPrice, taxRate, enteredDiscount) => {
    const subtotal = scaledValue(quantity, 8) * scaledValue(unitPrice, 8) / 10000000000000000n;
    const requestedDiscount = scaledValue(enteredDiscount);
    const discount = requestedDiscount < subtotal ? requestedDiscount : subtotal;
    const tax = (subtotal - discount) * scaledValue(taxRate, 2) / 10000n;
    return { subtotal, discount, tax, total: subtotal - discount + tax };
};
