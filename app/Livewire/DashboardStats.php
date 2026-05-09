<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use App\Models\Product;

class DashboardStats extends Component
{
    public $totalOrders;
    public $totalRevenue;
    public $totalProducts;
    public $activeOrders;

    public function mount()
    {
        $this->updateStats();
    }

    public function updateStats()
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $todayOrders = Order::whereBetween('created_at', [$todayStart, $todayEnd]);

        $this->totalOrders = (clone $todayOrders)->count();
        $this->totalRevenue = (float) (clone $todayOrders)->sum('total_amount');
        $this->totalProducts = Product::count();
        $this->activeOrders = (clone $todayOrders)
            ->whereIn('status', ['awaiting_acceptance', 'pending', 'preparing'])
            ->count();
    }

    public function render()
    {
        return view('livewire.dashboard-stats');
    }
}
