<?php

declare(strict_types=1);

use App\Models\Setting;

it('mempertahankan tipe nilai pengaturan', function (mixed $value) {
    $setting = Setting::factory()->create(['value' => $value]);

    expect($setting->fresh()->value)->toBe($value);
})->with([
    'integer' => [3],
    'nol' => [0],
    'boolean' => [false],
    'string' => ['Dino Net'],
]);
