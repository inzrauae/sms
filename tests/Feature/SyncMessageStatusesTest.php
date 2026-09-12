<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMessageStatusesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.textlk.token', 'test-upstream-token');
    }

    public function test_artisan_sync_updates_message_statuses(): void
    {
        $user = User::factory()->create();

        $message = Message::create([
            'uid' => 'sync-test-msg-1',
            'provider_uid' => 'textlk-upstream-111',
            'user_id' => $user->id,
            'recipient' => '94712345678',
            'sender_id' => 'Brand',
            'body' => 'Status sync test',
            'sms_type' => 'plain',
            'segments' => 1,
            'units' => 1,
            'cost' => 1.10,
            'status' => 'Queued',
            'source' => 'dashboard',
        ]);

        Http::fake([
            'https://app.text.lk/api/v3/sms/textlk-upstream-111' => Http::response([
                'status' => 'success',
                'data' => [
                    'uid' => 'textlk-upstream-111',
                    'status' => 'Delivered',
                ],
            ], 200),
        ]);

        $this->artisan('sms:sync-statuses')
            ->expectsOutput('Checked 1, updated 1.')
            ->assertSuccessful();

        $this->assertEquals('Delivered', $message->fresh()->status);
    }
}

