<?php

namespace App\Http\Controllers;

use App\Contracts\TaxPlatformGateway;
use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Http\Requests\SaveCustomerRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);
        $user = $request->user();
        $search = $request->string('q')->trim()->toString();

        $customers = Customer::query()
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereBelongsTo($user))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('economic_code', 'like', "%{$search}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('customers.index', compact('customers', 'search'));
    }

    public function create(Request $request, TaxPlatformGateway $gateway): View
    {
        Gate::authorize('create', Customer::class);
        $economicCode = $request->string('economic_code')->trim()->toString();
        $lookupResult = null;
        $lookupError = null;

        if (preg_match('/^\d{10,14}$/', $economicCode)) {
            try {
                $lookupResult = $gateway->lookupCustomer($request->user(), $economicCode);
            } catch (MoadianConfigurationException|MoadianApiException $exception) {
                $lookupError = $exception->getMessage();
            }
        }

        return view('customers.create', [
            'economicCode' => $economicCode,
            'lookupResult' => $lookupResult,
            'lookupError' => $lookupError,
            'isDemo' => $gateway->isDemo(),
        ]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse
    {
        Gate::authorize('create', Customer::class);
        $customer = $request->user()->customers()->create($request->validated());

        return redirect()->route('customers.edit', $customer)->with('success', 'مشتری با موفقیت ذخیره شد.');
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', compact('customer'));
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);
        $customer->update($request->validated());

        return redirect()->route('customers.index')->with('success', 'اطلاعات مشتری به‌روزرسانی شد.');
    }
}
