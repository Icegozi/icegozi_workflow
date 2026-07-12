<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_default_admin_when_missing(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('username', 'admin')->firstOrFail();

        $this->assertTrue($admin->is_admin);
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_seeder_does_not_overwrite_existing_admin_credentials(): void
    {
        $existingHash = Hash::make('a-different-secure-password');
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'owner@example.com',
            'password' => $existingHash,
            'is_admin' => true,
        ]);

        $this->seed(UserSeeder::class);

        $admin->refresh();

        $this->assertSame('owner@example.com', $admin->email);
        $this->assertSame($existingHash, $admin->password);
        $this->assertTrue(Hash::check('a-different-secure-password', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }
}
