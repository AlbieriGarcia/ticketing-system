<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ana Pérez',
            'email' => 'Ana@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.role', 'customer')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
    }

    public function test_register_rejects_duplicated_email(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Ana',
            'email' => 'ANA@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_user_can_login_and_get_token(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', [
            'email' => 'ana@example.com',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token', 'token_type', 'expires_in_minutes']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', [
            'email' => 'ana@example.com',
            'password' => 'wrong-pass1',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret123',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_me_returns_authenticated_user(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret123']);
        $token = $this->postJson('/api/auth/login', [
            'email' => 'ana@example.com',
            'password' => 'secret123',
        ])->json('token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com');
    }

    public function test_me_requires_authentication(): void
    {
        $this->get('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
