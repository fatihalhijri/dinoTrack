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
    public function createQrisCharge(Invoice $invoice, string $orderId): PaymentChargeResult;
    public function verifyNotification(array $payload): bool;
    public function parseNotification(array $payload): GatewayNotification;
    public function checkStatus(string $orderId): GatewayNotification;
}
```

`order_id` ditentukan `CreateQrisCharge` (bukan gateway) karena urutan
percobaan disimpan di `payment_charges.attempt`. `GatewayNotification::$status`
bernilai `null` untuk status yang tidak mengubah charge (refund, chargeback,
status tidak dikenal); status mentah ada di `transactionStatus`.

### Ringkasan API (Core API)

Dicocokkan dengan dokumentasi resmi Midtrans pada 2026-10-05.

- Sandbox base URL: `https://api.sandbox.midtrans.com`
- Production base URL: `https://api.midtrans.com`
- Auth: Basic Auth, username = Server Key, password kosong
- Charge: `POST /v2/charge` dengan `payment_type: "qris"`,
  `transaction_details.order_id`, `transaction_details.gross_amount` (integer),
  dan `custom_expiry` (`expiry_duration` 15, `unit` `minute`).
  `qris.acquirer` tidak dikirim (default `gopay`).
- Header `Idempotency-Key` = `order_id` (maks. 46 karakter, berlaku 5 menit),
  sehingga retry setelah timeout mendapat respons yang sama, bukan 406.
- Respons charge: URL gambar QR di `actions` (`generate-qr-code-v2` berbingkai
  ASPI dipakai lebih dulu, lalu `generate-qr-code`). **`qr_string` dan
  `expiry_time` tidak didokumentasikan** untuk QRIS: disimpan bila ada;
  `expires_at` dihitung dari `custom_expiry` bila `expiry_time` tidak ada.
- Keberhasilan dilihat dari **`status_code` di body** (`"201"` untuk charge),
  bukan hanya status HTTP: galat seperti 406 (order_id ganda) bisa datang
  dengan HTTP 200.
- Cek status: `GET /v2/{order_id}/status`. Order yang tidak ada dibalas HTTP 200
  dengan body `status_code: "404"`; `status_code` transaksi yang ada pun
  bervariasi (200, 201, 407), sehingga keberhasilan dilihat dari adanya
  `transaction_status`.
- Nominal di respons dan notifikasi berupa string dua desimal (`"150000.00"`);
  dikonversi ke integer rupiah, nominal pecahan ditolak.
- Waktu (`transaction_time`, `settlement_time`, `expiry_time`) dalam GMT+7.
- HTTP client: connect timeout 5 detik, timeout 15 detik, 2 percobaan untuk
  galat koneksi. Semua kegagalan menjadi `PaymentGatewayException` berisi kode
  dan pesan Midtrans (tanpa kredensial).

### Notifikasi (webhook)

- Endpoint aplikasi: `POST /webhooks/payments/midtrans`, didaftarkan di
  `routes/webhooks.php` dengan grup `api` (tanpa session, cookie, dan CSRF)
  dan rate limit `webhooks` 120/menit per IP.
- Payload selalu disimpan ke `payment_notifications` (termasuk yang
  signature-nya salah). Payload kosong dibalas 400.
- Verifikasi: `signature_key == sha512(order_id + status_code + gross_amount + server_key)`,
  memakai `gross_amount` persis seperti diterima. Field yang hilang atau Server
  Key kosong dianggap signature salah → **403**.
- Signature valid → dispatch `ProcessPaymentNotificationJob` lalu balas 200
  `OK`. Job memanggil `ProcessGatewayNotification` dan mengisi `processed_at`.
- Pemetaan status: `settlement` (atau `capture` + `fraud_status=accept`) →
  `settled`; `pending` → `pending`; `expire` → `expired`; `cancel`, `deny`,
  `failure` → `failed`; `refund`, `partial_refund`, `chargeback`,
  `partial_chargeback` → hanya dicatat (`payment.gateway_reversal`).
- Midtrans bisa mengirim notifikasi **ganda dan tidak berurutan**: proses
  idempotent (satu charge paling banyak satu payment) dan status akhir tidak
  ditimpa notifikasi yang datang terlambat.
- Signature dengan Server Key sudah cukup; `checkStatus()` tidak dipanggil di
  jalur webhook, tetapi dipakai rekonsiliasi per jam
  (`billing:reconcile-payments`) untuk menangkap webhook yang terlewat.
- Cocokkan `gross_amount` dengan `payment_charges.amount`. Jika tidak cocok,
  atau invoice sudah `paid`/`cancelled`, perlakukan sebagai pembayaran anomali
  (`review_status = needs_review`), tetap respons 200.
- `order_id` yang tidak dikenal (misalnya notifikasi uji dari dashboard)
  dicatat di log lalu diabaikan.

### order_id

Format: `{nomor_invoice_tanpa_slash}-{urutan_percobaan}`, contoh
`INV20261000001-1` (dari `INV/2026/10/00001`). Unik per percobaan charge.
Charge baru hanya dibuat jika charge sebelumnya `expired`/`failed`
(lihat `docs/04-aturan-bisnis.md`). Baris charge dibuat sebelum gateway
dipanggil; jika panggilan gagal, baris itu ditandai `failed` sehingga
percobaan berikutnya memakai `order_id` baru.

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

Catatan implementasi (dicocokkan dengan library 1.7.1 pada 2026-10-05):

- Satu koneksi per perintah lewat `RouterOsClientFactory`: connect timeout 5
  detik, socket timeout 10 detik, **1 percobaan** (bawaan library 10 percobaan
  dengan jeda 1 detik akan menahan worker lebih dari semenit). Pengulangan
  diserahkan ke queue.
- Library tidak punya `disconnect()` publik; koneksi ditutup dengan mengirim
  `/quit` di `finally`.
- Secret dicari dengan `/ppp/secret/print ?name=`; tidak ada →
  `SecretNotFoundException`.
- **Aktivasi** juga mengirim `disabled=no` karena secret pelanggan baru atau
  yang diaktifkan kembali mungkin sedang dinonaktifkan. **Nonaktif secret**:
  `disabled=yes` lalu kick sesi.
- Balasan dibaca mentah karena parser library menghilangkan penanda `!trap`;
  perintah yang ditolak router (misalnya profil tidak ada) →
  `RouterCommandException` (tidak dicoba ulang). Trap saat memutus sesi yang
  sudah hilang diabaikan.
- Gagal koneksi, socket timeout, dan login ditolak →
  `RouterUnreachableException` berisi nama/host router, tanpa password.
- SSL (`use_ssl`) memakai opsi bawaan library: cipher ADH tanpa verifikasi
  sertifikat, satu-satunya cara api-ssl RouterOS berjalan tanpa sertifikat.
  Karena itu akses API tetap wajib dibatasi ke IP server / VPN.

### Persiapan di router (dikerjakan manual oleh admin jaringan)

1. PPP profile `ISOLIR` dengan `address-list=isolir`
2. Firewall NAT: traffic HTTP dari address-list `isolir` di-redirect ke
   halaman isolir aplikasi
3. Firewall filter: izinkan akses ke domain aplikasi dan payment gateway,
   blokir selain itu untuk address-list `isolir`. Pertimbangkan juga
   mengizinkan WhatsApp agar pelanggan bisa membuka link tagihan dan
   menghubungi admin. Redirect ke `http://<domain-aplikasi>/isolir`
   (lebih mudah lewat web-proxy redirect daripada dst-nat, karena dst-nat
   meneruskan header Host domain aslinya)
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
