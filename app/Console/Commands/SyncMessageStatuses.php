<?php

namespace App\Console\Commands;

use App\Services\Dispatch;
use Illuminate\Console\Command;

/**
 * Text.lk publishes no delivery webhook in the v3 docs, so delivery status
 * is polled instead. Scheduled in routes/console.php.
 */
class SyncMessageStatuses extends Command
{
    protected $signature = 'sms:sync-statuses {--limit=25}';

    protected $description = 'Poll Text.lk for delivery status on messages still in flight';

    public function handle(Dispatch $dispatch): int
    {
        $result = $dispatch->syncStatuses((int) $this->option('limit'));
        $this->info("Checked {$result['checked']}, updated {$result['updated']}.");

        return self::SUCCESS;
    }
}
