<?php

namespace Tests\Feature;

use App\Models\SenderId;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.textlk.token', 'test-upstream-token');
    }

    public function test_non_admin_cannot_access_admin_console(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/console');
        $response->assertRedirect('/dashboard');

        $apiResponse = $this->actingAs($user)->getJson('/admin/overview');
        $apiResponse->assertStatus(403);
    }

    public function test_admin_can_view_console_and_overview(): void
    {
        $admin = User::factory()->admin()->create();

        Http::fake([
            'https://app.text.lk/api/v3/balance' => Http::response([
                'status' => 'success',
                'data' => ['remaining_sms_unit' => 10000],
            ], 200),
        ]);

        $pageResponse = $this->actingAs($admin)->get('/console');
        $pageResponse->assertStatus(200);

        $overviewResponse = $this->actingAs($admin)->getJson('/admin/overview');
        $overviewResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'upstream_units' => 10000,
                ],
            ]);
    }

    public function test_admin_can_adjust_user_credits_and_clear_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['credits' => 50, 'rate' => 1.00]);

        // Create a top-up request
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'request',
            'units' => 200,
            'amount' => 200,
            'balance_after' => 50,
        ]);

        $response = $this->actingAs($admin)->postJson("/admin/users/{$user->id}/credits", [
            'units' => 200,
            'note' => 'Payment received via bank transfer',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => ['credits' => 250],
            ]);

        $this->assertEquals(250, $user->fresh()->credits);

        // Queued request should be cleared
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'type' => 'request',
        ]);
    }

    public function test_admin_can_update_user_rate_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['rate' => 1.10, 'status' => 'active']);

        $response = $this->actingAs($admin)->patchJson("/admin/users/{$user->id}", [
            'rate' => 0.85,
            'status' => 'suspended',
            'role' => 'user',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertEquals(0.85, $user->fresh()->rate);
        $this->assertEquals('suspended', $user->fresh()->status);
    }

    public function test_admin_can_approve_or_reject_sender_name(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $sender = SenderId::create([
            'user_id' => $user->id,
            'mask' => 'BrandLK',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson("/admin/senders/{$sender->id}/decision", [
            'decision' => 'approved',
            'note' => 'TRCSL registration confirmed',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertEquals('approved', $sender->fresh()->status);
        $this->assertEquals('TRCSL registration confirmed', $sender->fresh()->note);
    }

    public function test_admin_can_update_portal_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/admin/settings', [
            'brand_name' => 'SwiftSMS LK',
            'default_rate' => '1.25',
            'signup_bonus' => '15',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'brand_name' => 'SwiftSMS LK',
                    'default_rate' => '1.25',
                    'signup_bonus' => '15',
                ],
            ]);
    }
}

