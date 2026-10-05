<?php

declare(strict_types=1);

use App\Enums\Role;
use Inertia\Testing\AssertableInertia as Assert;

it('membagikan permission kasir ke halaman Inertia', function () {
    $kasir = userWithRole(Role::Kasir);

    $response = $this->actingAs($kasir)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->where('auth.permissions', [
        'customers.activate',
        'customers.view',
        'invoices.resend',
        'invoices.view',
        'packages.view',
        'payments.record',
        'payments.view',
    ]));
});

it('membagikan semua permission untuk admin', function () {
    $admin = userWithRole(Role::Admin);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('auth.permissions', 19)
        ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('users.manage')));
});

it('tidak membagikan permission untuk tamu', function () {
    $response = $this->get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page->where('auth.permissions', []));
});
