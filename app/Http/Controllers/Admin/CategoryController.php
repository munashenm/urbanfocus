<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $parents = Category::query()
            ->topLevel()
            ->with(['children' => fn ($q) => $q->withCount('products')->orderBy('sort_order')])
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        $orphanCount = Category::query()
            ->whereNotNull('parent_id')
            ->whereDoesntHave('parent')
            ->count();

        return view('admin.categories.index', compact('parents', 'orphanCount'));
    }

    public function create(Request $request): View
    {
        $parents = Category::topLevel()->orderBy('sort_order')->get();
        $presetParentId = $request->integer('parent_id') ?: null;

        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true, 'parent_id' => $presetParentId]),
            'parents' => $parents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        $parents = Category::topLevel()
            ->where('id', '!=', $category->id)
            ->orderBy('sort_order')
            ->get();

        return view('admin.categories.form', compact('category', 'parents'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category->id);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);

        if ((int) ($validated['parent_id'] ?? 0) === $category->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent.'])->withInput();
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete a category with products. Disable it instead or move products first.');
        }

        if ($category->children()->exists()) {
            return back()->with('error', 'Cannot delete a category that still has subcategories.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:categories,id',
        ]);

        $blocked = Category::whereIn('id', $validated['ids'])
            ->where(function ($q) {
                $q->has('products')->orHas('children');
            })
            ->count();

        if ($blocked > 0) {
            return back()->with('error', "{$blocked} selected categor".($blocked === 1 ? 'y has' : 'ies have').' products or subcategories and were not deleted.');
        }

        $deleted = Category::whereIn('id', $validated['ids'])->delete();

        return redirect()
            ->route('admin.categories.index', $request->only('page'))
            ->with('success', "{$deleted} categor".($deleted === 1 ? 'y' : 'ies').' deleted.');
    }

    public function children(Category $category): JsonResponse
    {
        $children = $category->children()
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        return response()->json($children);
    }

    protected function validateCategory(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug,'.$id,
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
