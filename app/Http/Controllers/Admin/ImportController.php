<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WooCommerceImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('admin.import.index');
    }

    public function store(Request $request, WooCommerceImportService $importService): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $result = $importService->import($request->file('csv_file'));

        $message = "Imported {$result['imported']} products, updated {$result['updated']}.";

        if (! empty($result['errors'])) {
            return back()->with('warning', $message.' Some rows failed: '.implode('; ', array_slice($result['errors'], 0, 5)));
        }

        return back()->with('success', $message);
    }
}
