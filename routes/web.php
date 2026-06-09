<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\StockAlertController;
use App\Http\Controllers\B2bController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SeoLandingController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\Admin\SocialController as AdminSocialController;
use App\Http\Controllers\Admin\BlogAutomationController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\QuoteController as AdminQuoteController;
use App\Http\Controllers\Admin\QuotationController as AdminQuotationController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
Route::get('/brands/{brand:slug}', [BrandController::class, 'show'])->name('brands.show');
Route::get('/brand/{brand:slug}', fn (\App\Models\Brand $brand) => redirect()->route('brands.show', $brand, 301));
Route::get('/solutions/{slug}', [SeoLandingController::class, 'show'])->name('solutions.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{category}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/tag/{tag:slug}', [BlogController::class, 'tag'])->name('blog.tag');
Route::get('/blog/author/{author:slug}', [BlogController::class, 'author'])->name('blog.author');
Route::get('/blog/{article:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/product/{product:slug}/stock-alert', [StockAlertController::class, 'store'])->middleware('throttle:10,1')->name('products.stock-alert');

Route::get('/storage/{path}', [StorageController::class, 'show'])->where('path', '.*')->name('storage.serve');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/remove/{product}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/validate-coupon', [CheckoutController::class, 'validateCoupon'])->middleware('throttle:20,1')->name('checkout.validate-coupon');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/checkout/paystack/callback', [CheckoutController::class, 'paystackCallback'])->name('checkout.paystack.callback');
Route::post('/checkout/paystack/webhook', [CheckoutController::class, 'paystackWebhook'])->name('checkout.paystack.webhook');
Route::get('/checkout/paystack/{order}', [CheckoutController::class, 'paystackPay'])->name('checkout.paystack.pay');

Route::get('/track-order', [OrderTrackingController::class, 'showForm'])->name('orders.track');
Route::post('/track-order', [OrderTrackingController::class, 'lookup'])->name('orders.track.lookup');

Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::post('/newsletter', [\App\Http\Controllers\NewsletterController::class, 'store'])->middleware('throttle:5,1')->name('newsletter.store');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/shipping-returns', [PageController::class, 'shipping'])->name('shipping');
Route::get('/returns', [PageController::class, 'returns'])->name('returns');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/warranty', [PageController::class, 'warranty'])->name('warranty');
Route::get('/popia', [PageController::class, 'popia'])->name('popia');
Route::get('/careers', [PageController::class, 'careers'])->name('careers');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');

Route::prefix('b2b')->name('b2b.')->group(function () {
    Route::get('/quote', [B2bController::class, 'quote'])->name('quote');
    Route::get('/rfq', [B2bController::class, 'rfq'])->name('rfq');
    Route::get('/procurement', [B2bController::class, 'procurement'])->name('procurement');
    Route::get('/source-product', [B2bController::class, 'source'])->name('source');
    Route::post('/submit', [B2bController::class, 'store'])->middleware('throttle:5,1')->name('store');
});

Route::get('/request-quote', fn () => redirect()->route('b2b.quote'))->name('quote.request');
Route::get('/upload-rfq', fn () => redirect()->route('b2b.rfq'))->name('rfq.upload');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders/{order}', [AccountController::class, 'orderShow'])->name('orders.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

Route::middleware('auth')->get('/orders/{order}/invoice', [InvoiceController::class, 'show'])->name('orders.invoice');

