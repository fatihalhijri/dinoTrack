<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\HealthCheckResult;
use App\Enums\HealthStatus;
use App\Services\Health\HealthChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dijadwalkan setiap 15 menit; masalah ditulis ke log agar terlihat tanpa membuka terminal.
 * Kode keluar gagal hanya untuk status `fail`, sehingga bisa dipakai pemantauan eksternal.
 */
#[Signature('billing:health')]
#[Description('Periksa database, Redis, antrean, router, dan kegagalan yang perlu ditindak admin')]
class HealthCommand extends Command
{
    public function handle(HealthChecker $checker): int
    {
        $results = $checker->run();

        $this->table(
            ['Pemeriksaan', 'Status', 'Keterangan'],
            array_map(fn (HealthCheckResult $result): array => [$result->name, $result->status->label(), $result->message], $results),
        );

        $this->logProblems($results);

        $failed = $this->countStatus($results, HealthStatus::Fail);
        $warnings = $this->countStatus($results, HealthStatus::Warning);

        if ($failed > 0) {
            $this->error(sprintf('Kesehatan aplikasi: %d gagal, %d peringatan.', $failed, $warnings));

            return self::FAILURE;
        }

        $this->info(sprintf('Kesehatan aplikasi: 0 gagal, %d peringatan.', $warnings));

        return self::SUCCESS;
    }

    /**
     * @param  list<HealthCheckResult>  $results
     */
    private function logProblems(array $results): void
    {
        foreach ($results as $result) {
            $context = ['check' => $result->name, 'message' => $result->message];

            match ($result->status) {
                HealthStatus::Fail => Log::error('Pemeriksaan kesehatan gagal.', $context),
                HealthStatus::Warning => Log::warning('Pemeriksaan kesehatan memberi peringatan.', $context),
                default => null,
            };
        }
    }

    /**
     * @param  list<HealthCheckResult>  $results
     */
    private function countStatus(array $results, HealthStatus $status): int
    {
        return count(array_filter($results, fn (HealthCheckResult $result): bool => $result->status === $status));
    }
}
