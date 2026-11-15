<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Observers\ArticleObserver;
use App\Observers\BrandObserver;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Services\CartService;
use App\Services\IndexNowService;
use App\Support\PublicAssetSync;
use App\View\Composers\LayoutComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('helpers.php');

        if ($publicPath = env('PUBLIC_PATH')) {
            $this->app->usePublicPath($publicPath);
        }

        $this->app->singleton(CartService::class);
        $this->app->singleton(IndexNowService::class);
    }

    public function boot(): void
    {
        PublicAssetSync::syncIfNeeded();

        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();
        View::composer(['partials.header', 'partials.footer'], LayoutComposer::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Always generate absolute URLs from APP_URL. Using the request host
            // let a hit on mail.urbanfocus.co.za or the apex domain write that
            // host into the cached pages sitemap (sitemap.pages.*).
            $root = rtrim((string) config('app.url'), '/');
            $host = strtolower((string) parse_url($root, PHP_URL_HOST));
            if ($host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                URL::forceRootUrl($root);
            }
        }

        Product::observe(ProductObserver::class);
        Article::observe(ArticleObserver::class);
        Category::observe(CategoryObserver::class);
        Brand::observe(BrandObserver::class);

        Blade::if('permission', fn (string $permission): bool => auth()->check() && auth()->user()->hasPermission($permission));
        Blade::if('anypermission', fn (...$permissions): bool => auth()->check() && auth()->user()->hasAnyPermission($permissions));
    }
}
