<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_viewer_cannot_access_user_management(): void
    {
        $viewer = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $viewer->syncRoles(['viewer']);

        $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_locked_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'password' => bcrypt('Password123!'),
            'is_admin' => true,
            'is_active' => true,
            'locked_until' => now()->addMinutes(10),
        ]);
        $user->syncRoles(['admin']);

        $this->post(route('login'), [
            'email' => 'locked@example.com',
            'password' => 'Password123!',
        ])->assertSessionHasErrors('email');
    }
}
