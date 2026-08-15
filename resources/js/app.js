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
        closeOpenJalaliPicker();
    }
});

document.querySelectorAll('[data-navigate]').forEach((element) => {
    element.addEventListener('click', () => window.location.assign(element.dataset.navigate));
});

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const normalizeDateDigits = (value) => String(value || '').replace(/[۰-۹٠-٩]/g, (digit) => ({
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
})[digit]);

const gregorianToJalali = (gregorianYear, gregorianMonth, gregorianDay) => {
    const monthDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    let jalaliYear;

    if (gregorianYear > 1600) {
        jalaliYear = 979;
        gregorianYear -= 1600;
    } else {
        jalaliYear = 0;
        gregorianYear -= 621;
    }

    const adjustedYear = gregorianMonth > 2 ? gregorianYear + 1 : gregorianYear;
    let days = (365 * gregorianYear)
        + Math.floor((adjustedYear + 3) / 4)
        - Math.floor((adjustedYear + 99) / 100)
        + Math.floor((adjustedYear + 399) / 400)
        - 80
        + gregorianDay
        + monthDays[gregorianMonth - 1];

    jalaliYear += 33 * Math.floor(days / 12053);
    days %= 12053;
    jalaliYear += 4 * Math.floor(days / 1461);
    days %= 1461;

    if (days > 365) {
        jalaliYear += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }

    const jalaliMonth = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    const jalaliDay = days < 186 ? 1 + (days % 31) : 1 + ((days - 186) % 30);

    return [jalaliYear, jalaliMonth, jalaliDay];
};

const jalaliToGregorian = (jalaliYear, jalaliMonth, jalaliDay) => {
    jalaliYear += 1595;
    let days = -355668
        + (365 * jalaliYear)
        + (Math.floor(jalaliYear / 33) * 8)
        + Math.floor(((jalaliYear % 33) + 3) / 4)
        + jalaliDay
        + (jalaliMonth < 7 ? (jalaliMonth - 1) * 31 : ((jalaliMonth - 7) * 30) + 186);
    let gregorianYear = 400 * Math.floor(days / 146097);
    days %= 146097;

    if (days > 36524) {
        days -= 1;
        gregorianYear += 100 * Math.floor(days / 36524);
        days %= 36524;

        if (days >= 365) {
            days += 1;
        }
    }

    gregorianYear += 4 * Math.floor(days / 1461);
    days %= 1461;

    if (days > 365) {
        gregorianYear += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }

    let gregorianDay = days + 1;
    const isLeap = (gregorianYear % 4 === 0 && gregorianYear % 100 !== 0) || gregorianYear % 400 === 0;
    const monthDays = [0, 31, isLeap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gregorianMonth = 1;

    while (gregorianMonth <= 12 && gregorianDay > monthDays[gregorianMonth]) {
        gregorianDay -= monthDays[gregorianMonth];
        gregorianMonth += 1;
    }

    return [gregorianYear, gregorianMonth, gregorianDay];
};

const jalaliMonthLength = (year, month) => {
    if (month <= 6) return 31;
    if (month <= 11) return 30;

    const current = jalaliToGregorian(year, 1, 1);
    const next = jalaliToGregorian(year + 1, 1, 1);
    const days = (Date.UTC(...[next[0], next[1] - 1, next[2]]) - Date.UTC(...[current[0], current[1] - 1, current[2]])) / 86400000;

    return days === 366 ? 30 : 29;
};

const parseJalaliDate = (value) => {
    const match = normalizeDateDigits(value).trim().replaceAll('-', '/').replaceAll('.', '/').match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);

    if (!match) return null;

    const date = [Number(match[1]), Number(match[2]), Number(match[3])];

    if (date[0] < 1200 || date[0] > 1700 || date[1] < 1 || date[1] > 12 || date[2] < 1 || date[2] > jalaliMonthLength(date[0], date[1])) {
        return null;
    }

    return date;
};

const padDatePart = (value) => String(value).padStart(2, '0');
const jalaliDateString = (date) => `${date[0]}/${padDatePart(date[1])}/${padDatePart(date[2])}`;
const gregorianDateString = (date) => `${date[0]}-${padDatePart(date[1])}-${padDatePart(date[2])}`;
const jalaliMonthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const persianNumber = new Intl.NumberFormat('fa-IR', { useGrouping: false });
let openJalaliPicker = null;

const closeOpenJalaliPicker = () => {
    if (!openJalaliPicker) return;

    openJalaliPicker.querySelector('[data-jalali-panel]').classList.add('hidden');
    openJalaliPicker.querySelector('[data-jalali-toggle]').setAttribute('aria-expanded', 'false');
    openJalaliPicker = null;
};

