<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard(): View
    {
        $orders = auth()->user()->orders()->latest()->paginate(10);

        return view('account.dashboard', compact('orders'));
    }

    public function orderShow(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $order->load('items');

        return view('account.order', compact('order'));
    }
}
