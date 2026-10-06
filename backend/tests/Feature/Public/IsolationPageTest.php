<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Setting;
use App\Providers\AppServiceProvider;

function isolatedPageCustomer(): Customer
{
    return customerOnProfile(Customer::factory()->isolated()->state([
        'code' => 'PLG-000123',
        'name' => 'Budi Santoso',
        'phone' => '6281234567890',
    ]));
}

it('menampilkan pesan isolir dan cara bayar tanpa membuat session atau cookie', function () {
    config(['app.name' => 'DinoTrack']);

    $response = $this->get('/isolir');

    $response->assertOk()
        ->assertSee('DinoTrack')
        ->assertSee('Layanan internet Anda sedang dibatasi')
        ->assertSee('Cara membayar')
        ->assertSee('name="kode"', false)
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($response->headers->getCookies())->toBe([]);
});

it('menampilkan tagihan yang belum dibayar jika kode dan 4 digit nomor WhatsApp cocok', function () {
    $this->travelTo('2026-10-20 10:00');
    $customer = isolatedPageCustomer();
    $overdue = invoiceDueAt($customer, '2026-10-01');
    $paid = invoiceDueAt($customer, '2026-09-01', InvoiceStatus::Paid);

    $this->get('/isolir?kode=plg-000123&hp=7890')
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee($overdue->number)
        ->assertDontSee($paid->number)
        ->assertDontSee('Data tidak ditemukan');
});

it('memberi pesan yang sama untuk kode atau digit yang salah agar kode terdaftar tidak bisa ditebak', function (string $query) {
    $customer = isolatedPageCustomer();
    Customer::factory()->state(['code' => 'PLG-000999', 'phone' => '6281111112222'])->create()->delete();

    $this->get('/isolir?'.$query)
        ->assertOk()
        ->assertSee('Data tidak ditemukan')
        ->assertDontSee($customer->name);
})->with([
    'digit salah' => ['kode=PLG-000123&hp=0000'],
    'kode tidak ada' => ['kode=PLG-999999&hp=7890'],
    'format digit salah' => ['kode=PLG-000123&hp=78a0'],
    'pelanggan dihapus' => ['kode=PLG-000999&hp=2222'],
]);

it('meng-escape masukan yang ditampilkan ulang', function () {
    $this->get('/isolir?kode='.urlencode('<script>alert(1)</script>').'&hp=1234')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('membatasi percobaan cek tagihan per kode pelanggan', function () {
    isolatedPageCustomer();

    for ($attempt = 0; $attempt < AppServiceProvider::ISOLATION_LOOKUPS_PER_CODE_PER_HOUR; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
            ->get('/isolir?kode=PLG-000123&hp='.str_pad((string) $attempt, 4, '0', STR_PAD_LEFT))
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
        ->get('/isolir?kode=PLG-000123&hp=7890')
        ->assertTooManyRequests();
});

it('menampilkan kontak WhatsApp admin jika sudah diisi', function () {
    Setting::query()->create(['key' => 'business.whatsapp', 'value' => '0812-0000-1111']);

    $this->get('/isolir?kode=PLG-000123&hp=7890')
        ->assertSee('https://wa.me/6281200001111', false)
        ->assertSee('+6281200001111');
});
