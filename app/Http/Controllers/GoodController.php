<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGoodRequest;
use App\Models\Good;
use App\Models\StuffCatalogItem;
use App\Services\OfficialStuffCatalogClient;
use App\Services\StuffCatalogMetadata;
use App\Support\MeasurementUnitCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GoodController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Good::class);
        $user = $request->user();
        $search = $request->string('q')->trim()->toString();

        $goods = Good::query()
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereBelongsTo($user))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('commodity_code', 'like', "%{$search}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('goods.index', compact('goods', 'search'));
    }

    public function create(
        Request $request,
        StuffCatalogMetadata $metadata,
        OfficialStuffCatalogClient $officialCatalog,
    ): View {
        Gate::authorize('create', Good::class);
        $catalogSearch = mb_substr($this->normalizeCatalogSearch($request->string('catalog_query')->trim()->toString()), 0, 120);
        $catalogType = mb_substr($request->string('catalog_type')->trim()->toString(), 0, 80);
        $catalogVat = mb_substr($request->string('catalog_vat')->trim()->toString(), 0, 10);
        $catalogFiltersApplied = $catalogSearch !== '' || $catalogType !== '' || $catalogVat !== '';
        $catalogResults = null;

        if ($catalogFiltersApplied) {
            $catalogResults = StuffCatalogItem::query()
                ->when($catalogSearch !== '', fn (Builder $query) => $this->applyCatalogSearch($query, $catalogSearch))
                ->when($catalogType !== '', fn (Builder $query) => $query->where('type', $catalogType))
                ->when(is_numeric($catalogVat), fn (Builder $query) => $query->where('vat', (float) $catalogVat))
                ->paginate(25, ['*'], 'catalog_page')
                ->withQueryString();
        }

        $selectedCatalogItem = $request->integer('catalog_item') > 0
            ? StuffCatalogItem::query()->find($request->integer('catalog_item'))
            : null;
        $requestedCommodityCode = $this->normalizeCatalogSearch($request->string('commodity_code')->trim()->toString());
        $commodityCode = $selectedCatalogItem?->item_id
            ?? ($requestedCommodityCode !== ''
                ? $requestedCommodityCode
                : (preg_match('/^\d{13}$/', $catalogSearch) === 1 ? $catalogSearch : ''));
        $lookupResult = null;

        if ($selectedCatalogItem !== null) {
            $lookupResult = $this->catalogLookupResult($selectedCatalogItem);
        } elseif (preg_match('/^\d{8,20}$/', $commodityCode)) {
            $catalogItem = StuffCatalogItem::query()
                ->where('item_id', $commodityCode)
                ->orderByDesc('effective_date')
                ->first();

            if ($catalogItem === null) {
                $officialItem = $officialCatalog->lookup($commodityCode);

                if ($officialItem !== null) {
                    $catalogItem = $this->storeOfficialCatalogItem($officialItem);
                    $metadata->forget();
                }
            }

            $lookupResult = $catalogItem !== null
                ? $this->catalogLookupResult($catalogItem)
                : null;
        }

        return view('goods.create', [
            'commodityCode' => $commodityCode,
            'lookupResult' => $lookupResult,
            'lookupError' => null,
            'lookupNeedsConfiguration' => false,
            'catalogSearch' => $catalogSearch,
            'catalogType' => $catalogType,
            'catalogVat' => $catalogVat,
            'catalogFiltersApplied' => $catalogFiltersApplied,
            'catalogResults' => $catalogResults,
            'catalogTypes' => $metadata->types(),
            'catalogVats' => $metadata->vats(),
            'catalogCount' => $metadata->count(),
            'selectedCatalogItem' => $selectedCatalogItem,
        ]);
    }

    public function store(SaveGoodRequest $request): RedirectResponse
    {
        Gate::authorize('create', Good::class);
        $good = $request->user()->goods()->create($request->validated());

        return redirect()->route('goods.edit', $good)->with('success', 'کالا یا خدمت با موفقیت ذخیره شد.');
    }

    public function edit(Good $good): View
    {
        Gate::authorize('update', $good);

        return view('goods.edit', compact('good'));
    }

    public function update(SaveGoodRequest $request, Good $good): RedirectResponse
    {
        Gate::authorize('update', $good);
        $good->update($request->validated());

        return redirect()->route('goods.index')->with('success', 'اطلاعات کالا به‌روزرسانی شد.');
    }

    public function destroy(Good $good): RedirectResponse
    {
        Gate::authorize('delete', $good);

        if ($good->invoiceItems()->exists()) {
            return redirect()->route('goods.edit', $good)
                ->with('error', 'این قلم در یک یا چند صورتحساب استفاده شده و قابل حذف نیست؛ می‌توانید آن را غیرفعال کنید.');
        }

        $good->delete();

        return redirect()->route('goods.index')->with('success', 'کالا یا خدمت با موفقیت حذف شد.');
    }

    /** @return array{name: string, unit: string, unit_price: int, tax_rate: float, measurement_unit_code: string} */
    private function catalogLookupResult(StuffCatalogItem $item): array
    {
        return [
            'name' => $item->description,
            'unit' => str_contains((string) $item->type, 'خدمت') ? 'خدمت' : 'عدد',
            'unit_price' => 0,
            'tax_rate' => (float) $item->vat,
            'measurement_unit_code' => MeasurementUnitCode::resolve(),
        ];
    }

    private function normalizeCatalogSearch(string $value): string
    {
        return strtr(str_replace(['ي', 'ك'], ['ی', 'ک'], $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function applyCatalogSearch(Builder $query, string $catalogSearch): void
    {
        if (preg_match('/^\d+$/', $catalogSearch) === 1) {
            $query->where('item_id', 'like', "{$catalogSearch}%");

            return;
        }

        $booleanSearch = collect(preg_split('/\s+/u', $catalogSearch, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $term): string => preg_replace('/[^\p{L}\p{N}]+/u', '', $term) ?? '')
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->map(fn (string $term): string => "+{$term}*")
            ->implode(' ');

        if (in_array(DB::getDriverName(), ['mariadb', 'mysql'], true) && $booleanSearch !== '') {
            $query->whereFullText('description', $booleanSearch, ['mode' => 'boolean']);

            return;
        }

        $query->where('description', 'like', "%{$catalogSearch}%");
    }

    /** @param array<string, mixed> $item */
    private function storeOfficialCatalogItem(array $item): StuffCatalogItem
    {
        $sourceHash = hash('sha256', implode('|', [
            $item['item_id'],
            $item['effective_date'] ?? '',
            $item['expiration_date'] ?? '',
        ]));

        return StuffCatalogItem::query()->updateOrCreate(
            ['source_hash' => $sourceHash],
            $item,
        );
    }
}
