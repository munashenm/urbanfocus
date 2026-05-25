<?php

namespace Tests\Unit;

use App\Services\CatalogFilterService;
use Tests\TestCase;

class CatalogFilterServiceTest extends TestCase
{
    private CatalogFilterService $filter;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'catalog.it_category_heads' => ['Computer Software', 'Computer Memory'],
            'catalog.it_category_exceptions' => ['Software Bundles'],
            'catalog.excluded_category_terms' => ['dictionary', 'lady shaver'],
            'catalog.excluded_product_terms' => ['epilator', 'thesaurus'],
        ]);

        $this->filter = new CatalogFilterService;
    }

    public function test_it_category_head_is_recognised(): void
    {
        $this->assertTrue($this->filter->isItCategoryHead('Computer Software'));
        $this->assertFalse($this->filter->isItCategoryHead('Homeware'));
    }

    public function test_excluded_name_matches_blocklist_term(): void
    {
        $this->assertTrue($this->filter->isExcludedName('Oxford Dictionary'));
        $this->assertFalse($this->filter->isExcludedName('HP Laptop 15'));
    }

    public function test_product_text_matches_combined_blocklist(): void
    {
        $this->assertTrue($this->filter->textMatchesExcludedTerms('Lady shaver replacement head'));
        $this->assertTrue($this->filter->textMatchesExcludedTerms('Compact epilator kit'));
        $this->assertFalse($this->filter->textMatchesExcludedTerms('Logitech MX Master mouse'));
    }

    public function test_excluded_category_path_checks_all_segments(): void
    {
        $this->assertTrue($this->filter->isExcludedCategoryPath('Computer Software > Dictionaries'));
        $this->assertFalse($this->filter->isExcludedCategoryPath('Computer Software > Office Suites'));
    }

    public function test_import_row_rejected_for_non_it_category_head(): void
    {
        $this->assertTrue($this->filter->isExcludedImportRow([
            'category_head' => 'Homeware',
            'category' => 'Bathroom',
            'name' => 'Towel rail',
        ]));
    }

    public function test_import_row_rejected_for_blocked_product_name(): void
    {
        $this->assertTrue($this->filter->isExcludedImportRow([
            'category_head' => 'Computer Software',
            'category' => 'Utilities',
            'name' => 'Roget Thesaurus CD',
        ]));
    }

    public function test_import_row_kept_for_valid_it_product(): void
    {
        $this->assertFalse($this->filter->isExcludedImportRow([
            'category_head' => 'Computer Memory',
            'category' => 'DDR5',
            'name' => 'Kingston 32GB DDR5 RAM',
        ]));
    }

    public function test_it_category_exception_name_is_allowed(): void
    {
        $this->assertTrue($this->filter->isItCategoryExceptionName('Software Bundles'));
    }
}
