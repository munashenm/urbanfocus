<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Order $order, InvoiceService $invoices): View
    {
        $this->authorizeOrder($order);

        return view('invoices.show', $invoices->data($order));
    }

    protected function authorizeOrder(Order $order): void
    {
        if (auth()->user()?->isAdmin()) {
            return;
        }

        abort_unless($order->user_id === auth()->id(), 403);
    }
}
