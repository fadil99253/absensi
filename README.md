# HadirKu

Aplikasi absensi karyawan dengan frontend HTML/CSS/JavaScript dan backend Laravel 12. Frontend dilayani melalui Laravel pada origin yang sama, sehingga API dan aset aplikasi tidak perlu konfigurasi CORS.

## Struktur folder

```text
projek.absensi.wajah/
|-- backend/       # Aplikasi Laravel: API, database, migrasi, dan pengujian
|-- frontend/      # Tampilan: HTML, CSS, dan JavaScript
`-- README.md
```

File tampilan tetap berada di `frontend/`. Route Laravel di `backend/routes/web.php` menyajikan file tersebut, jadi folder frontend tidak perlu dipindah atau dicampur ke dalam kode backend.

## Persyaratan

- PHP 8.2 atau lebih baru dengan ekstensi `pdo_sqlite` atau `pdo_mysql` (sesuai database yang dipilih), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, dan `fileinfo` aktif.
- Composer 2.

## Menjalankan aplikasi

Jalankan perintah berikut di PowerShell dari direktori utama proyek:

```powershell
Set-Location .\backend
composer install
Copy-Item .env.example .env
php artisan key:generate
if (!(Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File }
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8001
```

Buka `http://127.0.0.1:8001`. Database SQLite berada di `backend/database/database.sqlite`.

Jika `php` belum dikenali PowerShell setelah instalasi PHP, tutup dan buka ulang terminal, atau jalankan `php.exe` dengan path instalasi PHP.

Jalankan pengujian backend dengan `php artisan test` dari direktori `backend`.

## Menggunakan MySQL

Ada dua cara menyiapkan tabel MySQL:

1. **Impor berkas SQL:** gunakan [backend/database/mysql/hadirku.sql](./backend/database/mysql/hadirku.sql) melalui phpMyAdmin atau MySQL Workbench. Berkas ini membuat database `hadirku`, semua tabel aplikasi, akun demo, dan enam karyawan demo. Berkas ini juga menandai migrasi sebagai sudah dijalankan.
2. **Migrasi Laravel:** buat database dan pengguna seperti di bawah, atur `.env`, lalu jalankan `php artisan migrate --seed`.

Untuk menyiapkan pengguna database khusus aplikasi, jalankan SQL berikut pada MySQL, phpMyAdmin, atau MySQL Workbench:

```sql
CREATE DATABASE hadirku
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'hadirku_user'@'127.0.0.1' IDENTIFIED BY 'ganti_dengan_password_kuat';
GRANT ALL PRIVILEGES ON hadirku.* TO 'hadirku_user'@'127.0.0.1';
```

Pastikan ekstensi PHP `pdo_mysql` aktif. Dari PowerShell di direktori utama proyek, salin contoh konfigurasi lalu ubah bagian database di `backend/.env` agar sesuai kredensial MySQL:

```powershell
Set-Location .\backend
if (!(Test-Path .env)) { Copy-Item .env.example .env }
```

Atur nilai berikut di `backend/.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hadirku
DB_USERNAME=hadirku_user
DB_PASSWORD=ganti_dengan_password_kuat
```

Jika memakai migrasi Laravel, buat kunci aplikasi dan jalankan migrasi serta seeder. Jika sudah mengimpor berkas SQL, tabel dan data demo sudah tersedia; cukup bersihkan cache konfigurasi sebelum memulai server:

```powershell
php artisan config:clear
```

Untuk pemasangan baru dengan migrasi Laravel:

```powershell
php artisan key:generate
php artisan migrate --seed
```

Mulai server:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

SQLite tetap menjadi konfigurasi bawaan; petunjuk di atas mengalihkan aplikasi ke MySQL. Migrasi adalah sumber skema database, jadi tabel tidak perlu dibuat atau diimpor terpisah.

## Akun demo

- Email: `admin@hadirku.id`
- Kata sandi: `hadirku123`

Akun dan enam karyawan demo dibuat oleh seeder. Untuk mengulang seeding data demo tanpa menghapus catatan presensi yang ada, jalankan `php artisan db:seed`.

## Fitur

- Ringkasan kehadiran, keterlambatan, karyawan belum hadir, dan total karyawan.
- Pencarian serta filter tanggal riwayat presensi, termasuk ekspor CSV.
- Pendaftaran karyawan baru dan penyimpanan data melalui Eloquent.
- Pencatatan presensi masuk/pulang dengan validasi urutan dan duplikasi.
- Token API Laravel Sanctum, pembatasan percobaan login, dan pencabutan token saat keluar.
- Akses kamera browser untuk mengambil foto sebelum mengonfirmasi presensi.
- Migrasi database dan pengujian API melalui PHPUnit.

> Catatan: aplikasi ini masih MVP. Foto kamera hanya diproses sementara di browser dan tidak disimpan. Pencocokan biometrik wajah belum diimplementasikan; konfirmasi kamera bukan verifikasi identitas. Semua akun demo saat ini memiliki hak akses administrator yang sama; tambahkan role/otorisasi sebelum digunakan di produksi.
