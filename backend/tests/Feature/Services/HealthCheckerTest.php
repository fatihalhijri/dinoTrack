<?php

declare(strict_types=1);

use App\Data\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Enums\MessageStatus;
use App\Enums\QueueName;
use App\Exceptions\RouterUnreachableException;
use App\Models\MessageLog;
use App\Models\PaymentNotification;
use App\Models\Router;
use App\Services\Health\HealthChecker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Hasil satu pemeriksaan dari HealthChecker::run().
 */
function healthCheck(string $name): HealthCheckResult
{
    $result = collect(app(HealthChecker::class)->run())->firstWhere('name', $name);

    expect($result)->toBeInstanceOf(HealthCheckResult::class, "Pemeriksaan {$name} tidak ada.");

    return $result;
}

/**
 * Job mentah di queue database yang masuk $minutesAgo menit lalu.
 */
function pendingDatabaseJob(QueueName $queue, int $minutesAgo): void
{
    config(['queue.default' => 'database']);

    test()->travel(-$minutesAgo)->minutes();
    Queue::connection('database')->pushRaw('{"displayName":"Simulasi"}', $queue->value);
    test()->travelBack();
}

it('melaporkan semua sehat di development tanpa router, Redis, dan worker', function () {
    $statuses = collect(app(HealthChecker::class)->run())
        ->mapWithKeys(fn (HealthCheckResult $result): array => [$result->name => $result->status])
        ->all();

    expect($statuses)->toBe([
        'Database' => HealthStatus::Ok,
        'Redis' => HealthStatus::Skipped,
        'Antrean default' => HealthStatus::Skipped,
        'Antrean network' => HealthStatus::Skipped,
        'Antrean notifications' => HealthStatus::Skipped,
        'Notifikasi pembayaran' => HealthStatus::Ok,
        'Job gagal' => HealthStatus::Ok,
        'Pesan WhatsApp' => HealthStatus::Ok,
        'Router' => HealthStatus::Skipped,
        'Konfigurasi production' => HealthStatus::Skipped,
    ]);
});

it('memeriksa Redis bila cache memakai redis', function () {
    config(['cache.default' => 'redis']);
    Redis::shouldReceive('connection->ping')->once()->andReturn(true);

    expect(healthCheck('Redis'))->status->toBe(HealthStatus::Ok);
});

it('gagal bila Redis yang dipakai tidak terjangkau', function () {
    config(['queue.default' => 'redis']);
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('Connection refused'));

    expect(healthCheck('Redis'))
        ->status->toBe(HealthStatus::Fail)
        ->message->toBe('Connection refused');
});

it('gagal bila job pending tertahan lebih dari 10 menit karena worker berhenti', function (QueueName $queue) {
    pendingDatabaseJob($queue, minutesAgo: 11);

    expect(healthCheck("Antrean {$queue->value}"))
        ->status->toBe(HealthStatus::Fail)
        ->message->toBe('1 job menunggu, tertua 11 menit. Pastikan worker Supervisor berjalan.');
})->with([QueueName::Default, QueueName::Network]);

it('sehat bila job pending belum lewat 10 menit', function () {
    pendingDatabaseJob(QueueName::Network, minutesAgo: 9);

    expect(healthCheck('Antrean network'))
        ->status->toBe(HealthStatus::Ok)
        ->message->toBe('1 job menunggu.');
});

it('tidak menilai umur antrean notifications karena job yang ditahan rate limit tampak tua', function () {
    pendingDatabaseJob(QueueName::Notifications, minutesAgo: 180);

    expect(healthCheck('Antrean notifications'))->status->toBe(HealthStatus::Ok);
});

it('gagal bila notifikasi pembayaran valid belum diproses lebih dari 15 menit', function () {
    $this->freezeTime();
    PaymentNotification::factory()->create(['created_at' => now()->subMinutes(16)]);

    expect(healthCheck('Notifikasi pembayaran'))
        ->status->toBe(HealthStatus::Fail)
        ->message->toBe('1 notifikasi valid belum diproses lebih dari 15 menit; pelanggan mungkin sudah membayar tetapi tagihan belum lunas.');
});

