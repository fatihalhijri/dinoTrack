<?php

declare(strict_types=1);

use App\Enums\IsolationReason;
use App\Enums\QueueName;
use App\Jobs\ActivateCustomerJob;
use App\Jobs\ApplyCustomerProfileJob;
use App\Jobs\DisableCustomerSecretJob;
use App\Jobs\IsolateCustomerJob;
use App\Jobs\ProcessPaymentNotificationJob;
use App\Jobs\SendInvoiceNotificationJob;
use App\Jobs\SendIsolationNotificationJob;
use App\Jobs\SendPaymentConfirmationJob;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Attributes\Queue as QueueAttribute;
use Illuminate\Support\Facades\Queue;

it('mengirim job ke queue sesuai jenis pekerjaannya', function (Closure $makeJob, QueueName $queue) {
    $job = $makeJob();
    Queue::fake([$job::class]);

    dispatch($job);

    Queue::assertPushedOn($queue->value, $job::class);
})->with([
    'aktivasi pelanggan' => [fn () => new ActivateCustomerJob(new Customer), QueueName::Network],
    'pasang profil paket' => [fn () => new ApplyCustomerProfileJob(new Customer), QueueName::Network],
    'nonaktifkan secret' => [fn () => new DisableCustomerSecretJob(new Customer), QueueName::Network],
    'isolir pelanggan' => [fn () => new IsolateCustomerJob(new Customer, IsolationReason::Overdue, CarbonImmutable::parse('2026-10-07')), QueueName::Network],
    'notifikasi pembayaran Midtrans' => [fn () => new ProcessPaymentNotificationJob(new PaymentNotification), QueueName::Default],
    'pesan tagihan terbit' => [fn () => new SendInvoiceNotificationJob(new Invoice), QueueName::Notifications],
    'pesan isolir' => [fn () => new SendIsolationNotificationJob(new Customer), QueueName::Notifications],
    'pesan pembayaran diterima' => [fn () => new SendPaymentConfirmationJob(new Payment), QueueName::Notifications],
    'kirim pesan WhatsApp' => [fn () => new SendWhatsAppMessage(new MessageLog), QueueName::Notifications],
]);

it('mewajibkan setiap job menetapkan queue agar job baru tidak diam-diam masuk antrean default', function () {
    foreach (glob(app_path('Jobs/*.php')) ?: [] as $file) {
        $class = 'App\\Jobs\\'.basename($file, '.php');

        expect((new ReflectionClass($class))->getAttributes(QueueAttribute::class))
            ->toHaveCount(1, "{$class} wajib memakai #[Queue(QueueName::...)]");
    }
});
