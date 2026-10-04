<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Invoice;
use App\Models\MessageLog;

it('mencatat pesan per invoice dan template untuk mencegah kiriman ganda', function () {
    $invoice = Invoice::factory()->create();

    MessageLog::factory()->for($invoice)->for($invoice->customer)->create([
        'template_key' => MessageTemplateKey::ReminderDue,
    ]);

    $log = $invoice->messageLogs()->where('template_key', MessageTemplateKey::ReminderDue)->sole();

    expect($log->template_key)->toBe(MessageTemplateKey::ReminderDue)
        ->and($log->status)->toBe(MessageStatus::Sent)
        ->and($log->customer->is($invoice->customer))->toBeTrue();
});
