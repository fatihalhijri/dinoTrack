<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Router;
use App\Models\User;
use App\Support\ActivityLogger;

it('mencatat aksi beserta pelaku, subjek alias morph, dan properti', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $log = app(ActivityLogger::class)->log('customer.created', $customer, $user, ['code' => 'PLG-000001']);

    expect($log->fresh())
        ->user_id->toBe($user->id)
        ->subject_type->toBe('customer')
        ->subject_id->toBe($customer->id)
        ->action->toBe('customer.created')
        ->properties->toBe(['code' => 'PLG-000001']);
});

it('mencatat aksi sistem tanpa pelaku dan tanpa properti', function () {
    $log = app(ActivityLogger::class)->log('system.ping');

    expect($log->fresh())
        ->user_id->toBeNull()
        ->subject_type->toBeNull()
        ->properties->toBeNull();
});

it('menyamarkan nilai kolom tersembunyi pada daftar perubahan', function () {
    $router = Router::factory()->create(['name' => 'Lama', 'password' => 'rahasia-lama']);
    $router->fill(['name' => 'Baru', 'password' => 'rahasia-baru']);

    expect(app(ActivityLogger::class)->pendingChanges($router))->toBe([
        'name' => ['Lama', 'Baru'],
        'password' => ['***', '***'],
    ]);
});
