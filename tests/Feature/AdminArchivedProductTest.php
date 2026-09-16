<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArchivedProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_products_can_be_published_from_the_admin_list(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create([
            'name' => 'Signal Fire AI-5 Pro 6-Motor Fibre Fusion Splicer Complete Kit',
            'sku' => 'UF-SF-AI5PRO',
            'is_active' => true,
        ]);
        $product->applyPublicationStatus('archived');

        $this->assertTrue($product->fresh() === null);
        $this->assertNotNull(Product::onlyTrashed()->find($product->id));

        $this->actingAs($admin)
            ->from(route('admin.products.index', ['status' => 'archived']))
            ->post(route('admin.products.bulk-update'), [
                'ids' => [$product->id],
                'action' => 'publish',
            ])
            ->assertRedirect(route('admin.products.index', ['status' => 'archived']))
            ->assertSessionHas('success');

        $fresh = Product::query()->find($product->id);
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->trashed());
        $this->assertTrue($fresh->is_active);
        $this->assertSame('published', $fresh->publicationStatus());
    }

    public function test_archived_products_can_be_permanently_deleted(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Archived HDMI Socket']);
        $product->applyPublicationStatus('archived');
        $id = $product->id;

        $this->actingAs($admin)
            ->from(route('admin.products.index', ['status' => 'archived']))
            ->post(route('admin.products.bulk-destroy'), [
                'ids' => [$id],
            ])
            ->assertRedirect(route('admin.products.index', ['status' => 'archived']))
            ->assertSessionHas('success');

        $this->assertNull(Product::withTrashed()->find($id));
    }

    public function test_archived_product_can_be_published_from_the_edit_form(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create([
            'name' => 'Archived Mini UPS',
            'price' => 769,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
        $product->applyPublicationStatus('archived');

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'name' => 'Archived Mini UPS',
                'price' => 769,
                'stock_quantity' => 0,
                'publication_status' => 'published',
                'manage_stock' => 0,
                'in_stock' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.products.edit', $product));

        $fresh = Product::query()->find($product->id);
        $this->assertNotNull($fresh);
        $this->assertSame('published', $fresh->publicationStatus());
    }

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    }
}
