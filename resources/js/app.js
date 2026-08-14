const sidebar = document.querySelector('[data-sidebar]');
const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');

const setSidebarOpen = (isOpen) => {
    sidebar?.classList.toggle('is-open', isOpen);
    sidebarBackdrop?.classList.toggle('hidden', !isOpen);
    document.body.classList.toggle('overflow-hidden', isOpen);
};

document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => setSidebarOpen(true));
sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebarOpen(false);
    }
});

document.querySelectorAll('[data-navigate]').forEach((element) => {
    element.addEventListener('click', () => window.location.assign(element.dataset.navigate));
});

const roleSelect = document.querySelector('#role');
const taxpayerProfile = document.querySelector('[data-taxpayer-profile]');

if (roleSelect && taxpayerProfile) {
    const syncTaxpayerRequirements = () => {
        const isRequired = roleSelect.value !== 'admin';

        taxpayerProfile.querySelectorAll('[data-taxpayer-required]').forEach((field) => {
            field.required = isRequired;
        });
        taxpayerProfile.classList.toggle('opacity-60', !isRequired);
    };

    roleSelect.addEventListener('change', syncTaxpayerRequirements);
    syncTaxpayerRequirements();
}

const selectAllInvoices = document.querySelector('[data-select-all-invoices]');
selectAllInvoices?.addEventListener('change', () => {
    document.querySelectorAll('[data-invoice-checkbox]:not(:disabled)').forEach((checkbox) => {
        checkbox.checked = selectAllInvoices.checked;
    });
});

const catalogImportForm = document.querySelector('[data-catalog-import-form]');

if (catalogImportForm) {
    const progressPanel = catalogImportForm.querySelector('[data-upload-progress]');
    const progressBar = catalogImportForm.querySelector('[data-upload-progress-bar]');
    const progressPercent = catalogImportForm.querySelector('[data-upload-percent]');
    const progressStatus = catalogImportForm.querySelector('[data-upload-status]');
    const progressHint = catalogImportForm.querySelector('[data-upload-hint]');
    const submitButton = catalogImportForm.querySelector('[data-upload-submit]');

    catalogImportForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const request = new XMLHttpRequest();
        const formData = new FormData(catalogImportForm);

        progressPanel.classList.remove('hidden');
        submitButton.disabled = true;
        submitButton.classList.add('cursor-not-allowed', 'opacity-60');
        progressStatus.textContent = 'در حال بارگذاری فایل روی سرور...';
        progressHint.textContent = 'تا پایان عملیات این صفحه را نبندید.';

        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                return;
            }

            const percentage = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100));
            progressBar.style.width = `${percentage}%`;
            progressPercent.textContent = `${percentage}%`;
        });

        request.upload.addEventListener('load', () => {
            progressBar.style.width = '100%';
            progressPercent.textContent = '100%';
            progressStatus.textContent = 'آپلود کامل شد؛ در حال پردازش و ورود اطلاعات...';
            progressHint.textContent = 'پردازش فایل بزرگ ممکن است چند دقیقه زمان ببرد.';
            progressBar.classList.add('animate-pulse');
        });

        request.addEventListener('load', () => {
            if (request.status >= 200 && request.status < 400) {
                window.location.assign(request.responseURL || catalogImportForm.action);

                return;
            }

            progressStatus.textContent = 'بروزرسانی انجام نشد.';
            progressHint.textContent = request.status === 413
                ? 'حجم فایل از سقف مجاز وب‌سرور بیشتر است.'
                : 'خطایی در ارسال یا پردازش فایل رخ داد؛ دوباره تلاش کنید.';
            progressBar.classList.remove('animate-pulse');
            progressBar.classList.add('bg-rose-600');
            submitButton.disabled = false;
            submitButton.classList.remove('cursor-not-allowed', 'opacity-60');
        });

        request.addEventListener('error', () => {
            progressStatus.textContent = 'ارتباط با سرور قطع شد.';
            progressHint.textContent = 'اتصال شبکه را بررسی و دوباره تلاش کنید.';
            progressBar.classList.remove('animate-pulse');
            progressBar.classList.add('bg-rose-600');
            submitButton.disabled = false;
            submitButton.classList.remove('cursor-not-allowed', 'opacity-60');
        });

        request.open('POST', catalogImportForm.action);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.send(formData);
    });
}

const invoiceForm = document.querySelector('[data-invoice-form]');

if (invoiceForm) {
    const itemsContainer = invoiceForm.querySelector('[data-invoice-items]');
    const template = document.querySelector('#invoice-item-template');
    const formatter = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 });
    let nextIndex = itemsContainer.querySelectorAll('[data-invoice-item]').length;

    const money = (value) => `${formatter.format(Math.round(value || 0))} ریال`;

    const recalculate = () => {
        let subtotal = 0;
        let discountTotal = 0;
        let taxTotal = 0;

        itemsContainer.querySelectorAll('[data-invoice-item]').forEach((row, position) => {
            row.querySelector('.invoice-item-number').textContent = String(position + 1);
            const quantity = Number(row.querySelector('[data-quantity]')?.value || 0);
            const unitPrice = Number(row.querySelector('[data-unit-price]')?.value || 0);
            const taxRate = Number(row.querySelector('[data-tax-rate]')?.value || 0);
            const lineSubtotal = quantity * unitPrice;
            const discount = Math.min(Number(row.querySelector('[data-discount]')?.value || 0), lineSubtotal);
            const tax = (lineSubtotal - discount) * taxRate / 100;
            const total = lineSubtotal - discount + tax;

            row.querySelector('[data-line-total]').textContent = money(total);
            subtotal += lineSubtotal;
            discountTotal += discount;
            taxTotal += tax;
        });

        invoiceForm.querySelector('[data-invoice-subtotal]').textContent = money(subtotal);
        invoiceForm.querySelector('[data-invoice-discount]').textContent = money(discountTotal);
        invoiceForm.querySelector('[data-invoice-tax]').textContent = money(taxTotal);
        invoiceForm.querySelector('[data-invoice-total]').textContent = money(subtotal - discountTotal + taxTotal);
    };

    const bindRow = (row) => {
        row.querySelector('[data-good-select]')?.addEventListener('change', (event) => {
            const option = event.target.selectedOptions[0];
            row.querySelector('[data-unit-price]').value = option?.dataset.price || 0;
            row.querySelector('[data-tax-rate]').value = option?.dataset.tax || 0;
            recalculate();
        });

        row.querySelectorAll('input').forEach((input) => input.addEventListener('input', recalculate));
        row.querySelector('[data-remove-invoice-item]')?.addEventListener('click', () => {
            if (itemsContainer.querySelectorAll('[data-invoice-item]').length > 1) {
                row.remove();
                recalculate();
            }
        });
    };

    itemsContainer.querySelectorAll('[data-invoice-item]').forEach(bindRow);
    invoiceForm.querySelector('[data-add-invoice-item]')?.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-invoice-item]');

        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `items[${nextIndex}][${field.dataset.field}]`;
        });
        nextIndex += 1;
        itemsContainer.appendChild(fragment);
        bindRow(itemsContainer.lastElementChild);
        recalculate();
    });

    recalculate();
}
