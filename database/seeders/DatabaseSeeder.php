<?php

namespace Database\Seeders;

use App\Models\SenderId;
use App\Models\Setting;
use App\Models\User;
use App\Services\CreditLedger;
use App\Support\Uid;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Portal settings
        $defaults = [
            'brand_name' => config('portal.brand_name', 'Lankalink SMS'),
            'default_rate' => config('portal.default_rate', '1.10'),
            'signup_bonus' => config('portal.signup_bonus', '10'),
            'support_email' => config('portal.support_email', 'support@example.lk'),
            'currency' => config('portal.currency', 'LKR'),
            'sender_id_fee' => config('portal.sender_id_fee', '1000'),
        ];
        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        // 2. Admin account
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.lk'],
            [
                'uid' => Uid::make(),
                'name' => 'Portal Admin',
                'password' => 'AdminPass123!',
                'role' => 'admin',
                'status' => 'active',
                'rate' => 1.10,
                'credits' => 0,
            ]
        );

        // 3. Demo customer
        $demo = User::firstOrCreate(
            ['email' => 'demo@example.lk'],
            [
                'uid' => Uid::make(),
                'name' => 'Nimal Perera',
                'company' => 'Ceylon Spice Traders',
                'phone' => '94712345678',
                'password' => 'demo12345',
                'role' => 'user',
                'status' => 'active',
                'rate' => 0.88,
                'credits' => 0,
            ]
        );

        if ($demo->credits < 2500) {
            CreditLedger::adjust($demo->id, 2500 - $demo->credits, [
                'type' => 'topup',
                'note' => 'Demo credits',
                'actor' => 'seed',
            ]);
        }

        SenderId::firstOrCreate(
            ['user_id' => $demo->id, 'mask' => 'SpiceLK'],
            ['status' => 'approved', 'note' => 'Approved for the demo']
        );
    }
}
