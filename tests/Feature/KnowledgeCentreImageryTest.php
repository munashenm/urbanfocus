<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeCentreImageryTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_and_article_cards_use_realistic_featured_photos_not_svg_placeholders(): void
    {
        $mac = $this->publishArticle(
            'Refurbished MacBook Air vs New Laptop: Which Offers Better Value?',
            'refurbished-macbook-air-vs-new-laptop-value',
            'laptops'
        );
        $m365 = $this->publishArticle(
            'Microsoft 365 Business Plans Explained',
            'microsoft-365-business-plans-explained',
            'software'
        );
        $unifi = $this->publishArticle(
            'Ubiquiti Supplier South Africa: A Buyer\'s Guide for Businesses & ISPs',
            'ubiquiti-supplier-south-africa-buyers-guide',
            'networking',
            featured: true
        );

        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('images/blog/laptops.svg', $home);
        $this->assertStringNotContainsString('images/blog/software.svg', $home);
        $this->assertStringNotContainsString('images/blog/networking.svg', $home);
        $this->assertStringContainsString('images/blog/featured/', $home);
        $this->assertStringContainsString('macbook-vs-business-laptop', $home);
        $this->assertStringContainsString('topic-software-office', $home);
        $this->assertStringContainsString('public-wifi-infrastructure', $home);

        $macImage = $mac->displayImageUrl();
        $m365Image = $m365->displayImageUrl();
        $unifiImage = $unifi->displayImageUrl();
        $this->assertNotSame($macImage, $m365Image);
        $this->assertNotSame($m365Image, $unifiImage);
        $this->assertNotSame($macImage, $unifiImage);
        $this->assertStringContainsString('laptop', strtolower($mac->imageAlt()));
        $this->assertStringNotContainsString('.svg', $macImage);

        $article = $this->get(route('blog.show', $unifi))->assertOk();
        $article->assertSee('article-featured-image', false);
        $article->assertSee('public-wifi-infrastructure', false);
        $article->assertSee('og:image', false);
        $article->assertSee('twitter:image', false);
        $article->assertSee($unifi->imageAlt(), false);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.article-featured-image', $css);
        $this->assertStringContainsString('max-height: 500px', $css);
        $this->assertStringContainsString('object-fit: cover', $css);
    }

    public function test_article_hero_and_cards_share_the_same_featured_image(): void
    {
        $article = $this->publishArticle(
            'PoE Security Cameras for Business: A Buying Guide',
            'poe-security-cameras-business-buying-guide',
            'cctv'
        );

        $url = $article->displayImageUrl();
        $this->get(route('blog.index'))->assertOk()->assertSee(basename(parse_url($url, PHP_URL_PATH)), false);
        $this->get(route('blog.show', $article))->assertOk()->assertSee(basename(parse_url($url, PHP_URL_PATH)), false);
        $this->assertStringContainsString('indoor-dome-camera', $url);
        $this->assertStringContainsString('security camera', strtolower($article->imageAlt()));
    }

    public function test_custom_uploaded_image_is_kept_and_svg_placeholders_are_replaced(): void
    {
        $custom = $this->publishArticle('Custom photo article', 'custom-photo-article', 'guides');
        $custom->update(['image' => 'https://cdn.example.test/genuine-product.jpg']);
        $this->assertSame('https://cdn.example.test/genuine-product.jpg', $custom->fresh()->displayImageUrl());

        $legacy = $this->publishArticle('Legacy SVG article', 'legacy-svg-article', 'laptops');
        $legacy->update(['image' => 'images/blog/laptops.svg']);
        $this->assertStringNotContainsString('.svg', $legacy->fresh()->displayImageUrl());
        $this->assertStringContainsString('images/blog/featured/', $legacy->fresh()->displayImageUrl());
    }

    protected function publishArticle(string $title, string $slug, string $category, bool $featured = false): Article
    {
        return Article::create([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => 'Test excerpt for '.$title,
            'content' => '<p>Test content.</p>',
            'category' => $category,
            'is_featured' => $featured,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
