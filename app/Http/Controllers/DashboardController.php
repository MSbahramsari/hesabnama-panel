<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $owned = fn (Builder $query): Builder => $query->when(! $user->isAdmin(), fn (Builder $query) => $query->where('user_id', $user->id));

        $metrics = [
            'customers' => $owned(Customer::query())->count(),
            'goods' => $owned(Good::query())->count(),
            'invoices' => $owned(Invoice::query())->count(),
            'confirmed_total' => (clone $this->effectiveSalesQuery($request))->sum('total'),
            'period_total' => (clone $this->effectiveSalesQuery($request))
                ->whereBetween('invoice_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('total'),
        ];

        $periodSales = collect(range(5, 0))->map(function (int $monthsAgo) use ($request): array {
            $month = now()->subMonths($monthsAgo);
            $total = (clone $this->effectiveSalesQuery($request))
                ->whereBetween('invoice_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->sum('total');

            return ['label' => JalaliDate::format($month, 'Y/m'), 'total' => (float) $total];
        });

        $effectiveInvoiceIds = $this->effectiveSalesQuery($request)->select('invoices.id');
        $topGoods = InvoiceItem::query()
            ->whereIn('invoice_id', $effectiveInvoiceIds)
            ->selectRaw('commodity_code, description, SUM(quantity) as quantity_sum, SUM(total) as total_sum')
            ->groupBy('commodity_code', 'description')
            ->orderByDesc('total_sum')
            ->limit(6)
            ->get();

        $recentInvoices = $owned(Invoice::query())
            ->select(['id', 'customer_id', 'number', 'invoice_date', 'status', 'total', 'created_at'])
            ->with('customer:id,name')
            ->latest()
            ->limit(6)
            ->get();

        $statusCounts = $owned(Invoice::query())
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('dashboard', [
            'metrics' => $metrics,
            'recentInvoices' => $recentInvoices,
            'statusCounts' => $statusCounts,
            'userCount' => $user->isAdmin() ? User::count() : null,
            'periodSales' => $periodSales,
            'topGoods' => $topGoods,
        ]);
    }

    private function effectiveSalesQuery(Request $request): Builder
    {
        $user = $request->user();

        return Invoice::query()
            ->where('status', InvoiceStatus::Confirmed)
            ->where('invoice_type', '!=', InvoiceType::Cancellation)
            ->whereDoesntHave('adjustments', fn (Builder $query) => $query->where('status', InvoiceStatus::Confirmed))
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereBelongsTo($user));
    }
}
