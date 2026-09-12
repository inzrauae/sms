<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditLedger
{
    /**
     * Move credits and write the ledger entry in one transaction.
     *
     * `units` is positive to add, negative to take away. Throws when the
     * user cannot cover a debit, which is what stops an oversell. The row
     * lock matters here because PHP-FPM serves requests concurrently, unlike
     * the single-threaded Node reference this was ported from.
     */
    public static function adjust(int $userId, int $units, array $meta = []): int
    {
        return DB::transaction(function () use ($userId, $units, $meta) {
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();

            $next = $user->credits + $units;
            if ($next < 0) {
                throw new InsufficientCreditsException($user->credits, abs($units));
            }

            $user->update(['credits' => $next]);

            Transaction::create([
                'user_id' => $userId,
                'type' => $meta['type'] ?? ($units >= 0 ? 'topup' : 'debit'),
                'units' => $units,
                'balance_after' => $next,
                'amount' => $meta['amount'] ?? abs($units) * ($user->rate ?? 0),
                'note' => $meta['note'] ?? null,
                'ref' => $meta['ref'] ?? null,
                'actor' => $meta['actor'] ?? null,
            ]);

            return $next;
        });
    }
}
