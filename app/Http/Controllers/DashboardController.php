<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
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
            'confirmed_total' => $owned(Invoice::query())->where('status', InvoiceStatus::Confirmed)->sum('total'),
        ];

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
        ]);
    }
}
