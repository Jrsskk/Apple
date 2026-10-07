<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_without_email_verification(): void
    {
        Notification::fake();

        $this->get('/register')->assertOk();
        $this->get('/register')->assertDontSee('Student ID');

        $response = $this->post('/register', [
            'name' => 'Avery Marie Student',
            'email' => 'avery@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ]);

        $user = User::where('email', 'avery@example.com')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertNull($user->employee_number);
        $this->assertSame('Avery', $user->first_name);
        $this->assertSame('Marie', $user->middle_name);
        $this->assertSame('Student', $user->last_name);
        $this->assertNull($user->email_verified_at);
        Notification::assertNothingSent();
        $this->get('/dashboard')->assertOk();
    }

    public function test_registration_rejects_duplicate_email_weak_password_and_mismatched_confirmation(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Avery Student',
            'email' => 'taken@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])->assertSessionHasErrors('email');

        $this->post('/register', [
            'name' => 'Avery Student',
            'email' => 'avery@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->post('/register', [
            'name' => 'Avery Student',
            'email' => 'avery@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'DifferentPass1!',
        ])->assertSessionHasErrors('password');
    }

}
