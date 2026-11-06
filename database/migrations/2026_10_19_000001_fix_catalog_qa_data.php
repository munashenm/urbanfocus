<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_slug_redirects')) {
            DB::table('category_slug_redirects')
                ->whereColumn('old_slug', 'target_path')
                ->delete();
        }

        $this->replaceCopy();
        $this->recategoriseSwitches();
    }

    public function down(): void
    {
        // Copy and category corrections are not reversed.
    }

    protected function replaceCopy(): void
    {
        $replacements = [
            'Poer Supply' => 'Power Supply',
            'Reciever' => 'Receiver',
            'Build-In Mic' => 'Built-In Mic',
            'Build-In' => 'Built-In',
            'Lenght' => 'Length',
            'intergrated' => 'integrated',
            'Intergrated' => 'Integrated',
            'Strems' => 'Streams',
            'Florescent' => 'Fluorescent',
            'Dimansion' => 'Dimension',
            'Capicity' => 'Capacity',
            'wither side' => 'either side',
            'Wither side' => 'Either side',
        ];

        $columnsByTable = [
            'products' => ['name', 'short_description', 'description', 'meta_title', 'meta_description'],
            'categories' => ['name', 'description', 'meta_title', 'meta_description'],
            'articles' => ['title', 'excerpt', 'content', 'meta_title', 'meta_description'],
        ];

        foreach ($columnsByTable as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = Schema::getColumnListing($table);

            foreach ($columns as $column) {
                if (! in_array($column, $existing, true)) {
                    continue;
                }

                foreach ($replacements as $from => $to) {
                    DB::table($table)
                        ->where($column, 'like', '%'.$from.'%')
                        ->update([
                            $column => DB::raw(
                                'REPLACE('.$column.', '.DB::getPdo()->quote($from).', '.DB::getPdo()->quote($to).')'
                            ),
                        ]);
                }
            }
        }
    }

    protected function recategoriseSwitches(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return;
        }

        $switches = Category::query()->where('slug', 'switches')->first();
        if (! $switches) {
            return;
        }

        $storage = Category::query()->where('slug', 'storage-devices')->value('id');
        $accessories = Category::query()->where('slug', 'computer-accessories')->value('id');
        $console = Category::query()->where('slug', 'console-accessories')->value('id');

        Product::query()
            ->where('category_id', $switches->id)
            ->orderBy('id')
            ->select(['id', 'name'])
            ->chunkById(200, function ($products) use ($storage, $accessories, $console) {
                foreach ($products as $product) {
                    $name = strtolower((string) $product->name);
                    $target = null;

                    if (str_contains($name, 'nintendo') && $console) {
                        $target = $console;
                    } elseif ($storage && preg_match('/micro\s*sd|microsd|sdxc|sdhc|sd card|memory card/', $name)) {
                        $target = $storage;
                    } elseif ($accessories && preg_match('/\bkvm\b/', $name)) {
                        $target = $accessories;
                    }

                    if ($target) {
                        Product::query()->where('id', $product->id)->update(['category_id' => $target]);
                    }
                }
            });
    }
};
