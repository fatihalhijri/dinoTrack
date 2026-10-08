<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Contracts\NetworkController;
use App\Data\HealthCheckResult;
use App\Enums\MessageStatus;
use App\Enums\QueueName;
use App\Models\MessageLog;
use App\Models\PaymentNotification;
use App\Models\Router;
use Closure;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pemeriksaan kesehatan untuk `billing:health` dan `/up` (docs/10-deploy.md).
 *
 * Hanya membaca: tidak mengubah `routers.last_connected_at` dan tidak menulis activity log,
 * karena dijalankan terjadwal setiap 15 menit.
 */
final class HealthChecker
{
    /** Job pending di queue `default`/`network` setua ini berarti worker tidak berjalan. */
    public const int STUCK_QUEUE_MINUTES = 10;

    /** Notifikasi Midtrans valid yang belum diproses selama ini: pelanggan sudah bayar, invoice belum lunas. */
    public const int UNPROCESSED_NOTIFICATION_MINUTES = 15;

    /** Pada 1 pesan / 5 detik, 2 jam cukup untuk ±1.400 pesan; lebih lama berarti antrean WA macet. */
    public const int QUEUED_MESSAGE_HOURS = 2;

    /** Rentang kegagalan terbaru yang dilaporkan (notifikasi pembayaran, pesan WA gagal). */
    public const int RECENT_HOURS = 24;

    public function __construct(
        private readonly NetworkController $network,
        private readonly QueueFactory $queues,
        private readonly FailedJobProviderInterface $failedJobs,
    ) {}

    /**
     * @return list<HealthCheckResult>
     */
    public function run(): array
    {
        return [
            $this->guard('Database', $this->checkDatabase(...)),
            $this->guard('Redis', $this->checkRedis(...)),
            ...array_map(
                fn (QueueName $queue): HealthCheckResult => $this->guard("Antrean {$queue->value}", fn (string $name): HealthCheckResult => $this->checkQueue($name, $queue)),
                QueueName::cases(),
            ),
            $this->guard('Notifikasi pembayaran', $this->checkPaymentNotifications(...)),
            $this->guard('Job gagal', $this->checkFailedJobs(...)),
            $this->guard('Pesan WhatsApp', $this->checkWhatsappMessages(...)),
            ...$this->checkRouters(),
            $this->guard('Konfigurasi production', $this->checkProductionConfig(...)),
        ];
    }

    /**
     * Pemeriksaan cepat untuk `/up` (tanpa router dan query bisnis).
     *
     * @throws Throwable database atau Redis tidak bisa dipakai
     */
    public function ensureServicesReachable(): void
    {
        DB::select('select 1');

        if ($this->usesRedis()) {
            Redis::connection()->ping();
        }
    }

    /**
     * Satu pemeriksaan yang galat (misalnya database mati) tidak menghentikan pemeriksaan lain.
     *
     * @param  Closure(string): HealthCheckResult  $check
     */
    private function guard(string $name, Closure $check): HealthCheckResult
    {
        try {
            return $check($name);
        } catch (Throwable $exception) {
            return HealthCheckResult::fail($name, Str::limit($exception->getMessage(), 200));
        }
    }

    private function checkDatabase(string $name): HealthCheckResult
    {
        DB::select('select 1');

        return HealthCheckResult::ok($name, 'Terhubung.');
    }

    private function checkRedis(string $name): HealthCheckResult
    {
        if (! $this->usesRedis()) {
            return HealthCheckResult::skipped($name, 'Queue, cache, dan session tidak memakai redis.');
        }

        Redis::connection()->ping();

        return HealthCheckResult::ok($name, 'Terhubung.');
    }

    /**
     * Antrean `notifications` tidak dinilai dari umur job: job yang ditahan rate limit dirilis
     * ulang dengan waktu pembuatan aslinya, sehingga antrean sehat pun tampak tua. Keterlambatan
     * pesan dinilai dari `message_logs` di checkWhatsappMessages().
     */
    private function checkQueue(string $name, QueueName $queue): HealthCheckResult
    {
        $connection = $this->queues->connection();

        if ($connection instanceof SyncQueue) {
            return HealthCheckResult::skipped($name, 'Queue sync: job dijalankan langsung.');
        }

        $pending = $connection->pendingSize($queue->value);

        if ($queue === QueueName::Notifications) {
            return HealthCheckResult::ok($name, "{$pending} job menunggu.");
        }

        $oldest = $connection->creationTimeOfOldestPendingJob($queue->value);
        $waitingSeconds = $oldest === null ? 0 : now()->getTimestamp() - (int) $oldest;

        if ($waitingSeconds > self::STUCK_QUEUE_MINUTES * 60) {
            return HealthCheckResult::fail($name, sprintf(
                '%d job menunggu, tertua %d menit. Pastikan worker Supervisor berjalan.',
                $pending,
                intdiv($waitingSeconds, 60),
            ));
        }

        return HealthCheckResult::ok($name, "{$pending} job menunggu.");
    }

