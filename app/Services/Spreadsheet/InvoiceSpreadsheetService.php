<?php

namespace App\Services\Spreadsheet;

use App\Actions\SaveInvoiceAction;
use App\Enums\InvoiceType;
use App\Enums\SettlementMethod;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\InvoiceSubmissionValidator;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class InvoiceSpreadsheetService
{
    private const HEADERS = [
        'شماره داخلی', 'تاریخ صورتحساب', 'کد اقتصادی مشتری', 'روش تسویه', 'مبلغ نقدی',
        'شناسه کالا/خدمت', 'تعداد', 'قیمت واحد', 'نرخ مالیات', 'تخفیف', 'توضیحات',
        'نوع صورتحساب', 'شماره مالیاتی', 'وضعیت',
    ];

    public function __construct(
        private SpreadsheetFile $spreadsheet,
        private SaveInvoiceAction $saveInvoice,
    ) {}

    /** @param array{q?: string, status?: string, type?: string} $filters */
    public function export(User $user, array $filters = []): BinaryFileResponse
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $invoices = Invoice::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereBelongsTo($user))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($type !== '', fn (Builder $query) => $query->where('invoice_type', $type))
            ->with(['customer', 'items'])
            ->latest('invoice_date')
            ->get();
        $rows = $invoices->flatMap(function (Invoice $invoice): array {
            return $invoice->items->map(fn ($item): array => [
                $invoice->number,
                JalaliDate::format($invoice->invoice_date),
                (string) $invoice->customer->economic_code,
                $invoice->settlement_method->label(),
                $invoice->settlement_method === SettlementMethod::Mixed ? (float) $invoice->cash_amount : '',
                (string) $item->commodity_code,
                (float) $item->quantity,
                (float) $item->unit_price,
                (float) $item->tax_rate,
                (float) $item->discount,
                (string) $invoice->description,
                $invoice->invoice_type->label(),
                (string) $invoice->tax_id,
                $invoice->status->label(),
            ])->all();
        });

        return $this->spreadsheet->download('invoices-'.now()->format('Y-m-d').'.xlsx', self::HEADERS, $rows);
    }

    public function template(): BinaryFileResponse
    {
        return $this->spreadsheet->download('invoices-template.xlsx', self::HEADERS, []);
    }

    /** @return array{created: int, updated: int, errors: array<int, string>} */
    public function import(User $user, UploadedFile $file): array
    {
        $data = $this->spreadsheet->read($file);
        $groupedRows = collect($data['rows'])->groupBy(fn (array $row): string => $this->spreadsheet->normalizeDigits($row['شماره داخلی'] ?? ''));
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($groupedRows as $number => $rows) {
            $firstRow = $rows->first();

            if ($number === '') {
                $errors[] = 'یک یا چند ردیف فاقد شماره داخلی صورتحساب است.';

                continue;
            }

            try {
                $invoiceType = mb_strtolower(trim((string) ($firstRow['نوع صورتحساب'] ?? '')));

                if (! in_array($invoiceType, ['', 'اصلی', 'original', '1'], true)) {
                    throw new \RuntimeException('صورتحساب اصلاحی و ابطالی باید از صفحه صورتحساب مرجع ساخته شود.');
                }

                $customerCode = $this->spreadsheet->normalizeDigits($firstRow['کد اقتصادی مشتری'] ?? '');
                $customer = Customer::query()->whereBelongsTo($user)->where('economic_code', $customerCode)->first();

                if ($customer === null) {
                    throw new \RuntimeException("مشتری با کد اقتصادی {$customerCode} پیدا نشد.");
                }

                $items = [];

                foreach ($rows as $row) {
                    $commodityCode = $this->spreadsheet->normalizeDigits($row['شناسه کالا/خدمت'] ?? '');
                    $good = Good::query()->whereBelongsTo($user)->where('commodity_code', $commodityCode)->first();

                    if ($good === null) {
                        throw new \RuntimeException("قلم با شناسه {$commodityCode} پیدا نشد.");
                    }

                    $items[] = [
                        'good_id' => $good->id,
                        'quantity' => $this->numeric($row['تعداد'] ?? 0),
                        'unit_price' => $this->numeric($row['قیمت واحد'] ?? $good->unit_price),
                        'tax_rate' => $this->numeric($row['نرخ مالیات'] ?? $good->tax_rate),
                        'discount' => $this->numeric($row['تخفیف'] ?? 0),
                    ];
                }

                $values = [
                    'customer_id' => $customer->id,
                    'number' => $number,
                    'invoice_date' => $this->invoiceDate($firstRow['تاریخ صورتحساب'] ?? ''),
                    'description' => trim((string) ($firstRow['توضیحات'] ?? '')),
                    'settlement_method' => $this->settlementMethod($firstRow['روش تسویه'] ?? ''),
                    'cash_amount' => $this->numeric($firstRow['مبلغ نقدی'] ?? 0),
                    'items' => $items,
                ];
                $submissionWindowDays = (int) config('services.moadian.normal_submission_window_days', InvoiceSubmissionValidator::NORMAL_SUBMISSION_WINDOW_DAYS);
                $validator = Validator::make($values, [
                    'customer_id' => ['required', 'integer'],
                    'number' => ['required', 'string', 'max:50'],
                    'invoice_date' => [
                        'required',
                        'date_format:Y-m-d',
                        'before_or_equal:today',
                        'after_or_equal:'.today('Asia/Tehran')->subDays($submissionWindowDays)->format('Y-m-d'),
                    ],
                    'description' => ['nullable', 'string', 'max:1000'],
                    'settlement_method' => ['required', 'in:cash,credit,mixed'],
                    'cash_amount' => ['nullable', 'numeric', 'min:0'],
                    'items' => ['required', 'array', 'min:1', 'max:100'],
                    'items.*.good_id' => ['required', 'integer'],
                    'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                    'items.*.unit_price' => ['required', 'numeric', 'gt:0'],
                    'items.*.tax_rate' => ['required', 'numeric', 'between:0,100'],
                    'items.*.discount' => ['nullable', 'numeric', 'min:0'],
                ]);

                if ($validator->fails()) {
                    throw new \RuntimeException($validator->errors()->first());
                }

                $invoice = Invoice::query()->whereBelongsTo($user)->where('number', $number)->first();

                if ($invoice !== null && ! $invoice->isEditable()) {
                    throw new \RuntimeException('این شماره قبلاً ارسال شده و قابل بازنویسی نیست.');
                }

                if ($invoice?->invoice_type !== null && $invoice->invoice_type !== InvoiceType::Original) {
                    throw new \RuntimeException('ویرایش صورتحساب اصلاحی یا ابطالی از طریق اکسل مجاز نیست.');
                }

                $wasNew = $invoice === null;
                $this->saveInvoice->handle($user, $validator->validated(), $invoice);
                $wasNew ? $created++ : $updated++;
            } catch (Throwable $exception) {
                $errors[] = "صورتحساب {$number}: {$exception->getMessage()}";
            }
        }

        return compact('created', 'updated', 'errors');
    }

    private function settlementMethod(mixed $value): string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'نسیه', 'credit', '2' => SettlementMethod::Credit->value,
            'نقدی/نسیه', 'نقدی / نسیه', 'mixed', '3' => SettlementMethod::Mixed->value,
            default => SettlementMethod::Cash->value,
        };
    }

    private function invoiceDate(mixed $value): ?string
    {
        $normalized = $this->spreadsheet->normalizeDigits($value);

        if (is_numeric($normalized) && (float) $normalized > 20000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $normalized)->format('Y-m-d');
        }

        if (preg_match('/^(12|13|14|15|16)\d{2}[\/\-.]/', $normalized) === 1) {
            return JalaliDate::toGregorianDate($normalized);
        }

        try {
            return CarbonImmutable::parse($normalized)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function numeric(mixed $value): float
    {
        return (float) str_replace([',', '٬', ' '], '', $this->spreadsheet->normalizeDigits($value));
    }
}