document.querySelectorAll('[data-jalali-date]').forEach((picker) => {
    const input = picker.querySelector('[data-jalali-input]');
    const hiddenInput = picker.querySelector('[data-gregorian-input]');
    const toggle = picker.querySelector('[data-jalali-toggle]');
    const panel = picker.querySelector('[data-jalali-panel]');
    const title = picker.querySelector('[data-jalali-title]');
    const daysContainer = picker.querySelector('[data-jalali-days]');
    const now = new Date();
    const today = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    let selectedDate = parseJalaliDate(input.value);
    let viewYear = selectedDate?.[0] ?? today[0];
    let viewMonth = selectedDate?.[1] ?? today[1];

    const syncHiddenInput = () => {
        selectedDate = parseJalaliDate(input.value);
        hiddenInput.value = selectedDate ? gregorianDateString(jalaliToGregorian(...selectedDate)) : '';
    };

    const positionPanel = () => {
        const rect = toggle.getBoundingClientRect();
        const width = Math.min(320, window.innerWidth - 24);
        const left = Math.max(12, Math.min(rect.right - width, window.innerWidth - width - 12));
        const estimatedHeight = 390;
        const top = rect.bottom + estimatedHeight > window.innerHeight
            ? Math.max(12, rect.top - estimatedHeight - 8)
            : rect.bottom + 8;

        panel.style.width = `${width}px`;
        panel.style.left = `${left}px`;
        panel.style.top = `${top}px`;
    };

    const closePanel = () => {
        panel.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');

        if (openJalaliPicker === picker) {
            openJalaliPicker = null;
        }
    };

    const selectDate = (date) => {
        selectedDate = date;
        input.value = jalaliDateString(date);
        hiddenInput.value = gregorianDateString(jalaliToGregorian(...date));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        closePanel();
    };

    const renderCalendar = () => {
        selectedDate = parseJalaliDate(input.value);
        title.textContent = `${jalaliMonthNames[viewMonth - 1]} ${persianNumber.format(viewYear)}`;
        daysContainer.replaceChildren();
        const firstGregorian = jalaliToGregorian(viewYear, viewMonth, 1);
        const firstWeekday = (new Date(firstGregorian[0], firstGregorian[1] - 1, firstGregorian[2]).getDay() + 1) % 7;

        for (let index = 0; index < firstWeekday; index += 1) {
            const placeholder = document.createElement('span');
            placeholder.className = 'jalali-day-placeholder';
            daysContainer.appendChild(placeholder);
        }

        for (let day = 1; day <= jalaliMonthLength(viewYear, viewMonth); day += 1) {
            const button = document.createElement('button');
            const date = [viewYear, viewMonth, day];
            const isToday = date.every((part, index) => part === today[index]);
            const isSelected = selectedDate && date.every((part, index) => part === selectedDate[index]);

            button.type = 'button';
            button.className = `jalali-day${isToday ? ' is-today' : ''}${isSelected ? ' is-selected' : ''}`;
            button.textContent = persianNumber.format(day);
            button.addEventListener('click', () => selectDate(date));
            daysContainer.appendChild(button);
        }
    };

    const openPanel = () => {
        if (openJalaliPicker && openJalaliPicker !== picker) {
            openJalaliPicker.querySelector('[data-jalali-panel]').classList.add('hidden');
            openJalaliPicker.querySelector('[data-jalali-toggle]').setAttribute('aria-expanded', 'false');
        }

        selectedDate = parseJalaliDate(input.value);
        viewYear = selectedDate?.[0] ?? today[0];
        viewMonth = selectedDate?.[1] ?? today[1];
        renderCalendar();
        panel.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        openJalaliPicker = picker;
        positionPanel();
    };

    toggle.addEventListener('click', () => panel.classList.contains('hidden') ? openPanel() : closePanel());
    input.addEventListener('blur', syncHiddenInput);
    input.addEventListener('change', syncHiddenInput);
    picker.closest('form')?.addEventListener('submit', syncHiddenInput);
    picker.querySelector('[data-jalali-prev]').addEventListener('click', () => {
        viewMonth -= 1;
        if (viewMonth === 0) {
            viewMonth = 12;
            viewYear -= 1;
        }
        renderCalendar();
    });
    picker.querySelector('[data-jalali-next]').addEventListener('click', () => {
        viewMonth += 1;
        if (viewMonth === 13) {
            viewMonth = 1;
            viewYear += 1;
        }
        renderCalendar();
    });
    picker.querySelector('[data-jalali-today]').addEventListener('click', () => selectDate(today));
    picker.querySelector('[data-jalali-clear]')?.addEventListener('click', () => {
        input.value = '';
        hiddenInput.value = '';
        selectedDate = null;
        closePanel();
    });
});

