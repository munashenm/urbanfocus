<?php

namespace Tests\Unit;

use App\Services\CategoryMapperService;
use Tests\TestCase;

class CategoryMapperServiceTest extends TestCase
{
    private CategoryMapperService $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new CategoryMapperService;
    }

    public function test_maps_esquire_category_head_to_canonical_path(): void
    {
        $path = $this->mapper->mapImportCategories([
            'category_head' => 'Computer Memory',
            'category' => 'DDR5',
        ]);

        $this->assertSame('Components & Storage > Memory (RAM)', $path);
    }

    public function test_maps_pinnacle_tree_to_canonical_path(): void
    {
        $path = $this->mapper->mapImportCategories([
            'category_tree' => 'computing/storage/flash',
        ]);

        $this->assertSame('Components & Storage > SSDs & Hard Drives', $path);
    }

    public function test_maps_existing_category_parts_by_head_name(): void
    {
        $path = $this->mapper->mapCategoryParts(['Cables & Adapters', 'Cable: Power']);

        $this->assertSame('Peripherals & Accessories > Cables & Adapters', $path);
    }
}
