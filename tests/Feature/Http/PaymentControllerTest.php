<?php

declare(strict_types=1);

use App\Enums\PaymentReviewStatus;
use App\Enums\Role;
use App\Models\Payment;
use Inertia\Testing\AssertableInertia as Assert;

it('memfilter daftar pembayaran menurut metode, status tinjauan, dan tanggal bayar', function (array $query, string $expectedReference) {
    Payment::factory()->cash()->create(['reference' => 'TUNAI-1', 'paid_at' => '2026-10-02 10:00']);
    Payment::factory()->qris()->create(['reference' => 'QRIS-1', 'paid_at' => '2026-10-03 10:00']);
    Payment::factory()->qris()->needsReview()->create(['reference' => 'ANOMALI-1', 'paid_at' => '2026-09-15 10:00']);

    $this->actingAs(userWithRole(Role::Kasir))
        ->get(route('payments.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/index')
            ->has('payments.data', 1)
            ->where('payments.data.0.reference', $expectedReference)
            ->has('methods', 3)
            ->has('review_statuses', 3));
})->with([
    'metode' => [['method' => 'cash'], 'TUNAI-1'],
    'status tinjauan' => [['review_status' => 'needs_review'], 'ANOMALI-1'],
    'rentang tanggal' => [['from' => '2026-10-03', 'to' => '2026-10-03'], 'QRIS-1'],
    'referensi' => [['search' => 'QRIS'], 'QRIS-1'],
]);

it('menolak rentang tanggal yang terbalik', function () {
    $this->actingAs(userWithRole(Role::Kasir))
        ->get(route('payments.index', ['from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

it('admin menandai pembayaran anomali sudah ditinjau', function () {
    $payment = Payment::factory()->qris()->needsReview()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->from(route('payments.index'))
        ->patch(route('payments.review', $payment), ['review_note' => 'Dana sudah dikembalikan'])
        ->assertRedirect(route('payments.index'))
        ->assertInertiaFlash('toast.message', 'Pembayaran ditandai sudah ditinjau.');

    expect($payment->refresh()->review_status)->toBe(PaymentReviewStatus::Resolved);
});

it('mewajibkan catatan tinjauan', function () {
    $payment = Payment::factory()->qris()->needsReview()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->patch(route('payments.review', $payment), [])
        ->assertSessionHasErrors(['review_note' => 'Catatan tinjauan wajib diisi.']);
});
