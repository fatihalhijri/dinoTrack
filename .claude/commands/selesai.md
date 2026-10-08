---
description: Tutup satu tahap — cek Definition of Done, update PROGRESS.md, dan commit
argument-hint: <nomor tahap, contoh 04>
---

Kita menutup tahap **$ARGUMENTS**.

1. Periksa setiap poin di `docs/07-definition-of-done.md` dan laporkan
   ✅/❌ untuk masing-masing. Jika ada ❌, berhenti dan jelaskan apa yang
   kurang.
2. Jika semua ✅:
   - Ubah status tahap di `PROGRESS.md` menjadi ✅, isi tanggal hari ini dan
     catatan singkat (1 kalimat).
   - Catat keputusan yang menyimpang dari `docs/` di tabel "Keputusan penting".
   - Catat hal yang ditunda di "Utang teknis".
3. Tampilkan ringkasan `git status` dan usulan pesan commit sesuai bagian
   **Commit** di file `prompts/$ARGUMENTS-*.md`.
4. Minta persetujuan saya, lalu jalankan `git add -A` dan `git commit`.
5. Ingatkan saya untuk menjalankan `/clear` sebelum tahap berikutnya.
