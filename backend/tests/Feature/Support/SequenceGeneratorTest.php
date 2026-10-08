<?php

declare(strict_types=1);

use App\Support\SequenceGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

it('mulai dari 1 saat key belum ada lalu berurutan', function () {
    $sequence = app(SequenceGenerator::class);

    expect($sequence->next('customer'))->toBe(1)
        ->and($sequence->next('customer'))->toBe(2)
        ->and($sequence->next('customer'))->toBe(3);

    $this->assertDatabaseHas('sequences', ['key' => 'customer', 'last_value' => 3]);
});

it('melanjutkan dari nilai terakhir yang tersimpan', function () {
    DB::table('sequences')->insert(['key' => 'customer', 'last_value' => 30]);

    expect(app(SequenceGenerator::class)->next('customer'))->toBe(31);
});

it('menyimpan urutan terpisah untuk setiap key', function () {
    $sequence = app(SequenceGenerator::class);
    $sequence->next('customer');
    $sequence->next('customer');

    expect($sequence->next('invoice:2026-10'))->toBe(1);
});

it('ikut di-rollback bersama transaksi pemanggil sehingga nomor tidak terbuang', function () {
    $sequence = app(SequenceGenerator::class);
    $sequence->next('customer');

    try {
        DB::transaction(function () use ($sequence): void {
            $sequence->next('customer');

            throw new RuntimeException('gagal menyimpan pelanggan');
        });
    } catch (RuntimeException) {
    }

    expect($sequence->next('customer'))->toBe(2);
});

it('mengunci baris sequence sampai transaksi selesai sehingga pemanggil bersamaan harus menunggu', function () {
    app(SequenceGenerator::class)->next('customer');

    // Koneksi kedua mewakili permintaan lain yang berjalan bersamaan.
    config(['database.connections.concurrent' => config('database.connections.mysql')]);
    $concurrent = DB::connection('concurrent');
    $concurrent->statement('SET SESSION innodb_lock_wait_timeout = 1');

    try {
        expect(fn () => $concurrent->transaction(
            fn () => $concurrent->table('sequences')->where('key', 'customer')->lockForUpdate()->value('last_value'),
        ))->toThrow(QueryException::class, 'Lock wait timeout exceeded');
    } finally {
        DB::purge('concurrent');
    }
});

it('memberi nilai unik tanpa galat saat beberapa proses meminta nomor bersamaan', function () {
    $key = 'test:concurrency';
    $processes = 4;
    $callsPerProcess = 150;

    // Proses anak tidak melihat transaksi RefreshDatabase, sehingga data disiapkan dan
    // dibersihkan lewat koneksi terpisah yang langsung commit.
    config(['database.connections.concurrent' => config('database.connections.mysql')]);
    $concurrent = DB::connection('concurrent');
    $concurrent->table('sequences')->where('key', $key)->delete();
    $concurrent->table('sequences')->insert(['key' => $key, 'last_value' => 0]);

    $code = sprintf(
        'for ($i = 0; $i < %d; $i++) { try { app(%s::class)->next("%s"); echo "."; } catch (Throwable $e) { echo PHP_EOL."GALAT ".$e->getMessage().PHP_EOL; } }',
        $callsPerProcess,
        SequenceGenerator::class,
        $key,
    );

    try {
        $results = Process::concurrently(function (Pool $pool) use ($processes, $code): void {
            for ($i = 0; $i < $processes; $i++) {
                $pool->path(base_path())
                    ->env([
                        'APP_ENV' => 'testing',
                        'DB_CONNECTION' => 'mysql',
                        'DB_DATABASE' => config('database.connections.mysql.database'),
                    ])
                    ->timeout(120)
                    ->command([PHP_BINARY, 'artisan', 'tinker', '--execute', $code]);
            }
        });

        $output = collect($results)->map(fn ($result) => $result->output().$result->errorOutput())->implode('');

        expect($output)->not->toContain('GALAT')
            ->and(substr_count($output, '.'))->toBe($processes * $callsPerProcess)
            ->and((int) $concurrent->table('sequences')->where('key', $key)->value('last_value'))->toBe($processes * $callsPerProcess);
    } finally {
        $concurrent->table('sequences')->where('key', $key)->delete();
        DB::purge('concurrent');
    }
});
