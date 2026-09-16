<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_an_image_does_not_delete_the_product(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Keep This Product']);
        $keep = $this->attachImage($product, 'keep.webp', primary: true);
        $remove = $this->attachImage($product, 'remove.webp', primary: false);

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->delete(route('admin.products.images.destroy', [$product, $remove]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHas('success', 'Image removed.');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Keep This Product']);
        $this->assertDatabaseMissing('product_images', ['id' => $remove->id]);
        $this->assertDatabaseHas('product_images', ['id' => $keep->id, 'product_id' => $product->id]);
        $this->assertFalse($product->trashed());
    }

    public function test_edit_form_does_not_nest_image_delete_inside_the_product_form(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();
        $image = $this->attachImage($product, 'gallery.webp');

        $html = $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('form="delete-image-'.$image->id.'"', $html);
        $this->assertStringContainsString('id="delete-image-'.$image->id.'"', $html);

        $productFormEnd = strpos($html, '</form>');
        $deleteForm = strpos($html, 'id="delete-image-'.$image->id.'"');
        $this->assertNotFalse($productFormEnd);
        $this->assertNotFalse($deleteForm);
        $this->assertGreaterThan($productFormEnd, $deleteForm, 'Image delete form must sit outside the product save form.');
    }

    public function test_method_spoofed_delete_on_the_update_url_does_not_destroy_the_product(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Must Remain']);
        $image = $this->attachImage($product, 'one.webp');

        $response = $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->post(route('admin.products.update', $product), [
                '_method' => 'DELETE',
                'name' => $product->name,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
            ]);

        $this->assertTrue(in_array($response->status(), [404, 405], true));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Must Remain']);
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    }

    protected function attachImage(Product $product, string $filename, bool $primary = true): ProductImage
    {
        return ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/'.$product->id.'/'.$filename,
            'alt_text' => $product->name,
            'sort_order' => $primary ? 0 : 1,
            'is_primary' => $primary,
        ]);
    }
}