document.addEventListener('click', (event) => {
    if (openJalaliPicker && !openJalaliPicker.contains(event.target)) {
        closeOpenJalaliPicker();
    }
});

window.addEventListener('resize', closeOpenJalaliPicker);
window.addEventListener('scroll', closeOpenJalaliPicker, true);

const selectedCatalogForm = document.querySelector('[data-selected-catalog-form]');

if (selectedCatalogForm) {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    window.requestAnimationFrame(() => {
        selectedCatalogForm.scrollIntoView({
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
            block: 'start',
        });
        selectedCatalogForm.querySelector('input[name="unit_price"]')?.focus({ preventScroll: true });
    });
}

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
    const numberFormatter = new Intl.NumberFormat('fa-IR');

    const unlockForm = () => {
        submitButton.disabled = false;
        submitButton.classList.remove('cursor-not-allowed', 'opacity-60');
    };

    const showFailure = (message, hint) => {
        progressStatus.textContent = message;
        progressHint.textContent = hint;
        progressBar.classList.remove('animate-pulse', 'bg-teal-600');
        progressBar.classList.add('bg-rose-600');
        unlockForm();
    };

    const pollImportProgress = async (imports) => {
        try {
            const responses = await Promise.all(imports.map((item) => fetch(item.status_url, {
                headers: { Accept: 'application/json' },
            })));

            if (responses.some((response) => !response.ok)) {
                throw new Error('Progress request failed.');
            }

            const states = await Promise.all(responses.map((response) => response.json()));
            const failedImport = states.find((state) => state.status === 'failed');

            if (failedImport) {
                showFailure('پردازش فایل ناموفق بود.', failedImport.error_message || 'گزارش خطا در تاریخچه بروزرسانی ثبت شد.');

                return;
            }

            const percentage = Math.round(states.reduce((total, state) => total + state.progress_percent, 0) / states.length);
            const processedRows = states.reduce((total, state) => total + state.processed_rows, 0);
            const completed = states.every((state) => state.status === 'completed');
            const queued = states.every((state) => state.status === 'queued');

            progressBar.classList.remove('animate-pulse');
            progressBar.style.width = `${percentage}%`;
            progressPercent.textContent = `${percentage}%`;
            progressStatus.textContent = completed
                ? 'پردازش کاتالوگ با موفقیت کامل شد.'
                : queued
                    ? 'فایل در صف پردازش است...'
                    : 'در حال پردازش و ورود اطلاعات...';
            progressHint.textContent = completed
                ? 'در حال بروزرسانی گزارش صفحه...'
                : `${numberFormatter.format(processedRows)} ردیف تاکنون بررسی شده است.`;

            if (completed) {
                window.setTimeout(() => window.location.assign(catalogImportForm.action), 800);

                return;
            }

            window.setTimeout(() => pollImportProgress(imports), 1500);
        } catch {
            progressStatus.textContent = 'دریافت وضعیت پردازش موقتاً ممکن نیست.';
            progressHint.textContent = 'اتصال دوباره بررسی می‌شود؛ این صفحه را نبندید.';
            window.setTimeout(() => pollImportProgress(imports), 3000);
        }
    };

    catalogImportForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const request = new XMLHttpRequest();
        const formData = new FormData(catalogImportForm);

        progressPanel.classList.remove('hidden');
        progressBar.classList.remove('animate-pulse', 'bg-rose-600');
        progressBar.classList.add('bg-teal-600');
        progressBar.style.width = '0%';
        progressPercent.textContent = '0%';
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
            if (request.status === 202) {
                try {
                    const response = JSON.parse(request.responseText);

                    progressBar.style.width = '0%';
                    progressPercent.textContent = '0%';
                    progressStatus.textContent = 'فایل دریافت شد؛ در انتظار شروع پردازش...';
                    progressHint.textContent = 'پردازش در پس‌زمینه انجام می‌شود.';
                    pollImportProgress(response.imports);
                } catch {
                    showFailure('پاسخ سرور قابل پردازش نیست.', 'صفحه را بازخوانی و تاریخچه بروزرسانی را بررسی کنید.');
                }

                return;
            }

            if (request.status >= 200 && request.status < 400) {
                window.location.assign(request.responseURL || catalogImportForm.action);

                return;
            }

            showFailure(
                'بروزرسانی انجام نشد.',
                request.status === 413
                ? 'حجم فایل از سقف مجاز وب‌سرور بیشتر است.'
                : 'خطایی در ارسال فایل رخ داد؛ دوباره تلاش کنید.',
            );
        });

        request.addEventListener('error', () => {
            showFailure('ارتباط با سرور قطع شد.', 'اتصال شبکه را بررسی و دوباره تلاش کنید.');
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
