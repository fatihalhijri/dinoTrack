# Starter Kit Pengembangan — Billing Internet ISP (DinoTrack)

Folder ini berisi semua yang dibutuhkan Claude Code untuk membangun aplikasi
billing internet secara bertahap, terarah, dan bisa diuji: fase backend
(Tahap 00–11) lalu fase frontend React (Tahap F00–F09).

> **Status (2026-10-09):** kedua fase sudah selesai; kemajuan dan sisa utang
> teknis ada di `PROGRESS.md`. Aplikasi kini berada di root repo (bukan folder
> `backend/` seperti di langkah awal di bawah). Untuk menjalankan dari clone:
> `composer install`, `npm ci`, salin `.env.example` ke `.env` lalu
> `php artisan key:generate`, `php artisan migrate --seed`,
> `php artisan storage:link`, dan `composer run dev`. Deploy: `docs/10-deploy.md`.

Langkah 1–5 di bawah adalah catatan cara proyek ini dimulai.

---

## Isi folder

```
dinoTrack/                    ← root repo = aplikasi Laravel
├── MULAI-DI-SINI.md          ← file ini (panduan pemakaian)
├── CLAUDE.md                 ← "otak" proyek, dibaca otomatis oleh Claude Code
├── PROGRESS.md               ← checklist kemajuan per tahap
├── docs/                     ← spesifikasi proyek (sumber kebenaran)
│   ├── 01-spesifikasi-produk.md … 07-definition-of-done.md
│   ├── 08-kontrak-halaman.md   ← kontrak props halaman Inertia (fase frontend)
│   ├── 09-audit-keamanan.md
│   └── 10-deploy.md
├── prompts/                  ← prompt siap pakai, satu file per tahap
│   ├── 00-setup-proyek.md … 11-kesiapan-deploy.md   (backend)
│   └── F00-design-system-dan-layout.md … F09-poles-akhir.md   (frontend)
└── .claude/
    ├── settings.json         ← izin perintah untuk Claude Code
    └── commands/             ← perintah khusus: /tahap, /cek, /review, /selesai
```

---

## Langkah 1 — Siapkan alat (sekali saja)

Pastikan sudah terpasang di Windows:

| Alat | Cek versi |
|---|---|
| PHP 8.4+ | `php -v` |
| Composer | `composer -V` |
| Node.js 22+ | `node -v` |
| Git for Windows | `git --version` |
| MySQL 8 (Laragon/XAMPP) | — |
| Laravel Installer | `composer global require laravel/installer` |
| Claude Code | `irm https://claude.ai/install.ps1 \| iex` lalu `claude --version` |

Redis opsional saat development (queue bisa pakai driver `database` dulu).

---

## Langkah 2 — Buat proyek Laravel

Disarankan memakai folder terpisah dari dinoTrack:

```powershell
cd C:\web_developer
mkdir billing-internet
cd billing-internet
laravel new backend
```

Pilihan saat instalasi:
- Starter kit: **React**
- Authentication: **Laravel's built-in authentication**
- Testing framework: **Pest**
- Database: **MySQL**

---

## Langkah 3 — Gabungkan kit ini ke proyek

Salin **seluruh isi** folder kit ini (termasuk folder tersembunyi `.claude`)
ke dalam folder proyek Laravel `backend` yang baru dibuat. Tidak ada file
Laravel yang tertimpa.

Hasil akhirnya:

```
C:\web_developer\billing-internet\backend\
├── app\  bootstrap\  config\  database\  ...   (dari Laravel)
├── CLAUDE.md  PROGRESS.md  MULAI-DI-SINI.md     (dari kit)
├── docs\  prompts\                               (dari kit)
└── .claude\                                      (dari kit)
```

Lalu commit awal:

```powershell
cd backend
git init
git add .
git commit -m "chore: inisialisasi Laravel + starter kit billing"
```

---

## Langkah 4 — Baca dan sesuaikan `docs/`

Sebelum mulai, **baca semua file di `docs/`** dan ubah bagian yang tidak
sesuai dengan bisnis kamu, terutama:

- `docs/04-aturan-bisnis.md` → tanggal tagih, masa toleransi, denda
- `docs/05-integrasi.md` → payment gateway dan WhatsApp gateway yang dipilih
- `docs/02-arsitektur.md` → keputusan single-tenant atau multi-tenant (SaaS)

Claude akan mengikuti dokumen ini. Kalau dokumen salah, kode juga salah.

---

## Langkah 5 — Bekerja per tahap dengan Claude Code

```powershell
cd C:\web_developer\billing-internet\backend
claude
```

Lalu jalankan tahap demi tahap:

```
/tahap 00
```

Alur setiap tahap:

1. `/tahap NN` → Claude membaca prompt tahap itu dan **menyusun rencana dulu**.
2. Periksa rencananya. Koreksi kalau perlu, lalu setujui.
3. Claude mengerjakan, lalu menjalankan test.
4. `/cek` → menjalankan format kode, analisis statis, dan seluruh test.
5. `/review` → Claude meninjau ulang perubahannya sendiri.
6. `/selesai NN` → update `PROGRESS.md` dan commit.
7. `/clear` → bersihkan konteks sebelum tahap berikutnya.

Kalau mau tanpa perintah khusus, buka file di `prompts/` dan salin bagian
**Prompt** ke Claude Code.

---

## Aturan emas

1. **Satu tahap per sesi**, `/clear` di antara tahap. Hemat kuota Pro dan hasil lebih akurat.
2. **Jangan lanjut kalau test merah.**
3. **Commit di setiap akhir tahap** supaya mudah mundur kalau ada kesalahan.
4. **Baca diff sebelum commit.** Kamu tetap pemilik kodenya.
5. **Kalau Claude salah arah, tekan `Esc`** lalu beri koreksi.
6. **Perubahan keputusan desain → update `docs/` dulu**, baru minta Claude mengikuti.
7. **Jangan pernah tempel kredensial asli** (server key, token WA, password router) ke chat. Isi langsung di `.env`.
