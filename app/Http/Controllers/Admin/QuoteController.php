<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $query = Quote::with('product')->latest();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $quotes = $query->paginate(20)->withQueryString();

        return view('admin.quotes.index', compact('quotes'));
    }

    public function show(Quote $quote): View
    {
        $quote->load('product');

        return view('admin.quotes.show', compact('quote'));
    }

    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,in_progress,quoted,closed',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $quote->update($validated);

        return back()->with('success', 'Quote updated.');
    }
}
