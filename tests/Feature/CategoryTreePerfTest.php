<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\CatalogFilterService;
use App\Support\CategoryTree;
use App\Support\StorefrontCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryTreePerfTest extends TestCase
{
    use RefreshDatabase;

    public function test_excluded_category_ids_do_not_query_once_per_category(): void
    {
        $root = Category::query()->create([
            'name' => 'Computing & Office',
            'slug' => 'computing-office',
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        for ($i = 0; $i < 40; $i++) {
            Category::query()->create([
                'name' => "Child {$i}",
                'slug' => "child-{$i}",
                'parent_id' => $root->id,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        CategoryTree::forget();
        StorefrontCache::forgetNavigation();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $ids = app(CatalogFilterService::class)->excludedCategoryIds();
        $this->assertIsArray($ids);

        $categoryLookups = collect(DB::getQueryLog())->filter(function (array $query) {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'from "categories"') || str_contains($sql, 'from `categories`');
        });

        $byId = $categoryLookups->filter(function (array $query) {
            $sql = strtolower($query['query']);

            return str_contains($sql, ' in (') || str_contains($sql, '.id = ');
        });

        $this->assertLessThanOrEqual(
            2,
            $categoryLookups->count(),
            'Expected one (maybe two) category table reads, got: '.json_encode(array_column($categoryLookups->all(), 'query'))
        );
        $this->assertCount(
            0,
            $byId,
            'Parent walks must not query categories by id. Queries: '.json_encode(array_column($byId->all(), 'query'))
        );

        DB::flushQueryLog();
        app(CatalogFilterService::class)->excludedCategoryIds();
        $this->assertCount(0, DB::getQueryLog(), 'A second call must use the in-request / cache memo');
    }

    public function test_parent_access_uses_the_in_memory_tree(): void
    {
        $root = Category::query()->create([
            'name' => 'Networking',
            'slug' => 'networking-infrastructure',
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $child = Category::query()->create([
            'name' => 'Switches',
            'slug' => 'network-switches',
            'parent_id' => $root->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        CategoryTree::forget();

        $fresh = Category::query()->find($child->id);
        $this->assertFalse($fresh->relationLoaded('parent'));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $parent = $fresh->parent;
        $this->assertNotNull($parent);
        $this->assertSame($root->id, $parent->id);
        $this->assertStringContainsString('networking-infrastructure', $fresh->url());

        $categoryQueries = collect(DB::getQueryLog())->filter(function (array $query) {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'from "categories"') || str_contains($sql, 'from `categories`');
        });

        $this->assertLessThanOrEqual(1, $categoryQueries->count());
    }

    public function test_walking_the_full_tree_stays_at_one_category_table_read(): void
    {
        $root = Category::query()->create([
            'name' => 'Security & Surveillance',
            'slug' => 'security-surveillance',
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $mid = [];
        for ($i = 0; $i < 20; $i++) {
            $mid[] = Category::query()->create([
                'name' => "Branch {$i}",
                'slug' => "branch-{$i}",
                'parent_id' => $root->id,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        foreach ($mid as $parent) {
            for ($j = 0; $j < 8; $j++) {
                Category::query()->create([
                    'name' => "Leaf {$parent->id}-{$j}",
                    'slug' => "leaf-{$parent->id}-{$j}",
                    'parent_id' => $parent->id,
                    'is_active' => true,
                    'sort_order' => $j,
                ]);
            }
        }

        CategoryTree::forget();
        StorefrontCache::forgetNavigation();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $tree = CategoryTree::current();
        $this->assertGreaterThanOrEqual(180, $tree->all()->count());

        foreach ($tree->all() as $category) {
            $category->parent;
            $category->url();
            $this->assertNotNull($tree->find((int) $category->id));
            $this->assertNotNull($tree->findBySlug((string) $category->slug, $category->parent_id ? (int) $category->parent_id : null));
            $tree->descendantIds((int) $category->id);
        }

        app(CatalogFilterService::class)->excludedCategoryIds();

        $categoryQueries = collect(DB::getQueryLog())->filter(function (array $query) {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'from "categories"') || str_contains($sql, 'from `categories`');
        });

        $byId = $categoryQueries->filter(function (array $query) {
            $sql = strtolower($query['query']);

            return str_contains($sql, ' in (') || str_contains($sql, '.id = ');
        });

        $this->assertLessThanOrEqual(
            2,
            $categoryQueries->count(),
            'Expected at most two category table reads after walking 180+ rows, got: '.json_encode(array_column($categoryQueries->all(), 'query'))
        );
        $this->assertCount(0, $byId, 'Lookups must stay in memory. Queries: '.json_encode(array_column($byId->all(), 'query')));
    }
}
