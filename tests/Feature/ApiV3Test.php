<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.textlk.token', 'test-upstream-token');
    }

    public function test_api_requires_sanctum_token(): void
    {
        $response = $this->getJson('/api/v3/balance');
        $response->assertStatus(401);
    }

    public function test_api_balance_returns_tenant_credits(): void
    {
        $user = User::factory()->create(['credits' => 750, 'rate' => 0.95]);
        $token = $user->createToken('test-api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v3/balance');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'remaining_sms_unit' => 750,
                    'rate' => 0.95,
                    'currency' => 'LKR',
                ],
            ]);
    }

    public function test_api_me_returns_profile(): void
    {
        $user = User::factory()->create(['name' => 'John Doe', 'credits' => 100]);
        $token = $user->createToken('test-api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v3/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name' => 'John Doe',
                    'remaining_sms_unit' => 100,
                ],
            ]);
    }

    public function test_api_sms_estimate_calculates_segments_and_cost(): void
    {
        $user = User::factory()->create(['rate' => 1.10]);
        $token = $user->createToken('test-api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v3/sms/estimate', [
                'recipient' => '0712345678, 0771234567',
                'message' => 'Your OTP is 9876.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'encoding' => 'gsm',
                    'segments' => 1,
                    'recipients' => 2,
                    'units' => 2,
                    'cost' => 2.20,
                ],
            ]);
    }

    public function test_api_sms_send_requires_approved_sender(): void
    {
        $user = User::factory()->create(['credits' => 50]);
        $token = $user->createToken('test-api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v3/sms/send', [
                'recipient' => '0712345678',
                'sender_id' => 'NonExistentSender',
                'message' => 'Hello',
            ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_api_sms_send_fails_when_credits_exhausted(): void
    {
        $user = User::factory()->create(['credits' => 0]);
        $token = $user->createToken('test-api')->plainTextToken;
        SenderId::create(['user_id' => $user->id, 'mask' => 'MyBrand', 'status' => 'approved']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v3/sms/send', [
                'recipient' => '0712345678',
                'sender_id' => 'MyBrand',
                'message' => 'Hello',
            ]);

        $response->assertStatus(402)
            ->assertJson(['status' => 'error']);
    }

    public function test_api_sms_send_succeeds(): void
    {
        $user = User::factory()->create(['credits' => 100]);
        $token = $user->createToken('test-api')->plainTextToken;
        SenderId::create(['user_id' => $user->id, 'mask' => 'MyBrand', 'status' => 'approved']);

        Http::fake([
            'https://app.text.lk/api/v3/sms/send' => Http::response([
                'status' => 'success',
                'data' => [
                    'uid' => 'upstream-api-sms-01',
                    'recipient' => '94712345678',
                    'status' => 'Queued',
                ],
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v3/sms/send', [
                'recipient' => '0712345678',
                'sender_id' => 'MyBrand',
                'message' => 'Hello through API',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'to' => '94712345678',
                    'from' => 'MyBrand',
                    'message' => 'Hello through API',
                    'sms_count' => 1,
                    'cost' => 1,
                ],
            ]);

        $this->assertEquals(99, $user->fresh()->credits);
    }

    public function test_api_sms_show_returns_message(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-api')->plainTextToken;

        $msg = Message::create([
            'uid' => 'local-msg-uid-99',
            'user_id' => $user->id,
            'recipient' => '94712345678',
            'sender_id' => 'MyBrand',
            'body' => 'API show test',
            'sms_type' => 'plain',
            'segments' => 1,
            'units' => 1,
            'cost' => 1.10,
            'status' => 'Delivered',
            'source' => 'api',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v3/sms/{$msg->uid}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'uid' => 'local-msg-uid-99',
                    'to' => '94712345678',
                    'from' => 'MyBrand',
                    'message' => 'API show test',
                    'status' => 'Delivered',
                ],
            ]);
    }
}