    private function checkPaymentNotifications(string $name): HealthCheckResult
    {
        $unprocessed = PaymentNotification::query()
            ->where('signature_valid', true)
            ->whereNull('processed_at')
            ->whereBetween('created_at', [
                now()->subHours(self::RECENT_HOURS),
                now()->subMinutes(self::UNPROCESSED_NOTIFICATION_MINUTES),
            ])
            ->count();

        if ($unprocessed > 0) {
            return HealthCheckResult::fail($name, sprintf(
                '%d notifikasi valid belum diproses lebih dari %d menit; pelanggan mungkin sudah membayar tetapi tagihan belum lunas.',
                $unprocessed,
                self::UNPROCESSED_NOTIFICATION_MINUTES,
            ));
        }

        return HealthCheckResult::ok($name, 'Semua notifikasi valid sudah diproses.');
    }

    private function checkFailedJobs(string $name): HealthCheckResult
    {
        $count = $this->failedJobs instanceof CountableFailedJobProvider
            ? $this->failedJobs->count()
            : count($this->failedJobs->all());

        if ($count > 0) {
            return HealthCheckResult::warning($name, "{$count} job gagal. Tinjau dengan `php artisan queue:failed`.");
        }

        return HealthCheckResult::ok($name, 'Tidak ada job gagal.');
    }

    private function checkWhatsappMessages(string $name): HealthCheckResult
    {
        $stuck = MessageLog::query()
            ->where('status', MessageStatus::Queued)
            ->where('created_at', '<', now()->subHours(self::QUEUED_MESSAGE_HOURS))
            ->count();
        $failed = MessageLog::query()
            ->where('status', MessageStatus::Failed)
            ->where('updated_at', '>=', now()->subHours(self::RECENT_HOURS))
            ->count();

        $problems = array_filter([
            $stuck > 0 ? sprintf('%d pesan tertahan lebih dari %d jam', $stuck, self::QUEUED_MESSAGE_HOURS) : null,
            $failed > 0 ? sprintf('%d pesan gagal dalam %d jam terakhir', $failed, self::RECENT_HOURS) : null,
        ]);

        if ($problems !== []) {
            return HealthCheckResult::warning($name, ucfirst(implode('; ', $problems)).'.');
        }

        return HealthCheckResult::ok($name, 'Tidak ada pesan tertahan atau gagal.');
    }

    /**
     * Router yang tidak terjangkau hanya peringatan: aplikasi tetap berjalan dan perintah router
     * dicoba ulang oleh queue.
     *
     * @return list<HealthCheckResult>
     */
    private function checkRouters(): array
    {
        try {
            $routers = Router::query()->active()->orderBy('id')->get();
        } catch (Throwable $exception) {
            return [HealthCheckResult::fail('Router', 'Daftar router tidak bisa dibaca: '.Str::limit($exception->getMessage(), 200))];
        }

        if ($routers->isEmpty()) {
            return [HealthCheckResult::skipped('Router', 'Belum ada router aktif.')];
        }

        return array_values($routers->map(fn (Router $router): HealthCheckResult => $this->checkRouter($router))->all());
    }

    private function checkRouter(Router $router): HealthCheckResult
    {
        $name = "Router: {$router->name}";

        try {
            return $this->network->testConnection($router)
                ? HealthCheckResult::ok($name, 'Terhubung.')
                : HealthCheckResult::warning($name, 'Tidak bisa dihubungi.');
        } catch (Throwable $exception) {
            return HealthCheckResult::warning($name, Str::limit($exception->getMessage(), 200));
        }
    }

    /**
     * Checklist .env production yang bisa diperiksa dari aplikasi (docs/10-deploy.md).
     */
    private function checkProductionConfig(string $name): HealthCheckResult
    {
        if (! app()->isProduction()) {
            return HealthCheckResult::skipped($name, 'Bukan environment production.');
        }

        $problems = array_keys(array_filter([
            'APP_DEBUG masih aktif' => Config::boolean('app.debug'),
            'APP_URL bukan https' => ! str_starts_with(Config::string('app.url'), 'https://'),
            'SESSION_SECURE_COOKIE belum aktif' => ! (bool) config('session.secure'),
            'QUEUE_CONNECTION masih sync' => $this->queueDriver() === 'sync',
            'MIDTRANS_IS_PRODUCTION belum aktif' => ! Config::boolean('services.midtrans.is_production'),
            'MIDTRANS_SERVER_KEY kosong' => blank(config('services.midtrans.server_key')),
            'WHATSAPP_DRIVER masih log' => config('services.whatsapp.driver') === 'log',
            'FONNTE_TOKEN kosong' => config('services.whatsapp.driver') === 'fonnte' && blank(config('services.fonnte.token')),
        ]));

        if ($problems !== []) {
            return HealthCheckResult::fail($name, implode('; ', $problems).'.');
        }

        return HealthCheckResult::ok($name, 'Sesuai checklist.');
    }

    private function usesRedis(): bool
    {
        return in_array('redis', [
            $this->queueDriver(),
            config('cache.stores.'.Config::string('cache.default').'.driver'),
            config('session.driver'),
        ], true);
    }

    private function queueDriver(): mixed
    {
        return config('queue.connections.'.Config::string('queue.default').'.driver');
    }
}
