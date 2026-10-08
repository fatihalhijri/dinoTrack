---
description: Jalankan format kode, analisis statis, dan seluruh test, lalu perbaiki yang gagal
---

Jalankan pemeriksaan kualitas berurutan:

1. `./vendor/bin/pint`
2. `./vendor/bin/phpstan analyse --memory-limit=1G`
3. `php artisan test`
4. `npm run types:check`
5. `npm run check`
6. `npm run build`

Untuk setiap kegagalan:
- Jelaskan penyebabnya dalam satu atau dua kalimat.
- Perbaiki **kode aplikasinya**, bukan melemahkan test atau menurunkan level
  PHPStan. Jika test-nya yang memang salah, jelaskan alasannya dulu.
- Jalankan ulang sampai semua hijau.

Akhiri dengan ringkasan: jumlah test lulus, error PHPStan, dan file yang
diubah. Laporkan apa adanya jika masih ada yang gagal.
