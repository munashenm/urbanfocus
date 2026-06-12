<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function __construct(protected ImageService $images) {}
    public function index(): View
    {
        $banners = Banner::orderBy('sort_order')->paginate(20);

        return view('admin.banners.index', compact('banners'));
    }

    public function create(): View
    {
        return view('admin.banners.form', ['banner' => new Banner]);
    }

    public function store(Request $request): RedirectResponse
    {
        Banner::create($this->validated($request, null));

        return redirect()->route('admin.banners.index')->with('success', 'Banner created.');
    }

    public function edit(Banner $banner): View
    {
        return view('admin.banners.form', compact('banner'));
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $banner->update($this->validated($request, $banner));

        return redirect()->route('admin.banners.index')->with('success', 'Banner updated.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Banner deleted.');
    }

    protected function validated(Request $request, ?Banner $banner = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:5120',
            'link' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:50',
            'placement' => 'required|in:home,shop',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        unset($data['image_file']);

        if ($request->hasFile('image_file')) {
            if ($banner?->image && ! str_starts_with($banner->image, 'http')) {
                $this->images->delete($banner->image);
            }

            $data['image'] = $this->storeBannerImage($request);
        } elseif (! $request->filled('image')) {
            if ($banner) {
                unset($data['image']);
            } else {
                $data['image'] = null;
            }
        }

        return $data;
    }

    protected function storeBannerImage(Request $request): string
    {
        $file = $request->file('image_file');
        $path = 'banners/'.uniqid('', true).'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            abort(422, 'Could not read uploaded banner image.');
        }

        Storage::disk('public')->makeDirectory('banners');
        Storage::disk('public')->put($path, $contents);

        $target = public_path('storage/'.$path);
        $directory = dirname($target);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($target, $contents);

        return $path;
    }
}
