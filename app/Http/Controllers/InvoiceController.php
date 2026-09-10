<?php

namespace App\Http\Controllers;

use App\Actions\DuplicateInvoiceAction;
use App\Actions\SaveInvoiceAction;
use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SettlementMethod;
use App\Http\Requests\SaveInvoiceRequest;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\MoadianClientFactory;
use App\Support\JalaliDate;
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
        $type = $request->string('type')->toString();
        $buyerStatus = $request->string('buyer_status')->toString();
        $settlementMethod = $request->string('settlement_method')->toString();
        $customerId = $request->integer('customer_id');
        $invoiceDate = $request->string('invoice_date')->toString();
        $invoiceDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate) === 1 ? $invoiceDate : '';
        $minimumTotal = $request->float('minimum_total');
        $moadianConfiguration = $clientFactory->configurationForUser($user);

        $invoices = Invoice::query()
            ->select(['id', 'user_id', 'customer_id', 'reference_invoice_id', 'number', 'tax_id', 'invoice_date', 'invoice_type', 'settlement_method', 'status', 'moadian_status', 'buyer_status', 'total', 'created_at'])
            ->with('customer:id,name,economic_code')
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereBelongsTo($user))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when(InvoiceStatus::tryFrom($status), fn (Builder $query) => $query->where('status', $status))
            ->when(InvoiceType::tryFrom($type), fn (Builder $query) => $query->where('invoice_type', $type))
            ->when(SettlementMethod::tryFrom($settlementMethod), fn (Builder $query) => $query->where('settlement_method', $settlementMethod))
            ->when($customerId > 0, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->when($invoiceDate !== '', fn (Builder $query) => $query->whereDate('invoice_date', $invoiceDate))
            ->when($minimumTotal > 0, fn (Builder $query) => $query->where('total', '>=', $minimumTotal))
            ->when($buyerStatus === 'not_synced', fn (Builder $query) => $query->whereNull('buyer_status'))
            ->when(BuyerStatus::tryFrom($buyerStatus), fn (Builder $query) => $query->where('buyer_status', $buyerStatus))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'buyerStatus' => $buyerStatus,
            'settlementMethod' => $settlementMethod,
            'customerId' => $customerId,
            'invoiceDate' => $invoiceDate,
            'minimumTotal' => $minimumTotal > 0 ? $minimumTotal : null,
            'statuses' => InvoiceStatus::cases(),
            'types' => InvoiceType::cases(),
            'buyerStatuses' => BuyerStatus::cases(),
            'settlementMethods' => SettlementMethod::cases(),
            'filterCustomers' => Customer::query()->whereBelongsTo($user)->orderBy('name')->get(['id', 'name']),
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
        $invoice->load(['customer', 'items.good', 'referenceInvoice', 'adjustments']);
        $moadianConfiguration = $clientFactory->configurationForUser($invoice->user);

        return view('invoices.show', [
            'invoice' => $invoice,
            'moadianIsReal' => $moadianConfiguration->isReal(),
            'moadianIsReady' => $moadianConfiguration->isReady(),
        ]);
    }

    public function print(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['customer', 'items.good', 'user.taxpayerProfile', 'referenceInvoice']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'profile' => $invoice->user->taxpayerProfile,
        ]);
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        Gate::authorize('update', $invoice);
        $invoice->load(['items', 'referenceInvoice.items', 'referenceInvoice.customer']);

        return view('invoices.edit', array_merge($this->formData($request, $invoice), compact('invoice')));
    }

    public function update(SaveInvoiceRequest $request, Invoice $invoice, SaveInvoiceAction $action): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        $action->handle($request->user(), $request->validated(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', 'فاکتور به‌روزرسانی شد.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'صورتحساب با موفقیت حذف شد.');
    }

    public function duplicate(Invoice $invoice, DuplicateInvoiceAction $action): RedirectResponse
    {
        Gate::authorize('duplicate', $invoice);
        $copy = $action->handle($invoice->user, $invoice, $this->suggestedNumber($invoice->user));

        return redirect()->route('invoices.edit', $copy)
            ->with('success', 'یک پیش‌نویس جدید با اطلاعات این صورتحساب ساخته شد.');
    }

    /** @return array{customers: Collection<int, Customer>, goods: Collection<int, Good>, suggestedNumber: string} */
    private function formData(Request $request, ?Invoice $invoice = null): array
    {
        $user = $request->user();
        $invoiceGoodIds = $invoice?->items->pluck('good_id')->filter()->all() ?? [];
        $customers = Customer::query()
            ->whereBelongsTo($user)
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when($invoice, fn (Builder $query) => $query->orWhere('id', $invoice->customer_id)))
            ->orderBy('name')
            ->get();
        $goods = Good::query()
            ->whereBelongsTo($user)
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when($invoiceGoodIds !== [], fn (Builder $query) => $query->orWhereIn('id', $invoiceGoodIds)))
            ->when(
                $invoice?->invoice_type === InvoiceType::Correction,
                fn (Builder $query) => $query->whereIn('id', $invoice->referenceInvoice?->items->pluck('good_id')->filter() ?? []),
            )
            ->orderBy('name')
            ->get();

        return ['customers' => $customers, 'goods' => $goods, 'suggestedNumber' => $this->suggestedNumber($user)];
    }

    private function suggestedNumber(User $user): string
    {
        $sequence = Invoice::query()->whereBelongsTo($user)->whereYear('created_at', now()->year)->count() + 1;
        $prefix = 'INV-'.JalaliDate::format(now(), 'Ym').'-';
        $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

        while (Invoice::query()->whereBelongsTo($user)->where('number', $number)->exists()) {
            $sequence++;
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        }

        return $number;
    }
}
