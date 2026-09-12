<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjust_adds_credits_and_logs_transaction(): void
    {
        $user = User::factory()->create(['credits' => 100, 'rate' => 1.10]);

        $newBalance = CreditLedger::adjust($user->id, 50, [
            'type' => 'topup',
            'note' => 'Payment received',
            'actor' => 'admin',
        ]);

        $this->assertEquals(150, $newBalance);
        $this->assertEquals(150, $user->fresh()->credits);

        $tx = Transaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($tx);
        $this->assertEquals('topup', $tx->type);
        $this->assertEquals(50, $tx->units);
        $this->assertEquals(150, $tx->balance_after);
        $this->assertEquals('Payment received', $tx->note);
        $this->assertEquals('admin', $tx->actor);
    }

    public function test_adjust_debits_credits_correctly(): void
    {
        $user = User::factory()->create(['credits' => 100, 'rate' => 1.10]);

        $newBalance = CreditLedger::adjust($user->id, -30, [
            'type' => 'debit',
            'note' => 'Sent 30 SMS',
            'actor' => 'dashboard',
        ]);

        $this->assertEquals(70, $newBalance);
        $this->assertEquals(70, $user->fresh()->credits);

        $tx = Transaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertEquals('debit', $tx->type);
        $this->assertEquals(-30, $tx->units);
        $this->assertEquals(70, $tx->balance_after);
    }

    public function test_adjust_throws_exception_on_insufficient_credits(): void
    {
        $user = User::factory()->create(['credits' => 20]);

        $this->expectException(InsufficientCreditsException::class);

        CreditLedger::adjust($user->id, -25, [
            'type' => 'debit',
            'note' => 'Oversell attempt',
        ]);

        $this->assertEquals(20, $user->fresh()->credits);
    }
}

