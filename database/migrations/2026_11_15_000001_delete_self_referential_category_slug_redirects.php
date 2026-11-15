<?php

use App\Services\CategorySlugRedirectCleaner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove category_slug_redirects rows that 301 a URL to itself.
     * Real redirects (old slug → a different canonical path) are kept.
     * Down does not restore the deleted rows; they were loops, not content.
     */
    public function up(): void
    {
        if (! Schema::hasTable('category_slug_redirects')) {
            return;
        }

        app(CategorySlugRedirectCleaner::class)->deleteSelfReferential();
    }

    public function down(): void
    {
        // Intentionally empty. The deleted rows redirected to the current URL.
    }
};
