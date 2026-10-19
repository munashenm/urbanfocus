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

    public function test_infer_slug_path_keeps_network_switches_and_moves_lookalikes(): void
    {
        $this->assertSame(
            'networking-connectivity/switches',
            $this->mapper->inferSlugPath(['name' => '24-Port Gigabit Switch'])
        );
        $this->assertSame(
            'computing-office/storage-devices',
            $this->mapper->inferSlugPath(['name' => 'SanDisk 256GB microSDXC for Nintendo Switch'])
        );
        $this->assertSame(
            'computing-office/computer-accessories',
            $this->mapper->inferSlugPath(['name' => '4-Port HDMI KVM Switch'])
        );
        $this->assertSame(
            'gaming-entertainment/console-accessories',
            $this->mapper->inferSlugPath(['name' => 'Nintendo Switch Pro Controller'])
        );
    }

    public function test_maps_scoop_poe_usb_c_adapter_to_poe_equipment(): void
    {
        $path = $this->mapper->mapImportCategories([
            'import_source' => 'scoop',
            'name' => 'Procet PoE to USB-C 5V Power and Data Adapter',
            'brand' => 'PROCET',
        ]);

        $this->assertSame('Networking & Connectivity > POE Equipment', $path);
    }
}
