<?php

declare(strict_types=1);

use App\Actions\Notifications\SendInvoiceReminders;
use App\Enums\InvoiceStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Models\Setting;
use Database\Seeders\MessageTemplateSeeder;

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);
    $this->travelTo('2026-10-09 09:00');
});

/**
 * @return array<int, MessageTemplateKey|null> invoice_id => template
 */
function remindersByInvoice(): array
{
    return MessageLog::query()->get()->mapWithKeys(fn (MessageLog $log) => [$log->invoice_id => $log->template_key])->all();
}

it('mengingatkan H-3 dan hari jatuh tempo hanya untuk invoice yang belum lunas', function () {
    $customer = customerOnProfile(Customer::factory()->active());
    $dueInThreeDays = invoiceDueAt($customer, '2026-10-12', InvoiceStatus::Unpaid);
    $dueToday = invoiceDueAt($customer, '2026-10-09', InvoiceStatus::Unpaid);
    invoiceDueAt($customer, '2026-10-19', InvoiceStatus::Paid);
    invoiceDueAt($customer, '2026-10-29', InvoiceStatus::Cancelled);
    invoiceDueAt($customer, '2026-10-10', InvoiceStatus::Unpaid);
    invoiceDueAt($customer, '2026-10-01', InvoiceStatus::Overdue);

    $result = app(SendInvoiceReminders::class)->handle(today());

    expect(remindersByInvoice())->toBe([
        $dueInThreeDays->id => MessageTemplateKey::ReminderBeforeDue,
        $dueToday->id => MessageTemplateKey::ReminderDue,
    ])->and($result)->toMatchArray(['queued' => 2, 'skipped' => 0, 'failed' => 0]);
});

it('tidak mengingatkan invoice yang lunas tepat pada H-3 atau hari jatuh tempo', function (string $dueAt, InvoiceStatus $status) {
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), $dueAt, $status);

    app(SendInvoiceReminders::class)->handle(today());

    expect(MessageLog::query()->count())->toBe(0);
})->with([
    'H-3 lunas' => ['2026-10-12', InvoiceStatus::Paid],
    'H-3 dibatalkan' => ['2026-10-12', InvoiceStatus::Cancelled],
    'jatuh tempo lunas' => ['2026-10-09', InvoiceStatus::Paid],
]);

it('tetap mengingatkan pelanggan yang sudah berhenti karena invoicenya masih ditagih', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->terminated()), '2026-10-09', InvoiceStatus::Unpaid);

    app(SendInvoiceReminders::class)->handle(today());

    expect(remindersByInvoice())->toBe([$invoice->id => MessageTemplateKey::ReminderDue]);
});

it('tidak mengirim pengingat ganda jika dijalankan ulang di hari yang sama', function () {
    $messages = fakeMessages();
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-12', InvoiceStatus::Unpaid);

    app(SendInvoiceReminders::class)->handle(today());
    $result = app(SendInvoiceReminders::class)->handle(today());

    $messages->assertCalled('send', 1);
    expect($result)->toMatchArray(['queued' => 0, 'skipped' => 1]);
});

it('hanya mengirim pengingat hari jatuh tempo jika pengingat H-N diatur 0', function () {
    Setting::query()->create(['key' => 'billing.reminder_days_before', 'value' => 0]);
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-09', InvoiceStatus::Unpaid);

    app(SendInvoiceReminders::class)->handle(today());

    expect(remindersByInvoice())->toBe([$invoice->id => MessageTemplateKey::ReminderDue]);
});

it('memakai jumlah hari pengingat dari pengaturan', function () {
    Setting::query()->create(['key' => 'billing.reminder_days_before', 'value' => 5]);
    $customer = customerOnProfile(Customer::factory()->active());
    $dueInFiveDays = invoiceDueAt($customer, '2026-10-14', InvoiceStatus::Unpaid);
    invoiceDueAt($customer, '2026-10-12', InvoiceStatus::Unpaid);

    app(SendInvoiceReminders::class)->handle(today());

    expect(remindersByInvoice())->toBe([$dueInFiveDays->id => MessageTemplateKey::ReminderBeforeDue]);
});

it('melanjutkan invoice lain dan melaporkan invoice yang gagal dijadwalkan', function () {
    $customer = customerOnProfile(Customer::factory()->active());
    $failing = invoiceDueAt($customer, '2026-10-12', InvoiceStatus::Unpaid);
    $other = invoiceDueAt($customer, '2026-10-09', InvoiceStatus::Unpaid);
    MessageLog::creating(function (MessageLog $log) use ($failing): void {
        throw_if($log->invoice_id === $failing->id, new RuntimeException('Simulasi galat.'));
    });

    $result = app(SendInvoiceReminders::class)->handle(today());

    expect($result)->toBe(['queued' => 1, 'skipped' => 0, 'failed' => 1, 'failed_invoice_ids' => [$failing->id]])
        ->and(remindersByInvoice())->toBe([$other->id => MessageTemplateKey::ReminderDue]);
});
