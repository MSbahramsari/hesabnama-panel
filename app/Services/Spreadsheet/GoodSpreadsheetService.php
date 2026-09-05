<?php

namespace App\Services\Spreadsheet;

use App\Models\Good;
use App\Models\User;
use App\Support\MeasurementUnitCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GoodSpreadsheetService
{
    private const HEADERS = ['شناسه کالا/خدمت', 'عنوان', 'واحد', 'قیمت واحد', 'نرخ مالیات', 'وضعیت'];

    public function __construct(private SpreadsheetFile $spreadsheet) {}

    public function export(User $user, string $search = ''): BinaryFileResponse
    {
        $goods = Good::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereBelongsTo($user))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('commodity_code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->cursor()
            ->map(fn (Good $good): array => [
                (string) $good->commodity_code,
                $good->name,
                $good->unit,
                (float) $good->unit_price,
                (float) $good->tax_rate,
                $good->is_active ? 'فعال' : 'غیرفعال',
            ]);

        return $this->spreadsheet->download('goods-'.now()->format('Y-m-d').'.xlsx', self::HEADERS, $goods);
    }

    public function template(): BinaryFileResponse
    {
        return $this->spreadsheet->download('goods-template.xlsx', self::HEADERS, []);
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
                'commodity_code' => $this->spreadsheet->normalizeDigits($row['شناسه کالا/خدمت'] ?? ''),
                'name' => trim((string) ($row['عنوان'] ?? '')),
                'unit' => trim((string) ($row['واحد'] ?? 'عدد')) ?: 'عدد',
                'measurement_unit_code' => MeasurementUnitCode::resolve(
                    $this->spreadsheet->normalizeDigits($row['کد واحد اندازه‌گیری'] ?? ''),
                ),
                'unit_price' => $this->numeric($row['قیمت واحد'] ?? 0),
                'tax_rate' => $this->numeric($row['نرخ مالیات'] ?? 0),
                'is_active' => $this->isActive($row['وضعیت'] ?? 'فعال'),
            ];
            $validator = Validator::make($values, [
                'commodity_code' => ['required', 'digits_between:8,20'],
                'name' => ['required', 'string', 'max:255'],
                'unit' => ['required', 'string', 'max:40'],
                'measurement_unit_code' => ['required', 'digits_between:1,10'],
                'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
                'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'is_active' => ['boolean'],
            ]);

            if ($validator->fails()) {
                $errors[] = 'ردیف '.($index + 2).': '.$validator->errors()->first();

                continue;
            }

            $good = Good::query()->firstOrNew([
                'user_id' => $user->id,
                'commodity_code' => $values['commodity_code'],
            ]);
            $good->fill($validator->validated());
            $good->user_id = $user->id;
            $good->save();
            $good->wasRecentlyCreated ? $created++ : $updated++;
        }

        return compact('created', 'updated', 'errors');
    }

    private function numeric(mixed $value): float
    {
        return (float) str_replace([',', '٬', ' '], '', $this->spreadsheet->normalizeDigits($value));
    }

    private function isActive(mixed $value): bool
    {
        return ! in_array(mb_strtolower(trim((string) $value)), ['غیرفعال', 'inactive', 'false', '0', 'خیر'], true);
    }
}
