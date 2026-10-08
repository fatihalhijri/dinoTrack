<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentChargeStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentCharge;
use App\Providers\AppServiceProvider;
use App\Support\InvoicePaymentLink;

function publicInvoice(InvoiceStatus $status = InvoiceStatus::Unpaid): Invoice
{
    $customer = customerOnProfile(Customer::factory()->active()->state([
        'code' => 'PLG-000123',
        'name' => 'Budi Santoso',
        'phone' => '6281234567890',
        'address' => 'Jl. Melati No. 7',
    ]));

    $invoice = invoiceDueAt($customer, '2026-10-12', $status);
    $invoice->items()->create(['description' => 'Home 20 Mbps (5 Okt 2026 – 4 Nov 2026)', 'quantity' => 1, 'unit_price' => $invoice->total, 'amount' => $invoice->total]);

    return $invoice;
}

beforeEach(function () {
    $this->travelTo('2026-10-08 10:00');
});

it('menampilkan rincian tagihan lewat link bertanda tangan tanpa session dan tanpa data pribadi', function () {
    $invoice = publicInvoice();
    $invoice->update(['total' => 150_000]);

    $response = $this->get(InvoicePaymentLink::for($invoice));

    $response->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('Budi Santoso')
        ->assertSee('PLG-000123')
        ->assertSee('Rp150.000')
        ->assertSee('12 Oktober 2026')
        ->assertSee('Home 20 Mbps')
        ->assertSee('Tampilkan kode QRIS')
        ->assertDontSee('6281234567890')
        ->assertDontSee('Jl. Melati')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($response->headers->getCookies())->toBe([]);
});

it('menolak link tanpa tanda tangan atau yang diubah dengan halaman link tidak valid', function (Closure $url) {
    $invoice = publicInvoice();
    $other = invoiceDueAt($invoice->customer, '2026-11-12');

    $this->get($url($invoice, $other))
        ->assertForbidden()
        ->assertSee('Link tagihan tidak valid')
        ->assertDontSee($invoice->number);
})->with([
    'tanpa signature' => [fn (Invoice $invoice) => url("/tagihan/{$invoice->id}")],
    'ID invoice diganti' => [fn (Invoice $invoice, Invoice $other) => str_replace("/tagihan/{$invoice->id}?", "/tagihan/{$other->id}?", InvoicePaymentLink::for($invoice))],
    'signature diubah' => [fn (Invoice $invoice) => InvoicePaymentLink::for($invoice).'0'],
]);

it('menolak endpoint status dan pembayaran tanpa tanda tangan', function (string $method, string $suffix) {
    $invoice = publicInvoice();

    $this->json($method, "/tagihan/{$invoice->id}/{$suffix}")->assertForbidden();
})->with([['GET', 'status'], ['POST', 'qris']]);

it('tetap bisa dibuka tanpa batas waktu selama tagihan belum lunas', function () {
    $invoice = publicInvoice();
    $link = InvoicePaymentLink::for($invoice);

    $this->travel(400)->days();

    $this->get($link)->assertOk()->assertSee('Tampilkan kode QRIS');
});

it('menampilkan status akhir tanpa tombol bayar untuk tagihan lunas atau dibatalkan', function (InvoiceStatus $status, string $notice) {
    $invoice = publicInvoice($status);

    $this->get(InvoicePaymentLink::for($invoice))
        ->assertOk()
        ->assertSee($notice)
        ->assertDontSee('Tampilkan kode QRIS');
})->with([
    'lunas' => [InvoiceStatus::Paid, 'Tagihan sudah lunas'],
    'dibatalkan' => [InvoiceStatus::Cancelled, 'Tagihan ini sudah dibatalkan'],
]);

it('tidak membuat charge QRIS hanya karena halaman dibuka (pratinjau link WhatsApp)', function () {
    $gateway = fakeGateway();
    $invoice = publicInvoice();

    $this->get(InvoicePaymentLink::for($invoice))->assertOk();

    $gateway->assertNotCalled('createQrisCharge');
    expect(PaymentCharge::query()->count())->toBe(0);
});

it('langsung menampilkan QR dari charge pending yang masih berlaku', function () {
    $invoice = publicInvoice();
    PaymentCharge::factory()->for($invoice)->create(['qr_url' => 'https://fake.test/qr/lama.png', 'expires_at' => now()->addMinutes(10)]);

    $this->get(InvoicePaymentLink::for($invoice))
        ->assertOk()
        ->assertSee('https:\/\/fake.test\/qr\/lama.png', false);
});

it('membuat charge QRIS saat tombol bayar ditekan dan memakai ulang charge yang masih berlaku', function () {
    $gateway = fakeGateway();
    $invoice = publicInvoice();

    $first = $this->postJson(InvoicePaymentLink::pay($invoice));
    $second = $this->postJson(InvoicePaymentLink::pay($invoice));

    $first->assertOk()->assertJsonPath('charge.status', 'pending')->assertJsonPath('charge.qr_url', 'https://fake.test/qr/1.png');
    $second->assertOk()->assertJsonPath('charge.qr_url', 'https://fake.test/qr/1.png');
    $gateway->assertCalled('createQrisCharge', 1);
    expect(PaymentCharge::query()->count())->toBe(1);
});

it('menolak pembayaran untuk tagihan yang sudah lunas atau dibatalkan dengan 422', function (InvoiceStatus $status, string $message) {
    $gateway = fakeGateway();
    $invoice = publicInvoice($status);

    $this->postJson(InvoicePaymentLink::pay($invoice))
        ->assertUnprocessable()
        ->assertExactJson(['message' => $message]);
    $gateway->assertNotCalled('createQrisCharge');
})->with([
    [InvoiceStatus::Paid, 'Tagihan sudah lunas.'],
    [InvoiceStatus::Cancelled, 'Tagihan sudah dibatalkan.'],
]);

it('membalas 503 dengan pesan ramah tanpa detail galat saat gateway bermasalah', function () {
    fakeGateway()->failWith(new PaymentGatewayException('Membuat charge QRIS gagal: [500] Internal server error'));
    $invoice = publicInvoice();

    $this->postJson(InvoicePaymentLink::pay($invoice))
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Layanan pembayaran sedang bermasalah. Silakan coba beberapa saat lagi.']);
    expect(PaymentCharge::query()->sole()->status)->toBe(PaymentChargeStatus::Failed);
});

it('memberi status tagihan dan charge terakhir untuk polling halaman', function () {
    $invoice = publicInvoice(InvoiceStatus::Paid);
    PaymentCharge::factory()->settled()->for($invoice)->create(['qr_url' => 'https://fake.test/qr/1.png']);

    $this->getJson(InvoicePaymentLink::status($invoice))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson([
            'status' => 'paid',
            'is_paid' => true,
            'charge' => ['status' => 'settled', 'qr_url' => 'https://fake.test/qr/1.png'],
        ]);
});

it('membatasi permintaan QRIS per IP dan invoice', function () {
    fakeGateway();
    $invoice = publicInvoice();

    for ($i = 0; $i < AppServiceProvider::PUBLIC_INVOICE_PAYMENTS_PER_MINUTE; $i++) {
        $this->postJson(InvoicePaymentLink::pay($invoice))->assertOk();
    }

    $this->postJson(InvoicePaymentLink::pay($invoice))->assertTooManyRequests();
});

it('meng-escape nama pelanggan yang ditampilkan', function () {
    $invoice = publicInvoice();
    $invoice->customer->update(['name' => '<script>alert(1)</script>']);

    $this->get(InvoicePaymentLink::for($invoice))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});
