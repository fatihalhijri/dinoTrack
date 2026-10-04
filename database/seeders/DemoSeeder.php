<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh untuk development. Dijalankan pada database kosong (migrate:fresh --seed).
 * Belum ada invoice karena tagihan baru dibuat oleh generator di Tahap 04.
 */
class DemoSeeder extends Seeder
{
    private const int ACTIVE_CUSTOMERS = 22;

    private const int PENDING_CUSTOMERS = 5;

    private const int TERMINATED_CUSTOMERS = 3;

    public function run(): void
    {
        $this->seedUsers();

        $packages = collect([
            ['name' => 'Home 10 Mbps', 'speed_label' => '10 Mbps', 'price' => 100_000, 'mikrotik_profile' => 'HOME-10M'],
            ['name' => 'Home 20 Mbps', 'speed_label' => '20 Mbps', 'price' => 150_000, 'mikrotik_profile' => 'HOME-20M'],
            ['name' => 'Home 30 Mbps', 'speed_label' => '30 Mbps', 'price' => 200_000, 'mikrotik_profile' => 'HOME-30M'],
            ['name' => 'Home 50 Mbps', 'speed_label' => '50 Mbps', 'price' => 300_000, 'mikrotik_profile' => 'HOME-50M'],
        ])->map(fn (array $attributes): Package => Package::factory()->create($attributes));

        $router = Router::factory()->create([
            'name' => 'Router Utama',
            'host' => '192.168.88.1',
            'username' => 'billing',
            'password' => 'password',
        ]);

        $sequence = 0;
        $groups = [
            [self::ACTIVE_CUSTOMERS, fn () => Customer::factory()->active()],
            [self::PENDING_CUSTOMERS, fn () => Customer::factory()->pending()],
            [self::TERMINATED_CUSTOMERS, fn () => Customer::factory()->terminated()],
        ];

        foreach ($groups as [$count, $factory]) {
            for ($i = 0; $i < $count; $i++) {
                $installedAt = fake()->dateTimeBetween('-12 months', '-1 month');

                $factory()
                    ->for($router)
                    ->state(fn (array $attributes) => [
                        'installed_at' => $attributes['installed_at'] === null ? null : $installedAt->format('Y-m-d'),
                    ])
                    ->withSubscription($packages->random())
                    ->create(['code' => sprintf('PLG-%06d', ++$sequence)]);
            }
        }

        // Generator kode pelanggan (Tahap 03) melanjutkan dari nomor terakhir ini.
        DB::table('sequences')->updateOrInsert(
            ['key' => 'customer'],
            ['last_value' => $sequence, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function seedUsers(): void
    {
        foreach (RoleSeeder::ROLES as $role) {
            User::factory()
                ->create([
                    'name' => ucfirst($role).' Demo',
                    'email' => "{$role}@example.com",
                ])
                ->assignRole($role);
        }
    }
}
