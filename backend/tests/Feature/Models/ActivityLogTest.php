<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

it('menyimpan subject dengan alias morph, bukan nama kelas', function () {
    $customer = Customer::factory()->create();

    $log = ActivityLog::factory()->for($customer, 'subject')->create(['action' => 'customer.isolated']);

    expect(DB::table('activity_logs')->where('id', $log->id)->value('subject_type'))->toBe('customer')
        ->and($log->fresh()->subject->is($customer))->toBeTrue()
        ->and($customer->activityLogs()->count())->toBe(1);
});

it('mengizinkan log aksi sistem tanpa user dan tanpa subject', function () {
    $log = ActivityLog::factory()->create(['action' => 'settings.updated', 'properties' => ['key' => 'billing.grace_days']]);

    expect($log->fresh()->user)->toBeNull()
        ->and($log->fresh()->subject)->toBeNull()
        ->and($log->fresh()->properties)->toBe(['key' => 'billing.grace_days']);
});
