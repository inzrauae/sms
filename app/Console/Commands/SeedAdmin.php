<?php

namespace App\Console\Commands;

use App\Models\SenderId;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\Settings;
use App\Support\Uid;
use Illuminate\Console\Command;

/**
 * First-run setup. Creates the admin account, and optionally a demo
 * customer with an approved sender name so you can click around before
 * wiring up a real Text.lk token.
 *
 *   php artisan app:seed-admin admin@yourcompany.lk "Strong passphrase here"
 *   php artisan app:seed-admin admin@yourcompany.lk "Strong passphrase here" --demo
 */
class SeedAdmin extends Command
{
    protected $signature = 'app:seed-admin {email?} {password?} {--demo}';

    protected $description = 'Create (or promote) the portal admin account';

    public function handle(): int
    {
        $email = strtolower((string) ($this->argument('email') ?? $this->ask('Admin email')));
        $password = (string) ($this->argument('password') ?? $this->secret('Admin password (8+ characters)'));

        if (!str_contains($email, '@')) {
            $this->error('That does not look like an email address.');

            return self::FAILURE;
        }
        if (strlen($password) < 8) {
            $this->error('Use a password of at least 8 characters.');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $existing->update(['role' => 'admin', 'password' => $password]);
            $this->info("Updated {$email} — now an admin with the new password.");
        } else {
            $user = User::create([
                'uid' => Uid::make(),
                'name' => 'Portal admin',
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'rate' => (float) Settings::get('default_rate', '1.10'),
                'credits' => 0,
            ]);
            $this->info("Created admin {$email} (id {$user->id}).");
        }

        if ($this->option('demo')) {
            $demoEmail = 'demo@example.lk';
            $demo = User::where('email', $demoEmail)->first();

            if (!$demo) {
                $demo = User::create([
                    'uid' => Uid::make(),
                    'name' => 'Nimal Perera',
                    'company' => 'Ceylon Spice Traders',
                    'email' => $demoEmail,
                    'phone' => '94712345678',
                    'password' => 'demo12345',
                    'rate' => 0.88,
                ]);
                CreditLedger::adjust($demo->id, 2500, ['type' => 'topup', 'note' => 'Demo credits', 'actor' => 'seed']);
            }

            SenderId::firstOrCreate(
                ['user_id' => $demo->id, 'mask' => 'SpiceLK'],
                ['status' => 'approved', 'note' => 'Approved for the demo']
            );

            $this->info("Demo customer ready — {$demoEmail} / demo12345 with 2500 credits.");
        }

        $this->newLine();
        $this->info('Start the server with: php artisan serve');

        return self::SUCCESS;
    }
}
