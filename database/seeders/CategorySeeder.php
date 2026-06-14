<?php

namespace Database\Seeders;

use App\Services\CategoryMapperService;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();
    }
}
