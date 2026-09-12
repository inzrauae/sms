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
        $response = $this->postJson('/auth/register', [
            'name' => 'Kasun Silva',
            'company' => 'Silva Stores',
            'email' => 'kasun@example.lk',
            'phone' => '0712345678',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('users', [
            'email' => 'kasun@example.lk',
            'name' => 'Kasun Silva',
            'credits' => 10, // signup bonus
        ]);

        $this->assertAuthenticated();
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.lk']);

        $response = $this->postJson('/auth/register', [
            'name' => 'Another User',
            'email' => 'duplicate@example.lk',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.lk',
            'password' => 'secret12345',
            'role' => 'user',
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'user@example.lk',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => ['role' => 'user'],
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.lk',
            'password' => 'correctpass',
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'user@example.lk',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJson(['status' => 'error']);

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->suspended()->create([
            'email' => 'suspended@example.lk',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'suspended@example.lk',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['status' => 'error']);

        $this->assertGuest();
    }

    public function test_session_endpoint_returns_user_data_when_authenticated(): void
    {
        $user = User::factory()->create(['credits' => 50]);

        $response = $this->actingAs($user)->getJson('/auth/session');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'email' => $user->email,
                    'credits' => 50,
                ],
            ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertGuest();
    }
}

