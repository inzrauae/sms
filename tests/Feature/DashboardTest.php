<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.textlk.token', 'test-upstream-token');
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login?next=%2Fdashboard');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_overview_endpoint_returns_metrics(): void
    {
        $user = User::factory()->create(['credits' => 500, 'rate' => 1.10]);

        $response = $this->actingAs($user)->getJson('/app/overview');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'credits' => 500,
                    'rate' => 1.10,
                ],
            ]);
    }

    public function test_preview_calculates_segments_and_cost(): void
    {
        $user = User::factory()->create(['credits' => 100, 'rate' => 1.10]);

        $response = $this->actingAs($user)->postJson('/app/preview', [
            'recipients' => '0712345678, 0771234567',
            'message' => 'Hello customer!',
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
                    'affordable' => true,
                ],
            ]);
    }

    public function test_send_fails_if_sender_id_is_not_approved(): void
    {
        $user = User::factory()->create(['credits' => 100]);

        $response = $this->actingAs($user)->postJson('/app/send', [
            'sender_id' => 'UnapprovedMask',
            'recipients' => '0712345678',
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error', 'code' => 'SENDER_NOT_APPROVED']);
    }

    public function test_send_fails_if_insufficient_credits(): void
    {
        $user = User::factory()->create(['credits' => 0]);
        SenderId::create(['user_id' => $user->id, 'mask' => 'MyBrand', 'status' => 'approved']);

        $response = $this->actingAs($user)->postJson('/app/send', [
            'sender_id' => 'MyBrand',
            'recipients' => '0712345678',
            'message' => 'Hello',
        ]);

        $response->assertStatus(402)
            ->assertJson(['status' => 'error', 'code' => 'INSUFFICIENT_CREDITS']);
    }

    public function test_send_succeeds_and_deducts_credits(): void
    {
        $user = User::factory()->create(['credits' => 50, 'rate' => 1.00]);
        SenderId::create(['user_id' => $user->id, 'mask' => 'MyBrand', 'status' => 'approved']);

        Http::fake([
            'https://app.text.lk/api/v3/sms/send' => Http::response([
                'status' => 'success',
                'data' => [
                    'uid' => 'textlk-msg-123',
                    'recipient' => '94712345678',
                    'status' => 'Queued',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/app/send', [
            'sender_id' => 'MyBrand',
            'recipients' => '0712345678',
            'message' => 'Your order is confirmed.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'units' => 1,
                    'credits' => 49,
                ],
            ]);

        $this->assertEquals(49, $user->fresh()->credits);
        $this->assertDatabaseHas('messages', [
            'user_id' => $user->id,
            'recipient' => '94712345678',
            'sender_id' => 'MyBrand',
            'units' => 1,
        ]);
    }

    public function test_send_refunds_credits_if_upstream_fails(): void
    {
        $user = User::factory()->create(['credits' => 50]);
        SenderId::create(['user_id' => $user->id, 'mask' => 'MyBrand', 'status' => 'approved']);

        Http::fake([
            'https://app.text.lk/api/v3/sms/send' => Http::response([
                'status' => 'error',
                'message' => 'Gateway temporary failure',
            ], 500),
        ]);

        $response = $this->actingAs($user)->postJson('/app/send', [
            'sender_id' => 'MyBrand',
            'recipients' => '0712345678',
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error', 'code' => 'UPSTREAM_FAILED']);

        // Balance refunded back to 50
        $this->assertEquals(50, $user->fresh()->credits);
    }

    public function test_user_can_request_and_manage_sender_ids(): void
    {
        $user = User::factory()->create();

        // Request sender ID
        $response = $this->actingAs($user)->postJson('/app/senders', ['mask' => 'MyStore']);
        $response->assertStatus(201);

        $this->assertDatabaseHas('sender_ids', [
            'user_id' => $user->id,
            'mask' => 'MyStore',
            'status' => 'pending',
        ]);

        // List
        $listResponse = $this->actingAs($user)->getJson('/app/senders');
        $listResponse->assertStatus(200)
            ->assertJsonFragment(['mask' => 'MyStore']);

        // Delete
        $sender = SenderId::where('user_id', $user->id)->first();
        $delResponse = $this->actingAs($user)->deleteJson("/app/senders/{$sender->id}");
        $delResponse->assertStatus(200);

        $this->assertDatabaseMissing('sender_ids', ['id' => $sender->id]);
    }

    public function test_user_can_create_and_revoke_api_tokens(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/app/tokens', ['name' => 'Server Token']);
        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['token']]);

        $tokenRow = $user->tokens()->first();
        $this->assertNotNull($tokenRow);
        $this->assertEquals('Server Token', $tokenRow->name);

        // Revoke
        $delResponse = $this->actingAs($user)->deleteJson("/app/tokens/{$tokenRow->id}");
        $delResponse->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenRow->id]);
    }

    public function test_user_can_export_messages_as_csv(): void
    {
        $user = User::factory()->create();
        Message::create([
            'uid' => 'msg-export-1',
            'user_id' => $user->id,
            'recipient' => '94712345678',
            'sender_id' => 'Brand',
            'body' => 'Export test',
            'sms_type' => 'plain',
            'segments' => 1,
            'units' => 1,
            'cost' => 1.10,
            'status' => 'Delivered',
            'source' => 'dashboard',
        ]);

        $response = $this->actingAs($user)->get('/app/messages/export');

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=utf-8', $response->headers->get('content-type'));
        $this->assertStringContainsString('Export test', $response->getContent());
        $this->assertStringContainsString('94712345678', $response->getContent());
    }

    public function test_user_can_request_credit_topup(): void
    {
        $user = User::factory()->create(['credits' => 10, 'rate' => 1.10]);

        $response = $this->actingAs($user)->postJson('/app/topup-request', [
            'units' => 500,
            'note' => 'Bank transfer done',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'request',
            'units' => 500,
            'amount' => 550.00,
        ]);
    }
}

