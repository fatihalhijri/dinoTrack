<?php

declare(strict_types=1);

use App\Models\Router;
use Illuminate\Support\Facades\DB;

it('menyimpan password router terenkripsi di database', function () {
    $router = Router::factory()->create(['password' => 'rahasia-router']);

    $stored = DB::table('routers')->where('id', $router->id)->value('password');

    expect($stored)->not->toBe('rahasia-router')
        ->and(decrypt($stored, unserialize: false))->toBe('rahasia-router')
        ->and($router->fresh()->password)->toBe('rahasia-router');
});

it('tidak menyertakan password saat router diserialisasi', function () {
    $router = Router::factory()->create();

    expect($router->toArray())->not->toHaveKey('password');
});
