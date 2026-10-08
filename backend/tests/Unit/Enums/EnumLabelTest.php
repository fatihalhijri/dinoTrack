<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\HealthStatus;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\OutstandingAgeBucket;
use App\Enums\PaymentChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;

it('memberi label untuk setiap nilai enum status', function (BackedEnum $case) {
    expect($case->label())->toBeString()->not->toBeEmpty();
})->with(fn (): array => collect([
    CustomerStatus::class,
    HealthStatus::class,
    InvoiceStatus::class,
    IsolationReason::class,
    MessageStatus::class,
    MessageTemplateKey::class,
    OutstandingAgeBucket::class,
    PaymentChargeStatus::class,
    PaymentMethod::class,
    PaymentReviewStatus::class,
])->flatMap(fn (string $enum): array => collect($enum::cases())
    ->mapWithKeys(fn (BackedEnum $case): array => [class_basename($enum).'::'.$case->name => [$case]])
    ->all())
    ->all());

it('menganggap unpaid dan overdue sebagai tagihan yang masih harus dibayar', function () {
    expect(InvoiceStatus::outstanding())->toBe([InvoiceStatus::Unpaid, InvoiceStatus::Overdue]);
});

it('mengelompokkan umur tunggakan sesuai batas hari', function (int $ageDays, OutstandingAgeBucket $expected) {
    expect(OutstandingAgeBucket::forAgeDays($ageDays))->toBe($expected);
})->with([
    '1 hari' => [1, OutstandingAgeBucket::UpToSevenDays],
    '7 hari' => [7, OutstandingAgeBucket::UpToSevenDays],
    '8 hari' => [8, OutstandingAgeBucket::EightToThirtyDays],
    '30 hari' => [30, OutstandingAgeBucket::EightToThirtyDays],
    '31 hari' => [31, OutstandingAgeBucket::OverThirtyDays],
]);
