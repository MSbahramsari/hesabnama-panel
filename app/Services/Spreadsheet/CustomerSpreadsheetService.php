<?php

namespace App\Services\Spreadsheet;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerSpreadsheetService
{
    private const HEADERS = ['کد اقتصادی', 'شناسه ملی', 'نام مشتری', 'نوع شخصیت', 'نشانی', 'کد پستی', 'شماره تماس', 'وضعیت'];

    public function __construct(private SpreadsheetFile $spreadsheet) {}

    public function export(User $user, string $search = ''): BinaryFileResponse
    {
        $customers = Customer::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereBelongsTo($user))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('economic_code', 'like', "%{$search}%")
                ->orWhere('national_id', 'like', "%{$search}%")))
            ->orderBy('name')
            ->cursor()
            ->map(fn (Customer $customer): array => [
                (string) $customer->economic_code,
                (string) $customer->national_id,
                $customer->name,
                $customer->type === 'legal' ? 'حقوقی' : 'حقیقی',
                (string) $customer->address,
                (string) $customer->postal_code,
                (string) $customer->phone,
                $customer->is_active ? 'فعال' : 'غیرفعال',
            ]);

        return $this->spreadsheet->download('customers-'.now()->format('Y-m-d').'.xlsx', self::HEADERS, $customers);
    }

    public function template(): BinaryFileResponse
    {
        return $this->spreadsheet->download('customers-template.xlsx', self::HEADERS, []);
    }

    /** @return array{created: int, updated: int, errors: array<int, string>} */
    public function import(User $user, UploadedFile $file): array
    {
        $data = $this->spreadsheet->read($file);
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($data['rows'] as $index => $row) {
            $values = [
                'economic_code' => $this->spreadsheet->normalizeDigits($row['کد اقتصادی'] ?? ''),
                'national_id' => $this->spreadsheet->normalizeDigits($row['شناسه ملی'] ?? ''),
                'name' => trim((string) ($row['نام مشتری'] ?? '')),
                'type' => $this->customerType($row['نوع شخصیت'] ?? ''),
                'address' => trim((string) ($row['نشانی'] ?? '')),
                'postal_code' => $this->spreadsheet->normalizeDigits($row['کد پستی'] ?? ''),
                'phone' => $this->spreadsheet->normalizeDigits($row['شماره تماس'] ?? ''),
                'is_active' => $this->isActive($row['وضعیت'] ?? 'فعال'),
            ];
            $validator = Validator::make($values, [
                'economic_code' => ['required', 'regex:/^(?:\d{11}|\d{14})$/'],
                'national_id' => ['nullable', 'digits_between:10,14'],
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', 'in:legal,individual'],
                'address' => ['nullable', 'string', 'max:500'],
                'postal_code' => ['nullable', 'digits:10'],
                'phone' => ['nullable', 'string', 'max:20'],
                'is_active' => ['boolean'],
            ]);

            if ($validator->fails()) {
                $errors[] = 'ردیف '.($index + 2).': '.$validator->errors()->first();

                continue;
            }

            $customer = Customer::query()->firstOrNew([
                'user_id' => $user->id,
                'economic_code' => $values['economic_code'],
            ]);
            $customer->fill($validator->validated());
            $customer->user_id = $user->id;
            $customer->save();
            $customer->wasRecentlyCreated ? $created++ : $updated++;
        }

        return compact('created', 'updated', 'errors');
    }

    private function customerType(mixed $value): string
    {
        return in_array(mb_strtolower(trim((string) $value)), ['حقیقی', 'individual', 'real', '1'], true)
            ? 'individual'
            : 'legal';
    }

    private function isActive(mixed $value): bool
    {
        return ! in_array(mb_strtolower(trim((string) $value)), ['غیرفعال', 'inactive', 'false', '0', 'خیر'], true);
    }
}