it('mengabaikan notifikasi yang sudah diproses, bersignature salah, baru masuk, atau lebih dari 24 jam', function () {
    $this->freezeTime();
    PaymentNotification::factory()->create(['created_at' => now()->subHour(), 'processed_at' => now()->subMinutes(59)]);
    PaymentNotification::factory()->create(['created_at' => now()->subHour(), 'signature_valid' => false]);
    PaymentNotification::factory()->create(['created_at' => now()->subMinutes(14)]);
    PaymentNotification::factory()->create(['created_at' => now()->subHours(25)]);

    expect(healthCheck('Notifikasi pembayaran'))->status->toBe(HealthStatus::Ok);
});

it('memberi peringatan bila ada job gagal', function () {
    app('queue.failer')->log('database', 'network', '{"uuid":"9c1f0d2e-0000-4000-8000-000000000001","displayName":"Simulasi"}', new RuntimeException('Router mati'));

    expect(healthCheck('Job gagal'))
        ->status->toBe(HealthStatus::Warning)
        ->message->toBe('1 job gagal. Tinjau dengan `php artisan queue:failed`.');
});

it('memberi peringatan bila pesan WhatsApp tertahan lebih dari 2 jam atau gagal dalam 24 jam', function () {
    $this->freezeTime();
    MessageLog::factory()->create(['status' => MessageStatus::Queued, 'sent_at' => null, 'created_at' => now()->subHours(3)]);
    MessageLog::factory()->failed()->create(['updated_at' => now()->subHour()]);

    expect(healthCheck('Pesan WhatsApp'))
        ->status->toBe(HealthStatus::Warning)
        ->message->toBe('1 pesan tertahan lebih dari 2 jam; 1 pesan gagal dalam 24 jam terakhir.');
});

it('tidak memperingatkan pesan yang baru antre atau gagal lebih dari 24 jam lalu', function () {
    $this->freezeTime();
    MessageLog::factory()->create(['status' => MessageStatus::Queued, 'sent_at' => null, 'created_at' => now()->subMinutes(90)]);
    MessageLog::factory()->failed()->create(['updated_at' => now()->subHours(25)]);

    expect(healthCheck('Pesan WhatsApp'))->status->toBe(HealthStatus::Ok);
});

it('memeriksa setiap router aktif dan hanya memberi peringatan bila tidak terjangkau', function () {
    $network = fakeNetwork();
    $network->connectable = false;
    $router = Router::factory()->create(['name' => 'Pusat']);
    Router::factory()->inactive()->create();

    expect(healthCheck('Router: Pusat'))
        ->status->toBe(HealthStatus::Warning)
        ->message->toBe('Tidak bisa dihubungi.');
    expect($network->calls('testConnection'))->toHaveCount(1)
        ->and($network->calls('testConnection')[0]['args'][0]->is($router))->toBeTrue();
});

it('menampilkan galat router tanpa menghentikan pemeriksaan lain', function () {
    fakeNetwork()->failWith(new RouterUnreachableException('Router Pusat tidak bisa dijangkau: timeout'));
    Router::factory()->create(['name' => 'Pusat']);

    expect(healthCheck('Router: Pusat'))
        ->status->toBe(HealthStatus::Warning)
        ->message->toBe('Router Pusat tidak bisa dijangkau: timeout');
    expect(healthCheck('Database'))->status->toBe(HealthStatus::Ok);
});

it('gagal bila konfigurasi production belum sesuai checklist', function () {
    $this->app['env'] = 'production';
    config([
        'app.debug' => true,
        'app.url' => 'http://billing.example.com',
        'session.secure' => null,
        'services.midtrans.is_production' => false,
        'services.midtrans.server_key' => '',
        'services.whatsapp.driver' => 'log',
    ]);

    expect(healthCheck('Konfigurasi production'))
        ->status->toBe(HealthStatus::Fail)
        ->message->toBe('APP_DEBUG masih aktif; APP_URL bukan https; SESSION_SECURE_COOKIE belum aktif; QUEUE_CONNECTION masih sync; MIDTRANS_IS_PRODUCTION belum aktif; MIDTRANS_SERVER_KEY kosong; WHATSAPP_DRIVER masih log.');
});

it('menerima konfigurasi production yang sesuai checklist', function () {
    $this->app['env'] = 'production';
    config([
        'app.debug' => false,
        'app.url' => 'https://billing.example.com',
        'session.secure' => true,
        'queue.default' => 'database',
        'services.midtrans.is_production' => true,
        'services.midtrans.server_key' => 'server-key-uji',
        'services.whatsapp.driver' => 'fonnte',
        'services.fonnte.token' => 'token-uji',
    ]);

    expect(healthCheck('Konfigurasi production'))->status->toBe(HealthStatus::Ok);
});
