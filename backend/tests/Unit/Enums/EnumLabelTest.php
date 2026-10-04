<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\PaymentChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;

it('memberi label untuk setiap nilai enum status', function (BackedEnum $case) {
    expect($case->label())->toBeString()->not->toBeEmpty();
})->with(fn (): array => collect([
    CustomerStatus::class,
    InvoiceStatus::class,
    IsolationReason::class,
    MessageStatus::class,
    MessageTemplateKey::class,
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
