<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Package;
use App\Models\Router;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Spatie\Permission\Models\Role;

it('mengisi data demo lengkap untuk development', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::role('admin')->pluck('email')->all())->toBe(['admin@example.com'])
        ->and(User::role('kasir')->pluck('email')->all())->toBe(['kasir@example.com'])
        ->and(User::role('teknisi')->pluck('email')->all())->toBe(['teknisi@example.com'])
        ->and(Package::count())->toBe(4)
        ->and(Router::count())->toBe(1)
        ->and(Customer::count())->toBe(30)
        ->and(MessageTemplate::count())->toBe(count(MessageTemplateKey::cases()))
        ->and(Setting::where('key', 'billing.grace_days')->value('value'))->toBe(3);
});

it('memberi subscription aktif kepada setiap pelanggan yang belum berhenti', function () {
    $this->seed(DatabaseSeeder::class);

    $withoutSubscription = Customer::query()
        ->where('status', '!=', CustomerStatus::Terminated)
        ->whereDoesntHave('activeSubscription')
        ->count();

    expect($withoutSubscription)->toBe(0)
        ->and(Customer::where('status', CustomerStatus::Terminated)->has('activeSubscription')->count())->toBe(0);
});

it('memberi permission sesuai role kepada user demo', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::permission('payments.record')->pluck('email')->sort()->values()->all())
        ->toBe(['admin@example.com', 'kasir@example.com']);
});

it('aman menjalankan ulang seeder esensial tanpa data ganda', function () {
    $this->seed([RolePermissionSeeder::class, SettingSeeder::class, MessageTemplateSeeder::class]);

    $this->seed([RolePermissionSeeder::class, SettingSeeder::class, MessageTemplateSeeder::class]);

    expect(Role::count())->toBe(3)
        ->and(Setting::count())->toBe(count(SettingSeeder::DEFAULTS))
        ->and(MessageTemplate::count())->toBe(count(MessageTemplateKey::cases()));
});

it('tidak menimpa template dan pengaturan yang sudah diubah admin', function () {
    $this->seed([SettingSeeder::class, MessageTemplateSeeder::class]);
    Setting::where('key', 'billing.grace_days')->sole()->update(['value' => 0]);
    MessageTemplate::where('key', MessageTemplateKey::InvoiceIssued)->sole()->update(['body' => 'Template kustom']);

    $this->seed([SettingSeeder::class, MessageTemplateSeeder::class]);

    expect(Setting::where('key', 'billing.grace_days')->value('value'))->toBe(0)
        ->and(MessageTemplate::where('key', MessageTemplateKey::InvoiceIssued)->value('body'))->toBe('Template kustom');
});
