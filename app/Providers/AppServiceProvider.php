<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Product;
use App\Observers\ArticleObserver;
use App\Observers\ProductObserver;
use App\Support\PublicAssetSync;
use App\View\Composers\LayoutComposer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('helpers.php');

        if ($publicPath = env('PUBLIC_PATH')) {
            $this->app->usePublicPath($publicPath);
        }
    }

    public function boot(): void
    {
        PublicAssetSync::syncIfNeeded();

        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();
        View::composer(['partials.header', 'partials.footer'], LayoutComposer::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Match asset()/route() URLs to the host the visitor actually used (www vs apex).
            if (! $this->app->runningInConsole() && request()->getHost()) {
                URL::forceRootUrl('https://'.request()->getHttpHost());
            }
        }

        Product::observe(ProductObserver::class);
        Article::observe(ArticleObserver::class);

        Blade::if('permission', fn (string $permission): bool => auth()->check() && auth()->user()->hasPermission($permission));
        Blade::if('anypermission', fn (...$permissions): bool => auth()->check() && auth()->user()->hasAnyPermission($permissions));
    }
}
