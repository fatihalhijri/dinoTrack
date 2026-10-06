<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentReviewStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin menandai pembayaran anomali sudah ditinjau (misalnya uang sudah dikembalikan di luar
 * sistem). Invoice tidak berubah. Catatan tinjauan ditambahkan di bawah alasan anomali agar
 * alasan aslinya tetap terbaca.
 */
final class ResolvePayment
{
    public const int MIN_NOTE_LENGTH = 5;

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Payment $payment, string $note, User $by): Payment
    {
        $note = trim($note);

        if (mb_strlen($note) < self::MIN_NOTE_LENGTH) {
            throw ValidationException::withMessages([
                'review_note' => 'Catatan tinjauan minimal '.self::MIN_NOTE_LENGTH.' karakter.',
            ]);
        }

        return DB::transaction(function () use ($payment, $note, $by): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->review_status !== PaymentReviewStatus::NeedsReview) {
                throw ValidationException::withMessages([
                    'review_status' => 'Hanya pembayaran yang perlu tinjauan yang bisa ditandai sudah ditinjau.',
                ]);
            }

            $resolution = sprintf('Ditinjau %s (%s): %s', $by->name, now()->format('Y-m-d H:i'), $note);

            $payment->update([
                'review_status' => PaymentReviewStatus::Resolved,
                'review_note' => trim(($payment->review_note ?? '')."\n\n".$resolution),
            ]);

            $this->logger->log('payment.resolved', $payment, $by, ['note' => $note]);

            return $payment;
        });
    }
}
