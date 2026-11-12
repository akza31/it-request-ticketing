# IT Program Request & Ticketing Management — SQLite Edition

Standalone MVP — RSU Islam Klaten, Divisi Teknologi Informasi.

## Stack
- PHP Native 8.1+
- SQLite melalui PDO
- Bootstrap 5 (CDN)
- mPDF
- Local/private file storage

## Kebutuhan PHP
Aktifkan ekstensi `pdo_sqlite`, `sqlite3`, `mbstring`, dan `gd` pada `php.ini` XAMPP. Periksa dengan `php -m`.

## Notifikasi Telegram
Setelah membuat bot melalui BotFather, isi `bot_token`, `it_staff_chat_id`, dan `it_head_chat_id` pada `app/config/config.php`. Staff IT dan Kadiv IT perlu membuka percakapan bot dan menekan **Start** terlebih dahulu; masukkan chat ID masing-masing (bisa berupa ID grup jika bot sudah ditambahkan ke grup). Simpan token secara rahasia. Notifikasi dikirim setelah permohonan baru berhasil disimpan; jika Telegram tidak tersedia, permohonan tetap tersimpan.

## Instalasi XAMPP
1. Ekstrak folder `IT_Request_Ticketing` ke `C:\xampp\htdocs\it-request`.
2. File `app/config/config.php` dan database SQLite sudah disertakan. Sesuaikan `base_url` bila nama folder berbeda.
3. Jalankan `composer install` dari folder aplikasi untuk memasang mPDF.
4. Pastikan `storage/` dapat ditulis PHP dan tidak dapat diakses langsung dari web.
5. Buat akun SUPERADMIN pertama melalui CMD (hanya untuk bootstrap awal):
   `php scripts/create_admin.php admin "Administrator IT" PasswordKuat123!`
   Ganti dengan password unik minimal 12 karakter.
6. Buka `http://localhost/it-request/public/login.php`.
7. Login sebagai SUPERADMIN, buka menu **Direktorat & Unit** untuk membuat struktur organisasi.
8. Buka menu **Pengguna** untuk membuat akun UNIT, APPROVER, IT_HEAD, dan IT_STAFF. Setelah itu pengguna dapat login dari halaman yang sama.
9. Untuk database yang sudah ada, backup database terlebih dahulu lalu jalankan `php scripts/migrate_ticket_workflow.php` dari folder aplikasi. Setelah itu SUPERADMIN/IT_HEAD dapat mengisi direktori **Petugas IT**, menetapkan petugas pada tiket, dan mencatat progres hingga selesai dari menu **IT Ticketing**.

Jika database belum tersedia, jalankan `php scripts/init_db.php`. Script tidak menimpa database yang sudah ada.

## Alur MVP
UNIT membuat permohonan → sistem membuat nomor permohonan dan tiket `WAITING_APPROVAL` → PDF dapat diunduh → approver memberi persetujuan digital → IT menetapkan petugas dan memperbarui progres tiket sampai `COMPLETED` dengan catatan hasil pekerjaan.

## Catatan SQLite
- File database: `storage/database/it_request_ticketing.sqlite`.
- PDO SQLite, foreign keys, WAL, dan busy timeout diaktifkan.
- Jangan letakkan file SQLite di folder `public/`.
- Backup harus mencakup database dan dokumen di storage. Untuk backup konsisten gunakan SQLite Online Backup API atau hentikan aplikasi sebelum menyalin file.
- SQLite cocok untuk MVP atau beban tulis ringan–menengah pada satu server. Untuk beban tulis konkuren tinggi atau beberapa server aplikasi, pertimbangkan database server khusus.

## Keamanan dan batasan MVP
Password menggunakan `password_hash()` dan query menggunakan prepared statements. Persetujuan digital adalah persetujuan internal aplikasi, bukan tanda tangan elektronik tersertifikasi. Starter ini tetap perlu pengujian, penguatan otorisasi, dan penyempurnaan modul administrasi sebelum produksi.

## Modul administrasi yang tersedia
- **Pengguna** (khusus SUPERADMIN): tambah/edit akun, atur role dan unit, reset password dengan mengisi password baru, aktif/nonaktif akun.
- **Direktorat & Unit** (khusus SUPERADMIN): tambah/edit dan aktif/nonaktif direktorat maupun unit kerja.
- **Profil Saya**: melihat identitas akun dan mengganti password sendiri.
- Navigasi otomatis menampilkan menu administrasi hanya untuk SUPERADMIN.

### Aturan akun
- Role `UNIT` wajib dihubungkan ke satu unit kerja.
- Role `APPROVER` wajib dihubungkan ke salah satu unit pada direktorat yang menjadi kewenangannya. Sistem mengambil direktorat dari unit tersebut.
- Role `IT_HEAD`, `IT_STAFF`, dan `SUPERADMIN` boleh tidak terikat unit.
- Username unik; password awal/perubahan minimal 12 karakter.
- Akun dinonaktifkan tidak dapat login. Akun tidak dihapus permanen agar histori permohonan dan audit tetap utuh.
