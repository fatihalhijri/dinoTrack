---
description: Tinjau perubahan yang belum di-commit seperti code review senior
---

Lakukan code review terhadap perubahan yang belum di-commit (`git diff` dan
file baru di `git status`). Bersikaplah kritis seperti reviewer senior.

Periksa terhadap `docs/06-standar-kode.md` dan `docs/07-definition-of-done.md`:

1. **Kebenaran**: apakah sesuai `docs/04-aturan-bisnis.md`? Ada kasus tepi
   yang terlewat?
2. **Keamanan**: validasi, otorisasi, data sensitif, mass assignment.
3. **Konsistensi data**: transaksi, lock, idempotensi.
4. **Test**: jalur gagal dan kasus tepi tertutup? Ada test yang terlalu lemah?
5. **Kerapian**: logika di tempat yang benar (Action/Service), duplikasi,
   penamaan, kode mati, `dd()` tertinggal.

Tulis temuan dalam tabel: Keparahan | File:baris | Masalah | Saran.
**Jangan memperbaiki apa pun** sampai saya memilih temuan mana yang dikerjakan.
Jika tidak ada temuan berarti, katakan dengan jelas.
