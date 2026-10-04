<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageTemplateKey: string
{
    case InvoiceIssued = 'invoice_issued';
    case ReminderBeforeDue = 'reminder_before_due';
    case ReminderDue = 'reminder_due';
    case Isolated = 'isolated';
    case PaymentReceived = 'payment_received';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Tagihan terbit',
            self::ReminderBeforeDue => 'Pengingat sebelum jatuh tempo',
            self::ReminderDue => 'Pengingat hari jatuh tempo',
            self::Isolated => 'Pemberitahuan isolir',
            self::PaymentReceived => 'Konfirmasi pembayaran',
        };
    }
}
