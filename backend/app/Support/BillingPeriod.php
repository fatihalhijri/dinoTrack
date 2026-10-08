<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Satu periode tagihan (tanggal awal dan akhir inklusif) menurut aturan docs/04:
 * periode berjalan dari `billing_day` sampai sehari sebelum `billing_day` bulan berikutnya.
 * `billing_day` maksimal 28 sehingga selalu ada di setiap bulan.
 */
final readonly class BillingPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * Periode penuh yang memuat tanggal tersebut.
     */
    public static function containing(CarbonInterface $date, int $billingDay): self
    {
        $date = self::dateOnly($date);
        $start = $date->day >= $billingDay
            ? $date->setDay($billingDay)
            : $date->startOfMonth()->subMonth()->setDay($billingDay);

        return new self($start, $start->addMonth()->subDay());
    }

    /**
     * Periode pertama: dari tanggal mulai berlangganan sampai sehari sebelum `billing_day`
     * berikutnya. Sama dengan periode penuh jika tanggal mulai jatuh tepat di `billing_day`.
     */
    public static function first(CarbonInterface $startsAt, int $billingDay): self
    {
        return new self(self::dateOnly($startsAt), self::containing($startsAt, $billingDay)->end);
    }

    /**
     * Semua periode yang sudah dimulai (awal ≤ hari ini) tetapi belum ditagih.
     *
     * Jika belum ada invoice sama sekali, hanya periode berjalan yang ditagih, kecuali
     * `$fromFirstPeriod` (aktivasi) atau langganan baru dimulai di periode berjalan.
     * Ini mencegah subscription lama tanpa invoice (data demo atau migrasi) ditagih mundur
     * sampai tanggal mulainya.
     *
     * @return list<self>
     */
    public static function due(
        CarbonInterface $startsAt,
        int $billingDay,
        ?CarbonInterface $lastPeriodEnd,
        CarbonInterface $today,
        bool $fromFirstPeriod = false,
    ): array {
        $today = self::dateOnly($today);
        $current = self::containing($today, $billingDay);

        $period = match (true) {
            $lastPeriodEnd !== null => self::containing(self::dateOnly($lastPeriodEnd)->addDay(), $billingDay),
            $fromFirstPeriod, self::dateOnly($startsAt)->greaterThanOrEqualTo($current->start) => self::first($startsAt, $billingDay),
            default => $current,
        };

        $periods = [];

        while ($period->start->lessThanOrEqualTo($today)) {
            $periods[] = $period;
            $period = $period->next($billingDay);
        }

        return $periods;
    }

    public function next(int $billingDay): self
    {
        return self::containing($this->end->addDay(), $billingDay);
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    private static function dateOnly(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d'), $date->getTimezone());
    }
}
