<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use Livewire\WithPagination;

class OrdersTable extends Component
{
    use WithPagination;

    public $search = '';
    public bool $compact = false;
    public bool $todayOnly = false;

    public function render()
    {
        $orders = Order::with(['customer', 'address'])
            ->whereHas('customer', function($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            });

        if ($this->todayOnly) {
            $orders->whereBetween('created_at', [
                now()->startOfDay(),
                now()->endOfDay(),
            ]);
        }

        $orders = $orders->latest()->paginate(10);

        return view('livewire.orders-table', [
            'orders' => $orders
        ]);
    }
}
