<?php

namespace App\Http\Controllers;

use App\Contracts\TaxPlatformGateway;
use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Http\Requests\SaveGoodRequest;
use App\Models\Good;
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
        $commodityCode = $request->string('commodity_code')->trim()->toString();
        $lookupResult = null;
        $lookupError = null;

        if (preg_match('/^\d{8,20}$/', $commodityCode)) {
            try {
                $lookupResult = $gateway->lookupGood($commodityCode);
            } catch (MoadianConfigurationException|MoadianApiException $exception) {
                $lookupError = $exception->getMessage();
            }
        }

        return view('goods.create', [
            'commodityCode' => $commodityCode,
            'lookupResult' => $lookupResult,
            'lookupError' => $lookupError,
            'isDemo' => $gateway->isDemo(),
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
}
