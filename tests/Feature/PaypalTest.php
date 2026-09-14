<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaypalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.paypal.mode', 'sandbox');
        Config::set('services.paypal.client_id', 'test-client-id');
        Config::set('services.paypal.client_secret', 'test-client-secret');
    }

    private function fakeOauth(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-access-token',
                'expires_in' => 32400,
            ], 200),
        ]);
    }

    public function test_create_order_requires_at_least_100_units(): void
    {
        $user = User::factory()->create(['rate' => 0.99]);

        $response = $this->actingAs($user)->postJson('/app/paypal/orders', ['units' => 50]);

        $response->assertStatus(422)->assertJson(['status' => 'error']);
    }

    public function test_create_order_returns_order_id_and_usd_amount(): void
    {
        $user = User::factory()->create(['rate' => 0.99]);
        $this->fakeOauth();
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-access-token', 'expires_in' => 32400,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER123',
                'status' => 'CREATED',
            ], 201),
        ]);

        $response = $this->actingAs($user)->postJson('/app/paypal/orders', ['units' => 1000]);

        // 1000 units * Rs 0.99 = Rs 990, at the default Rs 300/USD rate = $3.30
        $response->assertStatus(200)->assertJson([
            'status' => 'success',
            'data' => [
                'order_id' => 'ORDER123',
                'units' => 1000,
                'lkr_amount' => 990,
                'usd_amount' => '3.30',
            ],
        ]);
    }

    public function test_capture_adds_credits_on_completed_payment(): void
    {
        $user = User::factory()->create(['rate' => 0.99, 'credits' => 10]);
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-access-token', 'expires_in' => 32400,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER123/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'custom_id' => $user->id . ':1000',
                    'payments' => ['captures' => [['amount' => ['value' => '3.30', 'currency_code' => 'USD']]]],
                ]],
            ], 201),
        ]);

        $response = $this->actingAs($user)->postJson('/app/paypal/orders/ORDER123/capture');

        $response->assertStatus(200)->assertJson(['status' => 'success', 'data' => ['credits' => 1010]]);
        $this->assertEquals(1010, $user->fresh()->credits);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'topup',
            'units' => 1000,
            'ref' => 'ORDER123',
            'actor' => 'paypal',
        ]);
    }

    public function test_capture_rejects_a_payment_that_belongs_to_another_account(): void
    {
        $user = User::factory()->create(['rate' => 0.99, 'credits' => 10]);
        $otherUserId = $user->id + 999;

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-access-token', 'expires_in' => 32400,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER123/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['custom_id' => $otherUserId . ':1000']],
            ], 201),
        ]);

        $response = $this->actingAs($user)->postJson('/app/paypal/orders/ORDER123/capture');

        $response->assertStatus(403);
        $this->assertEquals(10, $user->fresh()->credits);
    }

    public function test_capture_is_idempotent_on_replay(): void
    {
        $user = User::factory()->create(['rate' => 0.99, 'credits' => 10]);
        Transaction::create([
            'user_id' => $user->id, 'type' => 'topup', 'units' => 1000,
            'balance_after' => 1010, 'amount' => 3.30, 'ref' => 'ORDER123', 'actor' => 'paypal',
        ]);
        $user->update(['credits' => 1010]);

        $response = $this->actingAs($user)->postJson('/app/paypal/orders/ORDER123/capture');

        $response->assertStatus(200)->assertJson(['status' => 'success', 'data' => ['credits' => 1010]]);
        $this->assertEquals(1, Transaction::where('ref', 'ORDER123')->count());
    }

    public function test_create_order_fails_cleanly_when_paypal_is_not_configured(): void
    {
        Config::set('services.paypal.client_id', null);
        Config::set('services.paypal.client_secret', null);
        $user = User::factory()->create(['rate' => 0.99]);

        $response = $this->actingAs($user)->postJson('/app/paypal/orders', ['units' => 1000]);

        $response->assertStatus(502)->assertJsonFragment(['status' => 'error']);
    }
}
