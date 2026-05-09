<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        [$startDate, $endDate, $period] = $this->resolvePeriod($request);

        $baseQuery = Order::query()
            ->whereBetween('created_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->whereNotIn('status', ['awaiting_acceptance', 'cancelled']);

        $orders = (clone $baseQuery)
            ->with(['customer', 'items.product'])
            ->latest()
            ->get();

        $orderIds = $orders->pluck('id');

        $dailySales = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as sale_date')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_amount) as total_amount')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $paymentBreakdown = (clone $baseQuery)
            ->selectRaw("COALESCE(payment_method, 'unidentified') as payment_method")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_amount) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get();

        $channelBreakdown = (clone $baseQuery)
            ->selectRaw('type')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_amount) as total_amount')
            ->groupBy('type')
            ->orderByDesc('total_amount')
            ->get();

        $topProducts = OrderItem::query()
            ->with('product')
            ->whereIn('order_id', $orderIds->all())
            ->selectRaw('product_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(subtotal) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        $summary = [
            'gross_revenue' => (float) $orders->sum('total_amount'),
            'orders_count' => $orders->count(),
            'average_ticket' => $orders->count() > 0 ? (float) $orders->avg('total_amount') : 0.0,
            'delivery_revenue' => (float) $orders->where('type', 'delivery')->sum('total_amount'),
            'counter_revenue' => (float) $orders->where('type', 'counter')->sum('total_amount'),
            'table_revenue' => (float) $orders->where('type', 'table')->sum('total_amount'),
        ];

        return view('reports.sales', [
            'orders' => $orders,
            'summary' => $summary,
            'dailySales' => $dailySales,
            'paymentBreakdown' => $paymentBreakdown,
            'channelBreakdown' => $channelBreakdown,
            'topProducts' => $topProducts,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'period' => $period,
        ]);
    }

    protected function resolvePeriod(Request $request): array
    {
        $period = (string) $request->input('period', 'today');

        return match ($period) {
            'yesterday' => [now()->subDay(), now()->subDay(), $period],
            'last_7_days' => [now()->subDays(6), now(), $period],
            'month' => [now()->startOfMonth(), now()->endOfMonth(), $period],
            'custom' => $this->resolveCustomPeriod($request, $period),
            default => [now(), now(), 'today'],
        };
    }

    protected function resolveCustomPeriod(Request $request, string $period): array
    {
        $startDate = $this->parseDate($request->input('start_date')) ?? now();
        $endDate = $this->parseDate($request->input('end_date')) ?? now();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate, $period];
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
