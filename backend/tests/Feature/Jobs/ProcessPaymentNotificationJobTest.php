<?php

declare(strict_types=1);

use App\Jobs\ProcessPaymentNotificationJob;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentNotification;

it('tidak memproses ulang notifikasi yang sudah diproses', function () {
    $notification = PaymentNotification::factory()->create([
        'payload' => signedMidtransPayload(),
        'processed_at' => now()->subMinute(),
    ]);

    ProcessPaymentNotificationJob::dispatchSync($notification);

    expect(Payment::query()->count())->toBe(0);
});

it('langsung gagal dan mencatat log jika payload tidak bisa dibaca', function () {
    $notification = PaymentNotification::factory()->create([
        'order_id' => 'INV20261000001-1',
        'payload' => signedMidtransPayload(['gross_amount' => '150000.50']),
    ]);

    ProcessPaymentNotificationJob::dispatchSync($notification);

    expect($notification->fresh()->processed_at)->toBeNull();
    expect(ActivityLog::query()->where('action', 'payment.notification_failed')->sole())
        ->subject_id->toBe($notification->id)
        ->properties->toMatchArray(['order_id' => 'INV20261000001-1']);
});
