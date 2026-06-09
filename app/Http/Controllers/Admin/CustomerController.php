<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use LogsAdminActivity;

    public function index(Request $request): View
    {
        $query = User::query()
            ->whereDoesntHave('roles')
            ->where('is_admin', false)
            ->withCount('orders')
            ->latest();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $customer): View
    {
        abort_if($customer->canAccessAdmin(), 404);

        $customer->loadCount('orders');
        $orders = Order::where('user_id', $customer->id)->latest()->paginate(10);

        return view('admin.customers.show', compact('customer', 'orders'));
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->canAccessAdmin(), 404);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email,'.$customer->id,
            'phone' => 'nullable|string|max:30',
            'company_name' => 'nullable|string|max:150',
            'vat_number' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $customer->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit('customers.update', $customer);

        return back()->with('success', 'Customer updated.');
    }
}
