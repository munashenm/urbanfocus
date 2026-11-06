<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryMapperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_archive_survives_invalid_utf8_bio(): void
    {
        $author = Author::query()->create([
            'name' => 'Urban Focus Team',
            'slug' => 'urban-focus-team',
            'bio' => "Guides \xC3\x28 from the Urban Focus team.",
            'is_active' => true,
        ]);

        Article::query()->create([
            'title' => 'Choosing a business switch',
            'slug' => 'choosing-a-business-switch',
            'excerpt' => 'A short buying note.',
            'content' => '<p>Network switches for the office.</p>',
            'author_id' => $author->id,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('blog.author', $author))
            ->assertOk()
            ->assertSee('Articles by Urban Focus Team', false);
    }

    public function test_quote_only_product_schema_includes_the_displayed_price(): void
    {
        $product = Product::factory()->create([
            'name' => 'Quote Only Firewall',
            'slug' => 'quote-only-firewall',
            'price' => 12500,
            'specifications' => ['Availability key' => 'request_quote'],
        ]);

        $this->assertTrue($product->isQuoteOnly());

        $schema = $product->fresh()->toSchemaArray();
        $this->assertSame('12500.00', $schema['offers']['price'] ?? null);
    }

    public function test_quote_product_query_is_noindex_and_b2b_pages_have_their_own_descriptions(): void
    {
        $quote = $this->get('/b2b/quote?product=15')->assertOk();
        $quote->assertSee('noindex, follow', false);
        $quote->assertDontSee(config('seo.defaults.description'), false);

        $rfq = $this->get('/b2b/rfq')->assertOk();
        $rfq->assertDontSee('name="robots" content="noindex', false);
        $rfq->assertDontSee(config('seo.defaults.description'), false);
    }

    public function test_switches_listing_hides_nintendo_and_sd_cards(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();
        $networking = Category::query()->where('slug', 'networking-connectivity')->whereNull('parent_id')->firstOrFail();
        $switches = Category::query()->where('slug', 'switches')->where('parent_id', $networking->id)->firstOrFail();

        Product::factory()->create([
            'name' => '24-Port Gigabit Switch',
            'slug' => '24-port-gigabit-switch',
            'category_id' => $switches->id,
        ]);
        Product::factory()->create([
            'name' => 'SanDisk 128GB microSDXC Nintendo Switch card',
            'slug' => 'sandisk-switch-card',
            'category_id' => $switches->id,
        ]);

        $html = $this->get($switches->url())->assertOk()->getContent();
        $this->assertStringContainsString('24-Port Gigabit Switch', $html);
        $this->assertStringNotContainsString('SanDisk 128GB', $html);
    }

    public function test_titles_are_not_cut_mid_word(): void
    {
        $this->assertSame(
            'Business laptop buying guide | Urban Focus',
            seo_page_title('Business laptop buying guide | Urban Fo')
        );
        $this->assertLessThanOrEqual(65, mb_strlen(seo_page_title(str_repeat('Networking ', 12).'| Urban Focus')));
        $this->assertLessThanOrEqual(160, mb_strlen(seo_meta_description(str_repeat('Delivery across South Africa. ', 12))));
    }

    public function test_homepage_has_a_single_h1_and_a_visible_displays_icon(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('📺', $html);
        $this->assertStringNotContainsString('🖵', $html);
    }
}
