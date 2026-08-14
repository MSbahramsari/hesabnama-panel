<?php

namespace App\Http\Controllers;

use App\Contracts\TaxPlatformGateway;
use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Http\Requests\SaveGoodRequest;
use App\Models\Good;
use App\Models\StuffCatalogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(Request $request, TaxPlatformGateway $gateway): View
    {
        Gate::authorize('create', Good::class);
        $catalogSearch = mb_substr($this->normalizeCatalogSearch($request->string('catalog_query')->trim()->toString()), 0, 120);
        $catalogType = mb_substr($request->string('catalog_type')->trim()->toString(), 0, 80);
        $catalogVat = mb_substr($request->string('catalog_vat')->trim()->toString(), 0, 10);
        $catalogFiltersApplied = $catalogSearch !== '' || $catalogType !== '' || $catalogVat !== '';
        $catalogResults = null;

        if ($catalogFiltersApplied) {
            $catalogResults = StuffCatalogItem::query()
                ->when($catalogSearch !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('item_id', 'like', "{$catalogSearch}%")
                    ->orWhere('description', 'like', "%{$catalogSearch}%")))
                ->when($catalogType !== '', fn (Builder $query) => $query->where('type', $catalogType))
                ->when(is_numeric($catalogVat), fn (Builder $query) => $query->where('vat', (float) $catalogVat))
                ->orderBy('description')
                ->paginate(25, ['*'], 'catalog_page')
                ->withQueryString();
        }

        $selectedCatalogItem = $request->integer('catalog_item') > 0
            ? StuffCatalogItem::query()->find($request->integer('catalog_item'))
            : null;
        $commodityCode = $selectedCatalogItem?->item_id
            ?? $request->string('commodity_code')->trim()->toString();
        $lookupResult = null;
        $lookupError = null;

        if ($selectedCatalogItem !== null) {
            $lookupResult = $this->catalogLookupResult($selectedCatalogItem);
        } elseif (preg_match('/^\d{8,20}$/', $commodityCode)) {
            $catalogItem = StuffCatalogItem::query()
                ->where('item_id', $commodityCode)
                ->orderByDesc('effective_date')
                ->first();

            try {
                $lookupResult = $catalogItem !== null
                    ? $this->catalogLookupResult($catalogItem)
                    : $gateway->lookupGood($request->user(), $commodityCode);
            } catch (MoadianConfigurationException|MoadianApiException $exception) {
                $lookupError = $exception->getMessage();
            }
        }

        return view('goods.create', [
            'commodityCode' => $commodityCode,
            'lookupResult' => $lookupResult,
            'lookupError' => $lookupError,
            'isDemo' => $gateway->isDemo(),
            'catalogSearch' => $catalogSearch,
            'catalogType' => $catalogType,
            'catalogVat' => $catalogVat,
            'catalogFiltersApplied' => $catalogFiltersApplied,
            'catalogResults' => $catalogResults,
            'catalogTypes' => StuffCatalogItem::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
            'catalogVats' => StuffCatalogItem::query()->distinct()->orderBy('vat')->pluck('vat'),
            'catalogCount' => StuffCatalogItem::query()->count(),
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

    /** @return array{name: string, unit: string, unit_price: int, tax_rate: float, measurement_unit_code: null} */
    private function catalogLookupResult(StuffCatalogItem $item): array
    {
        return [
            'name' => $item->description,
            'unit' => str_contains((string) $item->type, 'خدمت') ? 'خدمت' : 'عدد',
            'unit_price' => 0,
            'tax_rate' => (float) $item->vat,
            'measurement_unit_code' => null,
        ];
    }

    private function normalizeCatalogSearch(string $value): string
    {
        return str_replace(['ي', 'ك'], ['ی', 'ک'], $value);
    }
}
