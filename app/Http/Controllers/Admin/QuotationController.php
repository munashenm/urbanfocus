<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\Quote;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        protected QuotationService $quotations
    ) {}

    public function index(Request $request): View
    {
        $query = Quotation::with('creator')->latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_company', 'like', '%'.$search.'%')
                    ->orWhere('customer_email', 'like', '%'.$search.'%');
            });
        }

        $quotations = $query->paginate(20)->withQueryString();

        return view('admin.quotations.index', compact('quotations'));
    }

    public function create(Request $request): View
    {
        $prefill = null;
        if ($request->filled('from_quote')) {
            $enquiry = Quote::with('product')->find($request->integer('from_quote'));
            if ($enquiry) {
                $prefill = $this->quotations->prefillFromEnquiry($enquiry);
            }
        }

        return view('admin.quotations.create', [
            'quotation' => null,
            'prefill' => $prefill,
            'defaultValidUntil' => $this->quotations->defaultValidUntil()->format('Y-m-d'),
            'defaultTerms' => $this->quotations->defaultTerms(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateQuotation($request);

        $quotation = Quotation::create([
            ...$this->customerFields($validated),
            'quotation_number' => $this->quotations->nextNumber(),
            'status' => $validated['status'] ?? 'draft',
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'source_quote_id' => $validated['source_quote_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->quotations->syncItems($quotation, $validated['items']);

        return redirect()
            ->route('admin.quotations.show', $quotation)
            ->with('success', 'Quotation '.$quotation->quotation_number.' created.');
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['items.product', 'sourceQuote', 'creator']);

        return view('admin.quotations.show', [
            'quotation' => $quotation,
            'banking' => $this->quotations->bankingDetails(),
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        $quotation->load('items');

        return view('admin.quotations.edit', [
            'quotation' => $quotation,
            'prefill' => null,
            'defaultValidUntil' => $quotation->valid_until?->format('Y-m-d') ?? $this->quotations->defaultValidUntil()->format('Y-m-d'),
            'defaultTerms' => $quotation->terms ?? $this->quotations->defaultTerms(),
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $validated = $this->validateQuotation($request);

        $quotation->update([
            ...$this->customerFields($validated),
            'status' => $validated['status'],
            'discount_amount' => $validated['discount_amount'] ?? 0,
        ]);

        $this->quotations->syncItems($quotation, $validated['items']);

        return redirect()
            ->route('admin.quotations.show', $quotation)
            ->with('success', 'Quotation updated.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        $number = $quotation->quotation_number;
        $quotation->delete();

        return redirect()
            ->route('admin.quotations.index')
            ->with('success', 'Quotation '.$number.' deleted.');
    }

    public function print(Quotation $quotation): View
    {
        return view('quotations.document', $this->quotations->documentData($quotation));
    }

    public function download(Quotation $quotation): Response
    {
        $html = view('quotations.document', $this->quotations->documentData($quotation))->render();

        $filename = $quotation->quotation_number.'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function productSearch(Request $request): JsonResponse
    {
        $q = (string) $request->get('q', '');

        return response()->json([
            'products' => $this->quotations->searchProducts($q),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateQuotation(Request $request): array
    {
        return $request->validate([
            'status' => 'required|in:draft,sent,accepted,declined,expired',
            'customer_name' => 'required|string|max:255',
            'customer_company' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_vat_number' => 'nullable|string|max:50',
            'billing_address_line_1' => 'nullable|string|max:255',
            'billing_address_line_2' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_province' => 'nullable|string|max:100',
            'billing_postal_code' => 'nullable|string|max:20',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'terms' => 'nullable|string|max:5000',
            'internal_notes' => 'nullable|string|max:5000',
            'discount_amount' => 'nullable|numeric|min:0',
            'source_quote_id' => 'nullable|exists:quotes,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_id' => 'nullable|exists:products,id',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function customerFields(array $validated): array
    {
        return [
            'customer_name' => $validated['customer_name'],
            'customer_company' => $validated['customer_company'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_vat_number' => $validated['customer_vat_number'] ?? null,
            'billing_address_line_1' => $validated['billing_address_line_1'] ?? null,
            'billing_address_line_2' => $validated['billing_address_line_2'] ?? null,
            'billing_city' => $validated['billing_city'] ?? null,
            'billing_province' => $validated['billing_province'] ?? null,
            'billing_postal_code' => $validated['billing_postal_code'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'internal_notes' => $validated['internal_notes'] ?? null,
        ];
    }
}