Route::get('/rss.xml', [SeoController::class, 'blogRss'])->name('feeds.rss');
Route::get('/facebook-feed.xml', [SeoController::class, 'facebookCatalog'])->name('feeds.facebook');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/sitemap-images.xml', [SeoController::class, 'imageSitemap'])->name('sitemap.images');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/feeds/google-merchant.xml', [SeoController::class, 'googleMerchantFeed'])->name('feeds.google');
Route::get('/feeds/pricecheck.csv', [SeoController::class, 'priceCheckFeed'])->name('feeds.pricecheck');
Route::get('/feeds/bobshop.xml', [SeoController::class, 'bobShopXmlFeed'])->name('feeds.bobshop');
Route::get('/feeds/bobshop.csv', [SeoController::class, 'bobShopBulkloadCsv'])->name('feeds.bobshop.csv');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:products.view,products.create,products.edit,products.delete')->group(function () {
        Route::post('products/bulk-destroy', [AdminProductController::class, 'bulkDestroy'])->middleware('permission:products.delete')->name('products.bulk-destroy');
        Route::post('products/bulk-update', [AdminProductController::class, 'bulkUpdate'])->middleware('permission:products.edit')->name('products.bulk-update');
        Route::get('products/export/csv', [AdminProductController::class, 'export'])->name('products.export');
        Route::post('products/{product}/duplicate', [AdminProductController::class, 'duplicate'])->middleware('permission:products.create')->name('products.duplicate');
        Route::delete('products/{product}/images/{image}', [AdminProductController::class, 'destroyImage'])->middleware('permission:products.edit')->name('products.images.destroy');
        Route::patch('products/{product}/images/{image}/primary', [AdminProductController::class, 'setPrimaryImage'])->middleware('permission:products.edit')->name('products.images.primary');
        Route::resource('products', AdminProductController::class)->except(['show'])->withTrashed();
        Route::post('categories/bulk-destroy', [AdminCategoryController::class, 'bulkDestroy'])->middleware('permission:products.delete')->name('categories.bulk-destroy');
        Route::resource('categories', AdminCategoryController::class)->except(['show']);
        Route::resource('brands', AdminBrandController::class)->except(['show']);
        Route::get('inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
        Route::get('catalog', [AdminCatalogController::class, 'index'])->name('catalog.index');
        Route::post('catalog/import', [AdminCatalogController::class, 'import'])->middleware('permission:products.create')->name('catalog.import');
        Route::post('catalog/import/preview', [AdminCatalogController::class, 'importPreview'])->middleware('permission:products.create')->name('catalog.import-preview');
        Route::post('catalog/clear-products', [AdminCatalogController::class, 'clearProducts'])->middleware('permission:products.delete')->name('catalog.clear-products');
        Route::post('catalog/remove-non-it', [AdminCatalogController::class, 'removeNonIt'])->middleware('permission:products.delete')->name('catalog.remove-non-it');
        Route::post('catalog/consolidate-categories', [AdminCatalogController::class, 'consolidateCategories'])->middleware('permission:products.edit')->name('catalog.consolidate-categories');
        Route::post('catalog/optimize-seo', [AdminCatalogController::class, 'optimizeSeo'])->middleware('permission:products.edit')->name('catalog.optimize-seo');
        Route::get('catalog/export', [AdminCatalogController::class, 'export'])->name('catalog.export');
        Route::get('catalog/export/woocommerce', [AdminCatalogController::class, 'exportWooCommerce'])->name('catalog.export.woocommerce');
        Route::get('catalog/export/ineligible', [AdminCatalogController::class, 'exportIneligible'])->name('catalog.export-ineligible');
        Route::post('catalog/bulk-fix-merchant', [AdminCatalogController::class, 'bulkFixMerchant'])->middleware('permission:products.edit')->name('catalog.bulk-fix-merchant');
        Route::post('catalog/api-key', [AdminCatalogController::class, 'regenerateApiKey'])->middleware('permission:settings.manage')->name('catalog.api-key');
        Route::get('import', fn () => redirect()->route('admin.catalog.index'))->name('import.index');
        Route::post('import', [AdminCatalogController::class, 'import'])->middleware('permission:products.create')->name('import.store');
    });

    Route::middleware('permission:orders.view,orders.edit,orders.refund')->group(function () {
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::put('orders/{order}', [AdminOrderController::class, 'update'])->middleware('permission:orders.edit')->name('orders.update');
    });

    Route::middleware('permission:customers.view,customers.edit')->group(function () {
        Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
        Route::put('customers/{customer}', [AdminCustomerController::class, 'update'])->middleware('permission:customers.edit')->name('customers.update');
    });

    Route::middleware('permission:rfqs.view,rfqs.create,rfqs.edit,quotations.create')->group(function () {
        Route::get('quotes', [AdminQuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quote}', [AdminQuoteController::class, 'show'])->name('quotes.show');
        Route::put('quotes/{quote}', [AdminQuoteController::class, 'update'])->middleware('permission:rfqs.edit')->name('quotes.update');
        Route::get('quotations/products/search', [AdminQuotationController::class, 'productSearch'])->name('quotations.products.search');
        Route::get('quotations/{quotation}/print', [AdminQuotationController::class, 'print'])->name('quotations.print');
        Route::get('quotations/{quotation}/download', [AdminQuotationController::class, 'download'])->name('quotations.download');
        Route::resource('quotations', AdminQuotationController::class);
    });

    Route::middleware('permission:products.edit')->group(function () {
        Route::resource('coupons', AdminCouponController::class)->except(['show']);
        Route::resource('banners', AdminBannerController::class)->except(['show']);
        Route::post('articles/sync-news', [AdminArticleController::class, 'syncNews'])->name('articles.sync');
        Route::post('articles/seed-pillars', [AdminArticleController::class, 'seedPillars'])->name('articles.seed-pillars');
        Route::post('articles/bulk-publish', [AdminArticleController::class, 'bulkPublish'])->name('articles.bulk-publish');
        Route::post('articles/bulk-unpublish', [AdminArticleController::class, 'bulkUnpublish'])->name('articles.bulk-unpublish');
        Route::post('articles/bulk-destroy', [AdminArticleController::class, 'bulkDestroy'])->name('articles.bulk-destroy');
        Route::post('articles/{article}/toggle-publish', [AdminArticleController::class, 'togglePublish'])->name('articles.toggle-publish');
        Route::resource('articles', AdminArticleController::class)->except(['show']);
        Route::get('blog-strategy', [BlogAutomationController::class, 'index'])->name('blog-strategy.index');
        Route::post('blog-strategy/discover-topics', [BlogAutomationController::class, 'discoverTopics'])->name('blog-strategy.discover');
        Route::post('blog-strategy/sync-gsc', [BlogAutomationController::class, 'syncSearchConsole'])->name('blog-strategy.sync-gsc');
        Route::post('blog-strategy/topics/{topic}/draft', [BlogAutomationController::class, 'draftFromTopic'])->name('blog-strategy.draft');
        Route::get('social', [AdminSocialController::class, 'index'])->name('social.index');
        Route::post('social/publish', [AdminSocialController::class, 'publish'])->name('social.publish');
        Route::post('social/retry-failed', [AdminSocialController::class, 'retryFailed'])->name('social.retry-failed');
        Route::post('social/queue-all', [AdminSocialController::class, 'queueAll'])->name('social.queue-all');
        Route::post('social/webhooks/{webhookLog}/retry', [AdminSocialController::class, 'retryWebhook'])->name('social.webhook-retry');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [AdminReportController::class, 'export'])->name('reports.export');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', AdminUserController::class)->except(['show']);
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::get('roles/{role}/edit', [AdminRoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:audit_logs.view')->group(function () {
        Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
