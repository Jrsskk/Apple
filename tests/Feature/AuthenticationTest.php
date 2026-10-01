<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@edusync.test',
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'role' => UserRole::Student,
        ]);

        $response = $this->post('/login', [
            'email' => 'test@edusync.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_log_in_with_a_username(): void
    {
        $user = User::factory()->create([
            'username' => 'username-login',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => 'username-login',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@edusync.test',
            'password' => bcrypt('password'),
            'status' => 'inactive',
        ]);

        $this->post('/login', ['email' => 'inactive@edusync.test', 'password' => 'password']);
        $this->assertGuest();
    }
}
