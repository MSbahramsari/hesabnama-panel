<?php

namespace App\Http\Controllers;

use App\Actions\SaveInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Http\Requests\SaveInvoiceRequest;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Services\Moadian\MoadianClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request, MoadianClientFactory $clientFactory): View
    {
        Gate::authorize('viewAny', Invoice::class);
        $user = $request->user();
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $moadianConfiguration = $clientFactory->configurationForUser($user);

        $invoices = Invoice::query()
            ->select(['id', 'user_id', 'customer_id', 'number', 'invoice_date', 'status', 'buyer_status', 'total', 'created_at'])
            ->with('customer:id,name,economic_code')
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereBelongsTo($user))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when(InvoiceStatus::tryFrom($status), fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'search' => $search,
            'status' => $status,
            'statuses' => InvoiceStatus::cases(),
            'moadianIsReal' => $moadianConfiguration->isReal(),
            'moadianIsReady' => $moadianConfiguration->isReady(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Invoice::class);

        return view('invoices.create', $this->formData($request));
    }

    public function store(SaveInvoiceRequest $request, SaveInvoiceAction $action): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        $invoice = $action->handle($request->user(), $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', 'فاکتور به‌صورت پیش‌نویس ذخیره شد.');
    }

    public function show(Invoice $invoice, MoadianClientFactory $clientFactory): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['customer', 'items.good']);
        $moadianConfiguration = $clientFactory->configurationForUser($invoice->user);

        return view('invoices.show', [
            'invoice' => $invoice,
            'moadianIsReal' => $moadianConfiguration->isReal(),
            'moadianIsReady' => $moadianConfiguration->isReady(),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        Gate::authorize('update', $invoice);
        $invoice->load('items');

        return view('invoices.edit', array_merge($this->formData($request), compact('invoice')));
    }

    public function update(SaveInvoiceRequest $request, Invoice $invoice, SaveInvoiceAction $action): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        $action->handle($request->user(), $request->validated(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', 'فاکتور به‌روزرسانی شد.');
    }

    /** @return array{customers: Collection<int, Customer>, goods: Collection<int, Good>, suggestedNumber: string} */
    private function formData(Request $request): array
    {
        $user = $request->user();
        $customers = Customer::query()->whereBelongsTo($user)->where('is_active', true)->orderBy('name')->get();
        $goods = Good::query()->whereBelongsTo($user)->where('is_active', true)->orderBy('name')->get();
        $sequence = Invoice::query()->whereBelongsTo($user)->whereYear('created_at', now()->year)->count() + 1;

        return ['customers' => $customers, 'goods' => $goods, 'suggestedNumber' => 'INV-'.now()->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT)];
    }
}
