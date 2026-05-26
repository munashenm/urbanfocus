<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Product;
use App\Observers\ArticleObserver;
use App\Observers\ProductObserver;
use App\View\Composers\LayoutComposer;
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
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();
        View::composer(['partials.header', 'partials.footer'], LayoutComposer::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Product::observe(ProductObserver::class);
        Article::observe(ArticleObserver::class);
    }
}
