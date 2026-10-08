<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;

it('membalas up di /up saat database dan Redis bisa dipakai', function () {
    config(['cache.default' => 'redis']);
    Redis::shouldReceive('connection->ping')->once()->andReturn(true);

    $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'up']);
});

it('membalas 500 di /up saat Redis yang dipakai tidak terjangkau', function () {
    config(['app.debug' => false, 'cache.default' => 'redis']);
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('Connection refused'));

    $this->getJson('/up')->assertInternalServerError()->assertExactJson(['status' => 'down']);
});
