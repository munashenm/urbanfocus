<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class B2bController extends Controller
{
    public function quote(): View
    {
        return view('b2b.quote');
    }

    public function rfq(): View
    {
        return view('b2b.rfq');
    }

    public function procurement(): View
    {
        return view('b2b.procurement');
    }

    public function source(): View
    {
        return view('b2b.source');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:quote,rfq,bulk,source,procurement',
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:150',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'message' => 'nullable|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'rfq_file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt',
        ]);

        if ($validated['type'] === 'rfq' && ! $request->hasFile('rfq_file') && empty($validated['message'])) {
            return back()->withErrors(['rfq_file' => 'Please upload an RFQ document or add a message.'])->withInput();
        }

        if (in_array($validated['type'], ['quote', 'source', 'procurement'], true) && empty($validated['message'])) {
            return back()->withErrors(['message' => 'Please describe your requirements.'])->withInput();
        }

        $filePath = null;
        if ($request->hasFile('rfq_file')) {
            $filePath = $request->file('rfq_file')->store('rfq', 'public');
        }

        Quote::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'company' => $validated['company'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'] ?? null,
            'file_path' => $filePath,
            'product_id' => $validated['product_id'] ?? null,
        ]);

        $message = match ($validated['type']) {
            'rfq' => 'Your RFQ has been uploaded. Our team will respond within one business day.',
            'bulk' => 'Bulk order enquiry received. We will prepare a quote for you.',
            'source' => 'Sourcing request received. We will search our supplier network.',
            'procurement' => 'Procurement enquiry received. Our B2B team will contact you.',
            default => 'Quote request received. We will respond shortly.',
        };

        return back()->with('success', $message);
    }
}
