<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeholder_pixels_stay_out_and_real_ids_render(): void
    {
        config([
            'seo.analytics.gtm_id' => '',
            'seo.analytics.ga4_id' => '',
            'seo.analytics.meta_pixel_id' => '123456789',
            'seo.analytics.tiktok_pixel_id' => 'XXXXXXXX',
        ]);

        $hidden = $this->get(route('contact'))->assertOk()->getContent();
        $this->assertStringNotContainsString('fbq(', $hidden);
        $this->assertStringNotContainsString('123456789', $hidden);
        $this->assertStringNotContainsString('ttq.load', $hidden);
        $this->assertStringNotContainsString('XXXXXXXX', $hidden);

        config([
            'seo.analytics.meta_pixel_id' => '109876543210987',
            'seo.analytics.tiktok_pixel_id' => 'C9A1B2C3D4E5F6',
        ]);

        $shown = $this->get(route('contact'))->assertOk()->getContent();
        $this->assertStringContainsString('fbq(', $shown);
        $this->assertStringContainsString('109876543210987', $shown);
        $this->assertStringContainsString('ttq.load', $shown);
        $this->assertStringContainsString('C9A1B2C3D4E5F6', $shown);
    }

    public function test_csp_allows_cloudflare_insights_and_security_headers_are_set(): void
    {
        $response = $this->withHeader('X-Forwarded-Proto', 'https')->get(route('home'));

        $response->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://static.cloudflareinsights.com', $csp);
        $this->assertStringContainsString('https://cloudflareinsights.com', $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('https://checkout.paystack.com', $csp);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_plain_http_on_the_public_host_redirects_to_https_www_once(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://www.urbanfocus.co.za']);

        $this->get('http://urbanfocus.co.za/shop?q=hp')
            ->assertRedirect('https://www.urbanfocus.co.za/shop?q=hp');

        $this->get('http://www.urbanfocus.co.za/contact')
            ->assertRedirect('https://www.urbanfocus.co.za/contact');

        $this->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://www.urbanfocus.co.za/contact')
            ->assertOk();

        $this->post('http://www.urbanfocus.co.za/checkout/paystack/webhook', ['event' => 'charge.success'])
            ->assertStatus(401);
    }

    public function test_htaccess_blocks_readme_and_detects_cloudflare_https(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));
        $this->assertIsString($htaccess);
        $this->assertStringContainsString('X-Forwarded-Proto', $htaccess);
        $this->assertStringContainsString('CF-Visitor', $htaccess);
        $this->assertStringContainsString('https://www.urbanfocus.co.za', $htaccess);
        $this->assertStringNotContainsString('RewriteCond %{HTTPS} !=on', $htaccess);
        $this->assertStringContainsString('readme', strtolower($htaccess));
        $this->assertStringContainsString('composer.json', $htaccess);
        $this->assertStringContainsString('artisan', $htaccess);
        $this->assertDoesNotMatchRegularExpression('/ChangeMe123!/', $htaccess);
    }

    public function test_deploy_deletes_a_stray_readme_and_leaves_index_php(): void
    {
        $root = sys_get_temp_dir().'/uf-sec-root-'.uniqid();
        $html = sys_get_temp_dir().'/uf-sec-html-'.uniqid();
        mkdir($root.'/public/css', 0777, true);
        mkdir($html, 0777, true);
        file_put_contents($root.'/public/css/app.css', 'body{}');
        file_put_contents($html.'/README.md', 'admin password');
        file_put_contents($html.'/composer.json', '{}');
        file_put_contents($html.'/index.php', '<?php echo "LIVE";');

        require_once base_path('deploy/cpanel-asset-sync.php');
        cpanel_sync_public_assets($root, $html);

        $this->assertFileDoesNotExist($html.'/README.md');
        $this->assertFileDoesNotExist($html.'/composer.json');
        $this->assertSame('<?php echo "LIVE";', file_get_contents($html.'/index.php'));
        $this->assertFileExists($html.'/css/app.css');
    }

    public function test_admin_seeder_does_not_reset_an_existing_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@urbanfocus.co.za',
            'password' => Hash::make('existing-secret'),
            'is_admin' => true,
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Hash::check('existing-secret', $user->fresh()->password));
        $this->assertStringNotContainsString('ChangeMe123!', (string) file_get_contents(base_path('README.md')));
        $this->assertStringNotContainsString('ChangeMe123!', (string) file_get_contents(base_path('database/seeders/DatabaseSeeder.php')));
    }
}
