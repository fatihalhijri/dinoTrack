<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Testing\TestResponse;

it('mengirim header keamanan di halaman admin, halaman publik, dan webhook', function (Closure $request) {
    /** @var TestResponse $response */
    $response = $request($this);

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');
})->with([
    'dashboard admin' => fn ($test) => $test->actingAs(userWithRole(Role::Admin))->get(route('dashboard')),
    'halaman isolir publik' => fn ($test) => $test->get(route('isolation.show')),
    'webhook Midtrans' => fn ($test) => $test->postJson('/webhooks/payments/midtrans', []),
]);
