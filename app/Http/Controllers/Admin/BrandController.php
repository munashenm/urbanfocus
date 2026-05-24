<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        $brands = Brand::orderBy('sort_order')->paginate(20);

        return view('admin.brands.index', compact('brands'));
    }

    public function create(): View
    {
        return view('admin.brands.form', ['brand' => new Brand]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->handleLogoUpload($request, $data);
        Brand::create($data);

        return redirect()->route('admin.brands.index')->with('success', 'Brand created.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.form', compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validated($request, $brand->id);
        $this->handleLogoUpload($request, $data);
        $brand->update($data);

        return redirect()->route('admin.brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', 'Brand deleted.');
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:brands,slug,'.$id,
            'website' => 'nullable|url|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'logo_file' => 'nullable|image|max:2048',
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['logo_file']);

        return $data;
    }

    protected function handleLogoUpload(Request $request, array &$data): void
    {
        if (! $request->hasFile('logo_file')) {
            return;
        }

        $directory = public_path('images/brands');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = $request->file('logo_file')->getClientOriginalExtension();
        $filename = $data['slug'].'.'.$extension;
        $request->file('logo_file')->move($directory, $filename);
        $data['logo'] = 'images/brands/'.$filename;
    }
}
