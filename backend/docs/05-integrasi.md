# 05 — Integrasi Eksternal

Semua integrasi diakses lewat interface di `app/Contracts`. Detail API pihak
ketiga di bawah adalah ringkasan; **selalu cocokkan dengan dokumentasi resmi
terbaru** sebelum implementasi, dan sebutkan jika ada perbedaan.

---

## 1. Payment Gateway — Midtrans (QRIS dinamis)

### Interface

```php
interface PaymentGateway
{
    public function createQrisCharge(Invoice $invoice): PaymentChargeResult;
    public function verifyNotification(array $payload): bool;
    public function parseNotification(array $payload): GatewayNotification;
    public function checkStatus(string $orderId): GatewayNotification;
}
```

### Ringkasan API (Core API)

- Sandbox base URL: `https://api.sandbox.midtrans.com`
- Production base URL: `https://api.midtrans.com`
- Auth: Basic Auth, username = Server Key, password kosong
- Charge: `POST /v2/charge` dengan `payment_type: "qris"`,
  `transaction_details.order_id`, `transaction_details.gross_amount`
- Respons berisi `qr_string` dan URL gambar QR di `actions`
- Cek status: `GET /v2/{order_id}/status`

### Notifikasi (webhook)

- Endpoint aplikasi: `POST /webhooks/payments/midtrans` (dikecualikan dari CSRF)
- Verifikasi: `signature_key == sha512(order_id + status_code + gross_amount + server_key)`
- Status lunas: `transaction_status` = `settlement` (atau `capture` dengan `fraud_status=accept`)
- Status gagal: `expire`, `cancel`, `deny`, `failure`
- **Jangan percaya payload mentah**: setelah signature valid, boleh juga
  memanggil `checkStatus()` untuk konfirmasi ganda.
- Cocokkan `gross_amount` dengan `payment_charges.amount`. Jika tidak cocok,
  atau invoice sudah `paid`/`cancelled`, perlakukan sebagai pembayaran anomali
  (`review_status = needs_review`), tetap respons 200.

### order_id

Format: `{nomor_invoice_tanpa_slash}-{urutan_percobaan}`, contoh
`INV20261000001-1` (dari `INV/2026/10/00001`). Unik per percobaan charge.
Charge baru hanya dibuat jika charge sebelumnya `expired`/`failed`
(lihat `docs/04-aturan-bisnis.md`).

### Konfigurasi `.env`

```
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
```

### Uji lokal

Webhook butuh URL publik HTTPS. Gunakan ngrok / Cloudflare Tunnel saat
development, dan simulator pembayaran di dashboard sandbox Midtrans.

---

## 2. Mikrotik RouterOS

### Interface

```php
interface NetworkController
{
    public function testConnection(Router $router): bool;
    public function isolate(Customer $customer): void;
    public function activate(Customer $customer, string $profile): void;
    public function disableSecret(Customer $customer): void;
    public function isOnline(Customer $customer): bool;
}
```

### Implementasi

- Library: `evilfreelancer/routeros-api-php` (RouterOS API, port 8728 / 8729 SSL)
- **Isolir**: `/ppp/secret/set` profile = `router.isolation_profile`,
  lalu `/ppp/active/remove` sesi milik username tersebut
- **Aktivasi**: `/ppp/secret/set` profile = profil paket, lalu kick sesi aktif
- Kick sesi wajib agar profil baru langsung berlaku saat modem reconnect
- Lempar exception khusus (`RouterUnreachableException`,
  `SecretNotFoundException`) agar job bisa memutuskan retry atau tidak

### Persiapan di router (dikerjakan manual oleh admin jaringan)

1. PPP profile `ISOLIR` dengan `address-list=isolir`
2. Firewall NAT: traffic HTTP dari address-list `isolir` di-redirect ke
   halaman isolir aplikasi
3. Firewall filter: izinkan akses ke domain aplikasi dan payment gateway,
   blokir selain itu untuk address-list `isolir`
4. User API khusus dengan hak terbatas (`read`, `write`, `api`), bukan admin
5. Batasi akses API hanya dari IP server (lebih baik lewat VPN)

### Uji lokal

Gunakan Mikrotik CHR di VirtualBox untuk test manual. Test otomatis
memakai `FakeNetworkController`.

---

## 3. WhatsApp Gateway

### Interface

```php
interface MessageSender
{
    public function send(string $phone, string $message): MessageResult;
}
```

### Driver awal: Fonnte (tidak resmi)

- `POST https://api.fonnte.com/send`
- Header `Authorization: {token}`
- Body: `target` (nomor 62xxx), `message`
- Risiko: nomor bisa diblokir WhatsApp jika kirim massal. Beri jeda antar
  pesan (rate limit queue) dan hindari pesan identik ke banyak nomor sekaligus.

### Rencana: WhatsApp Business API resmi

Interface yang sama memungkinkan menambah driver resmi (Meta Cloud API)
tanpa mengubah kode bisnis. Pilih driver lewat `config/services.php`.

### Konfigurasi `.env`

```
WHATSAPP_DRIVER=fonnte
FONNTE_TOKEN=
```

### Normalisasi nomor

Simpan dan kirim dalam format `62xxxxxxxxxx`. Ubah awalan `08` → `628`,
`+62` → `62`, hapus spasi dan tanda hubung.
