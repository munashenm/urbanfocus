<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategorySlugRedirect;
use App\Services\CategoryMapperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_child_urls_do_not_redirect_to_themselves(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();

        $parent = Category::query()->where('slug', 'security-surveillance')->whereNull('parent_id')->firstOrFail();
        $child = Category::query()->where('slug', 'dash-cameras')->where('parent_id', $parent->id)->firstOrFail();

        CategorySlugRedirect::query()->updateOrCreate(
            ['old_slug' => 'dash-cameras'],
            ['target_path' => 'security-surveillance/dash-cameras']
        );

        $this->get('/category/dash-cameras')
            ->assertRedirect('/category/security-surveillance/dash-cameras');

        $this->get($child->url())->assertOk();
    }

    public function test_self_redirect_rows_do_not_loop(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();

        $webcams = Category::query()->create([
            'name' => 'Webcams',
            'slug' => 'webcams',
            'parent_id' => null,
            'is_active' => true,
        ]);

        CategorySlugRedirect::query()->create([
            'old_slug' => 'webcams',
            'target_path' => 'webcams',
        ]);

        $this->get('/category/webcams')
            ->assertOk()
            ->assertSee('Webcams', false);
    }
}
