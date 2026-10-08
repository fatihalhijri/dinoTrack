<?php

declare(strict_types=1);

use App\Support\BillingPeriod;
use Carbon\CarbonImmutable;

function ymd(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

/**
 * @param  list<BillingPeriod>  $periods
 * @return list<string>
 */
function periodStrings(array $periods): array
{
    return array_map(fn (BillingPeriod $period) => $period->start->toDateString().'..'.$period->end->toDateString(), $periods);
}

it('menentukan periode penuh yang memuat sebuah tanggal', function (string $date, int $billingDay, string $start, string $end) {
    $period = BillingPeriod::containing(ymd($date), $billingDay);

    expect($period->start->toDateString())->toBe($start)
        ->and($period->end->toDateString())->toBe($end);
})->with([
    'tepat di billing_day' => ['2026-10-10', 10, '2026-10-10', '2026-11-09'],
    'sesudah billing_day' => ['2026-10-25', 10, '2026-10-10', '2026-11-09'],
    'sebelum billing_day' => ['2026-10-05', 10, '2026-09-10', '2026-10-09'],
    'billing_day 1' => ['2026-10-31', 1, '2026-10-01', '2026-10-31'],
    'billing_day 28 di akhir Januari' => ['2027-01-31', 28, '2027-01-28', '2027-02-27'],
    'billing_day 28 di Februari biasa' => ['2027-02-28', 28, '2027-02-28', '2027-03-27'],
    'billing_day 28 di Februari kabisat' => ['2028-02-29', 28, '2028-02-28', '2028-03-27'],
    'sebelum billing_day di awal Maret' => ['2028-03-01', 28, '2028-02-28', '2028-03-27'],
    'pergantian tahun' => ['2026-12-20', 15, '2026-12-15', '2027-01-14'],
    'awal Januari mundur ke Desember' => ['2027-01-05', 15, '2026-12-15', '2027-01-14'],
]);

it('mengabaikan jam pada tanggal masukan', function () {
    $period = BillingPeriod::containing(CarbonImmutable::parse('2026-10-10 23:59:59'), 10);

    expect($period->start->format('Y-m-d H:i:s'))->toBe('2026-10-10 00:00:00');
});

it('menghitung jumlah hari periode secara inklusif', function (string $date, int $billingDay, int $days) {
    expect(BillingPeriod::containing(ymd($date), $billingDay)->days())->toBe($days);
})->with([
    'Oktober ke November' => ['2026-10-10', 10, 31],
    'November ke Desember' => ['2026-11-10', 10, 30],
    'melewati Februari biasa' => ['2027-02-10', 10, 28],
    'melewati Februari kabisat' => ['2028-02-10', 10, 29],
]);

it('membuat periode pertama dari tanggal mulai sampai sehari sebelum billing_day berikutnya', function (string $startsAt, int $billingDay, string $end, int $days) {
    $period = BillingPeriod::first(ymd($startsAt), $billingDay);

    expect($period->start->toDateString())->toBe($startsAt)
        ->and($period->end->toDateString())->toBe($end)
        ->and($period->days())->toBe($days);
})->with([
    'pasang setelah billing_day' => ['2026-10-25', 10, '2026-11-09', 16],
    'pasang sebelum billing_day' => ['2026-10-05', 10, '2026-10-09', 5],
    'pasang tepat di billing_day (periode penuh)' => ['2026-10-10', 10, '2026-11-09', 31],
    'pasang di hari terakhir periode' => ['2026-10-31', 1, '2026-10-31', 1],
]);

it('melanjutkan ke periode berikutnya', function () {
    $next = BillingPeriod::first(ymd('2026-10-25'), 10)->next(10);

    expect(periodStrings([$next]))->toBe(['2026-11-10..2026-12-09']);
});

it('menagih periode setelah invoice terakhir sampai hari ini', function () {
    $periods = BillingPeriod::due(ymd('2026-01-10'), 10, ymd('2026-09-09'), ymd('2026-10-10'));

    expect(periodStrings($periods))->toBe(['2026-09-10..2026-10-09', '2026-10-10..2026-11-09']);
});

it('tidak menagih periode yang belum dimulai', function () {
    expect(BillingPeriod::due(ymd('2026-01-10'), 10, ymd('2026-10-09'), ymd('2026-10-09')))->toBe([]);
});

it('hanya menagih periode berjalan untuk subscription lama tanpa invoice', function () {
    $periods = BillingPeriod::due(ymd('2025-12-03'), 10, null, ymd('2026-10-15'));

    expect(periodStrings($periods))->toBe(['2026-10-10..2026-11-09']);
});

it('menagih dari periode pertama jika langganan dimulai di periode berjalan', function () {
    $periods = BillingPeriod::due(ymd('2026-10-12'), 10, null, ymd('2026-10-15'));

    expect(periodStrings($periods))->toBe(['2026-10-12..2026-11-09']);
});

it('menagih semua periode sejak tanggal mulai saat aktivasi dengan tanggal pasang mundur', function () {
    $periods = BillingPeriod::due(ymd('2026-08-25'), 10, null, ymd('2026-10-15'), fromFirstPeriod: true);

    expect(periodStrings($periods))->toBe([
        '2026-08-25..2026-09-09',
        '2026-09-10..2026-10-09',
        '2026-10-10..2026-11-09',
    ]);
});
