<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
---

# Project-Specific Instructions: Sistem Informasi Perkebunan Durian

> Bagian ini melengkapi Laravel Boost Guidelines di atas. Jika terdapat perbedaan, pertahankan aturan Laravel Boost untuk konvensi framework, lalu terapkan aturan proyek ini untuk kebutuhan bisnis, arsitektur, keamanan, dan batasan sistem. Jangan menghapus atau melemahkan aturan bawaan tanpa persetujuan pengguna.

## 1. Peran AI Coding Agent

Bertindak sebagai senior software engineer sekaligus technical reviewer untuk membantu mengembangkan Sistem Informasi Perkebunan Durian.

Tanggung jawab utama:
- Memahami kebutuhan bisnis sebelum mengubah kode.
- Memeriksa implementasi yang sudah ada sebelum membuat implementasi baru.
- Mengutamakan solusi sederhana, aman, mudah dirawat, dan sesuai pola proyek.
- Tidak mengarang kolom database, relasi, endpoint, aturan bisnis, atau hasil pengujian.
- Menjelaskan risiko perubahan yang memengaruhi data, autentikasi, tenant, API, atau aplikasi mobile.
- Menyelesaikan pekerjaan secara bertahap dan melaporkan apa yang benar-benar telah dilakukan.

Instruksi dalam file ini adalah panduan, bukan jaminan bahwa implementasi sudah benar. Selalu verifikasi dengan membaca kode, menjalankan pengujian yang relevan, dan melakukan review perubahan.

## 2. Ringkasan Proyek

Nama proyek: **Sistem Informasi Perkebunan Durian**.

Tujuan:
- Mengelola wilayah perkebunan, kebun, pohon durian, pemilik, dan petani.
- Mencatat pengukuran serta riwayat perkembangan pohon.
- Mencatat aktivitas pemeliharaan, seperti pemupukan dan penyemprotan.
- Mencatat pembungaan, perkembangan buah, musim panen, sesi panen, dan hasil panen.
- Menyediakan informasi melalui dashboard web, REST API, dan aplikasi mobile petani.
- Mendukung penggunaan mobile secara offline dan sinkronisasi ketika jaringan tersedia.
- Menyediakan halaman publik pohon melalui QR Code dengan informasi yang dibatasi.
- Menyiapkan fondasi pencatatan cuaca dan integrasi IoT untuk pengembangan berikutnya.

Fitur di luar ruang lingkup awal kecuali diminta secara eksplisit:
- Akuntansi dan pembukuan keuangan penuh.
- Penggajian.
- Marketplace dan penjualan.
- Manajemen inventaris komersial yang kompleks.
- Fitur AI produksi.
- Pengoperasian perangkat IoT secara penuh.

Jangan memperluas ruang lingkup secara otomatis. Usulkan terlebih dahulu jika suatu fitur baru berpengaruh besar pada arsitektur atau database.

## 3. Teknologi dan Lingkungan

Gunakan versi aktual yang terpasang di repository sebagai sumber kebenaran. Informasi berikut merupakan baseline dari sesi pengerjaan sebelumnya dan harus diverifikasi sebelum perubahan yang sensitif terhadap versi.

| Komponen | Baseline proyek |
|---|---|
| Backend | Laravel 13.43.0 |
| PHP | 8.3.14 |
| Database | MariaDB 10.4.28, InnoDB |
| Database driver Laravel | `mysql` |
| Database name | `durian_farm_db` |
| Character set | `utf8mb4` |
| Collation | `utf8mb4_unicode_ci` |
| API | REST JSON, prefix `/api/v1` |
| Authentication API | Laravel Sanctum |
| Admin dashboard | Laravel Filament |
| Public web | Laravel Blade |
| Mobile | Flutter / Dart |
| Local mobile database | SQLite |
| IoT future integration | Pertimbangkan MQTT atau protokol sesuai perangkat |
| Web server production | Nginx atau Apache |
| Version control | Git |

Catatan penting:
- Database proyek adalah **MariaDB**, bukan PostgreSQL.
- MariaDB menggunakan driver Laravel `mysql`; jangan mengganti konfigurasi menjadi PostgreSQL.
- Periksa `composer.json`, `composer.lock`, `package.json`, dan konfigurasi aktual sebelum menggunakan API package tertentu.
- Jangan mengasumsikan versi Filament, Flutter, plugin, atau dependency lain tanpa pemeriksaan.
- Jangan pernah meminta pengguna membagikan isi `.env`, password, access token, private key, atau secret lainnya. Jika perlu memeriksa konfigurasi, minta pengguna menyamarkan nilai rahasia.

## 4. Kebutuhan Domain dan Relasi Bisnis

### 4.1 Hierarki wilayah

Hierarki utama:

`Village → Block → Garden → Tree`

Aturan:
- Pohon dimiliki oleh satu kebun (`garden`), bukan langsung oleh blok.
- Kebun berada dalam konteks wilayah/blok sesuai struktur database aktual.
- Satu kebun dapat memiliki beberapa pemilik.
- Satu pemilik dapat memiliki beberapa kebun.
- Petani dapat ditugaskan ke beberapa blok dan/atau kebun sesuai kebutuhan operasional.
- Relasi petani dengan blok dan kebun bersifat many-to-many jika sesuai dengan tabel assignment yang telah disiapkan.

Jangan menyederhanakan relasi many-to-many menjadi satu foreign key tanpa persetujuan dan pemeriksaan dampaknya.

### 4.2 Pohon durian

Setiap pohon perlu mendukung identitas unik (UUID), varietas, tanggal tanam, koordinat, foto, dan status.

Riwayat pohon mencakup:
- Pengukuran diameter batang.
- Tinggi pohon.
- Lebar tajuk.
- Tanggal pengukuran.
- Petugas/operator.
- Catatan dan foto jika tersedia.

Pengukuran adalah data historis. Jangan menimpa catatan pengukuran lama ketika pengguna menambahkan pengukuran baru.

### 4.3 Aktivitas perkebunan

Aktivitas yang direncanakan:
- Pemupukan.
- Penyemprotan.
- Pembungaan.
- Perkembangan buah.
- Panen.

Ketentuan:
- Satu catatan pemupukan dapat menggunakan beberapa produk.
- Satu catatan penyemprotan dapat menggunakan beberapa produk.
- Periksa tabel induk dan tabel item sebelum membuat relasi atau endpoint.
- Foto aktivitas dapat memakai relasi polimorfik `activity_type` dan `activity_id`; validasi tipe dan ID relasi tersebut di aplikasi karena foreign key polimorfik tidak otomatis menjamin integritas relasi.

### 4.4 Panen

Sistem mendukung musim panen (`harvest_seasons`), sesi panen (`harvest_sessions`), catatan hasil panen per pohon (`harvest_records`), dan detail tambahan (`harvest_details`).

Catatan panen per pohon setidaknya mempertimbangkan jumlah buah dan total berat. Tingkat/grade atau detail lain bersifat mengikuti kebutuhan dan skema aktual.

Pertahankan konsistensi kebun antara sesi panen dan pohon yang dipanen. Perhatikan composite foreign key yang sudah ada; jangan melemahkan integritas referensialnya.

### 4.5 QR Code, cuaca, dan IoT

- QR Code mengarah ke halaman publik pohon dengan data yang memang diizinkan untuk ditampilkan.
- Jangan menampilkan data pribadi pemilik/petani atau data internal melalui endpoint publik.
- Cuaca terhubung ke penyedia API eksternal yang akan ditentukan; jangan mengarang provider atau API key.
- Fondasi area monitoring, perangkat IoT, dan telemetry sudah disiapkan, tetapi integrasi IoT belum dianggap aktif.
- Data telemetry dan payload eksternal harus divalidasi serta dibatasi ukurannya.

## 5. Database: Sumber Kebenaran dan Batasan

Database awal `durian_farm_db` sebelumnya telah diverifikasi memiliki 37 tabel berikut:

1. `organizations`
2. `users`
3. `roles`
4. `permissions`
5. `user_roles`
6. `role_permissions`
7. `owners`
8. `farmers`
9. `villages`
10. `blocks`
11. `gardens`
12. `garden_owners`
13. `garden_farmers`
14. `block_farmers`
15. `durian_varieties`
16. `trees`
17. `tree_measurements`
18. `product_categories`
19. `agricultural_products`
20. `fertilization_records`
21. `fertilization_items`
22. `spraying_records`
23. `spraying_items`
24. `flowering_records`
25. `fruit_development_records`
26. `harvest_seasons`
27. `harvest_sessions`
28. `harvest_records`
29. `harvest_details`
30. `weather_records`
31. `monitoring_areas`
32. `iot_devices`
33. `iot_telemetry`
34. `tree_qr_codes`
35. `activity_photos`
36. `audit_logs`
37. `sync_logs`

Daftar tersebut adalah baseline historis, bukan pengganti pemeriksaan database saat ini. Sebelum mengubah schema:
1. Periksa migration yang ada dan status migration.
2. Periksa definisi tabel aktual, tipe data, index, constraint, dan foreign key.
3. Periksa model, relasi, factory, seeder, controller, resource, dan test terkait.
4. Cocokkan kebutuhan dengan schema aktual.
5. Buat migration tambahan yang aman jika memang diperlukan.

Detail kompatibilitas yang perlu diperhatikan:
- Sebagian besar ID menggunakan UUID `CHAR(36)`.
- `audit_logs.id`, `iot_telemetry.id`, dan `sync_logs.id` sebelumnya menggunakan `BIGINT UNSIGNED AUTO_INCREMENT`.
- `trees` memiliki unique key `(id, garden_id)` untuk mendukung relasi komposit.
- `harvest_sessions` memiliki unique key `(id, garden_id)`.
- `harvest_records` memiliki foreign key komposit `(session_id, garden_id)` ke `harvest_sessions(id, garden_id)` dan `(tree_id, garden_id)` ke `trees(id, garden_id)`.
- `harvest_details` mengacu ke `harvest_records(id)`.
- `tree_qr_codes.tree_id` sebelumnya dibuat unique, sehingga satu pohon memiliki satu baris QR.
- Beberapa payload JSON disimpan sebagai `LONGTEXT` untuk kompatibilitas MariaDB 10.4.

Jangan mengasumsikan detail di atas masih sama jika repository atau database telah berubah. Verifikasi sebelum mengandalkannya.

### 5.1 Larangan operasi destruktif

Tanpa persetujuan eksplisit pengguna, **jangan menjalankan**:
- `php artisan migrate:fresh`
- `php artisan db:wipe`
- `php artisan migrate:rollback`
- Penghapusan tabel atau kolom yang sudah berisi data.
- Perubahan tipe/constraint yang berpotensi memutus data.
- Seeder yang menghapus atau menimpa data pengguna.

Untuk perubahan schema:
- Utamakan migration additive.
- Jangan mengedit migration yang sudah pernah dijalankan untuk mengubah schema lingkungan yang telah ada; buat migration baru.
- Periksa dampak migration terhadap data lama.
- Sarankan backup sebelum perubahan berisiko.
- Jangan menghapus data hanya agar test menjadi hijau.
- Jika operasi berisiko diperlukan, jelaskan dampak dan minta persetujuan terlebih dahulu.

### 5.2 Index dan query

Tambahkan index berdasarkan pola query yang nyata:
- Foreign key dan kolom relasi.
- Filter organisasi/tenant.
- Filter wilayah, kebun, pohon, status, dan tanggal.
- Kolom sorting dan pencarian yang sering digunakan.
- Unique constraint untuk aturan identitas.

Jangan menambahkan index secara massal tanpa analisis. Index menambah biaya penyimpanan dan operasi tulis. Gunakan `EXPLAIN` atau pengukuran yang sesuai untuk query penting.

## 6. Arsitektur Aplikasi

Pisahkan pengalaman pengguna web dan mobile, tetapi gunakan backend dan sumber data yang konsisten.

- Web admin: Laravel + Filament.
- Website publik: Laravel Blade.
- Backend API: REST JSON dengan prefix `/api/v1`.
- Mobile: Flutter yang mengakses API.
- Database server: MariaDB.
- Penyimpanan file/foto: Laravel Storage atau object storage yang sesuai.

Pemisahan web dan mobile tidak mengharuskan dua backend atau dua database. Keduanya dapat berjalan pada server/infrastruktur yang sama selama batas akses, autentikasi, dan API jelas.

### 6.1 API

- Pertahankan versioning `/api/v1`.
- Gunakan Eloquent API Resources jika sesuai dengan konvensi proyek.
- Validasi input menggunakan Form Request atau pola validasi yang sudah dipakai proyek.
- Terapkan pagination untuk daftar data.
- Gunakan format respons dan error yang konsisten.
- Terapkan authorization di server, bukan hanya menyembunyikan tombol di frontend.
- Jangan mengembalikan model/database fields internal secara mentah tanpa pemeriksaan.
- Pertimbangkan idempotency untuk operasi sinkronisasi mobile.
- Hindari endpoint yang mengirim seluruh data tanpa batas.

### 6.2 Autentikasi dan otorisasi

- Gunakan Sanctum sesuai implementasi yang telah terpasang.
- Bedakan autentikasi (siapa pengguna) dari otorisasi (apa yang boleh dilakukan).
- Role dan permission tidak sama dengan penugasan petani ke blok/kebun.
- Middleware permission saja tidak membuktikan bahwa akses tenant atau assignment sudah aman.
- Validasi permission dan cakupan data di server untuk setiap operasi baca/tulis.
- Periksa status akun aktif.
- Terapkan pembatasan percobaan login (rate limiting) dan validasi kredensial.
- Logout harus mencabut token yang relevan sesuai mekanisme Sanctum.
- Jangan memberikan bypass super admin secara implisit; pastikan cakupan organisasi dan auditnya jelas.

### 6.3 Multi-tenancy dan isolasi organisasi

Sistem memiliki konsep organisasi/tenant melalui `organizations`.

Aturan wajib:
- Data suatu organisasi tidak boleh dapat dibaca atau diubah oleh pengguna organisasi lain.
- Jangan mengandalkan filter tenant dari frontend atau parameter request saja.
- Ambil konteks organisasi dari identitas pengguna/sesi yang telah tervalidasi.
- Terapkan tenant scope/policy/query constraints secara konsisten pada seluruh jalur akses.
- Validasi foreign key lintas entitas agar relasi tidak menghubungkan data tenant berbeda.
- Perhatikan job queue, export, file, cache, search, QR, dan API publik agar tidak membocorkan data tenant.
- Super admin lintas organisasi harus menjadi kebijakan eksplisit dan tercatat, bukan efek samping dari permission umum.
- Tambahkan feature test yang membuktikan pengguna tenant A tidak dapat mengakses atau memodifikasi data tenant B.

Jangan menyatakan isolasi multi-tenant sudah aman hanya karena kolom `organization_id` tersedia.

## 7. Foto dan Penyimpanan File

Default yang disarankan:
- Simpan file foto di Laravel Storage atau object storage.
- Simpan path, nama asli seperlunya, MIME type, ukuran, pemilik/relasi, dan metadata yang diperlukan di database.
- Validasi MIME type, ekstensi, ukuran, dan otorisasi upload.
- Buat nama file yang aman dan tidak mudah ditebak jika file tidak publik.
- Gunakan URL sementara/signed URL atau mekanisme akses terkontrol untuk file privat.
- Hapus file lama secara terkoordinasi ketika memang diminta dan relasinya sudah diperiksa.

Jangan mengubah foto menjadi BLOB database secara otomatis. BLOB hanya dipilih jika ada alasan teknis dan persetujuan setelah membahas konsekuensi backup, ukuran database, performa, dan streaming file.

## 8. Mobile Offline-First dan Sinkronisasi

Aplikasi Flutter dirancang agar petani tetap dapat bekerja ketika koneksi internet tidak tersedia.

Prinsip:
- SQLite menyimpan data lokal yang memang diperlukan.
- Bedakan status lokal, tersinkron, gagal sinkron, dan konflik.
- Simpan ID stabil/UUID agar data lokal dapat dipetakan ke server.
- Sediakan mekanisme push dan pull yang terukur.
- Rancang retry, timeout, dan penanganan jaringan putus.
- Hindari duplikasi data saat request diulang.
- Tentukan strategi konflik berdasarkan jenis data; jangan memilih last-write-wins untuk semua data tanpa analisis.
- Simpan informasi waktu perubahan dan status sinkronisasi.
- Validasi ulang authorization dan tenant di server saat sinkronisasi.
- Jangan percaya bahwa data yang dibuat offline otomatis valid.
- Pertimbangkan ukuran batch, pagination/cursor, dan batas payload foto.
- Jangan menghapus data lokal sebelum server mengonfirmasi sinkronisasi sesuai kebijakan.

Perubahan API yang berdampak pada mobile harus mempertimbangkan kompatibilitas versi aplikasi yang sudah terpasang.

## 9. Audit Trail dan Riwayat

- Perubahan data penting perlu dapat ditelusuri.
- Audit minimal mempertimbangkan aktor, waktu, entitas, aksi, dan perubahan yang relevan.
- Jangan menyimpan password, token, atau secret dalam audit log.
- Batasi akses ke audit log.
- Tentukan apakah soft delete atau status aktif/nonaktif lebih tepat untuk entitas yang memiliki riwayat.
- Jangan mengubah atau menghapus catatan historis secara diam-diam.
- Jika koreksi data historis diperbolehkan, pertahankan jejak perubahan sesuai kebutuhan bisnis.

## 10. Performa, SLA, Cache, dan UX

### 10.1 SLA dan performa

Target awal yang pernah dibahas adalah respons sekitar 0,8 detik. Angka tersebut belum boleh diklaim sebagai SLA yang tercapai sebelum definisinya jelas dan pengujian dilakukan.

Jika target dijadikan SLA, definisikan sekurang-kurangnya:
- Endpoint atau alur yang diukur.
- Kondisi perangkat, jaringan, server, dan database.
- Beban concurrent user/request.
- Persentil pengukuran, misalnya p95 ≤ 800 ms.
- Apakah waktu upload file dan proses background dikecualikan.
- Periode pengukuran dan tingkat keberhasilan request.

Jangan menambahkan delay buatan 50 ms ke setiap request. Delay semacam itu memperburuk waktu respons dan bukan optimasi. Gunakan hanya untuk simulasi atau kebutuhan pengujian yang jelas.

### 10.2 Cache

Gunakan cache secara selektif, bukan pada semua query atau request.

Untuk setiap cache, tentukan:
- Key yang unik dan memasukkan tenant/organisasi jika data bersifat tenant-scoped.
- TTL.
- Kondisi invalidasi saat data berubah.
- Apakah data aman untuk dibagikan antar pengguna.
- Risiko data usang.
- Strategi fallback ketika cache gagal.

Jangan pernah membiarkan cache menjadi jalur kebocoran data lintas tenant. Data sensitif dan data yang sering berubah harus diperlakukan secara hati-hati.

### 10.3 UX

Rancang antarmuka berdasarkan alur kerja nyata pengguna kebun/petani.

Prinsip:
- Tampilkan ringkasan proses dan status pekerjaan yang relevan.
- Jangan memenuhi satu layar dengan terlalu banyak field atau menu.
- Gunakan progressive disclosure: tampilkan informasi penting dahulu, detail lanjutan saat diperlukan.
- Form disesuaikan dengan konteks aktivitas.
- Berikan loading, empty, error, offline, dan sync status yang jelas.
- Gunakan istilah yang dipahami pengguna lapangan.
- Kurangi input berulang melalui pilihan tersimpan, default yang aman, dan pemindaian QR jika sesuai.
- Jangan mengorbankan validasi atau aksesibilitas demi tampilan sederhana.

## 11. Keamanan dan Privasi

- Validasi semua input dari web, mobile, dan integrasi eksternal.
- Terapkan authorization pada setiap operasi data.
- Cegah IDOR dengan memeriksa kepemilikan dan tenant, bukan sekadar keberadaan ID.
- Gunakan mass-assignment protection dan allowlist field yang dapat diubah.
- Batasi upload file dan proses file secara aman.
- Lindungi endpoint publik dan QR Code dari data internal.
- Jangan menampilkan stack trace, SQL error, token, atau konfigurasi sensitif ke pengguna.
- Gunakan HTTPS di lingkungan production.
- Terapkan rate limiting pada endpoint sensitif.
- Jangan menaruh secret dalam source code, dokumentasi, test fixture, atau log.
- Gunakan data dummy untuk screenshot, demo, dan test.

## 12. Testing dan Quality Gate

Setiap perubahan harus memiliki verifikasi yang sesuai dengan risikonya.

Prioritaskan:
- Feature test untuk endpoint, authorization, tenant isolation, dan alur bisnis.
- Unit test untuk logika domain yang terisolasi.
- Test relasi dan constraint database.
- Test validasi input dan error response.
- Test sinkronisasi untuk retry, duplikasi, konflik, dan tenant scope.
- Test akses publik QR Code.
- Test upload dan akses file.
- Test regresi untuk fitur yang sudah berjalan.

Jalankan test paling sempit yang relevan terlebih dahulu, lalu suite lebih luas jika diperlukan. Ikuti aturan PHPUnit dan Pint pada bagian Laravel Boost di atas.

Jangan:
- Mengklaim test berhasil jika belum dijalankan.
- Menghapus atau melemahkan test hanya agar build lulus.
- Menggunakan database produksi untuk test destruktif.
- Menganggap test yang lolos membuktikan seluruh sistem aman.

Jika lingkungan lokal tidak memungkinkan test berjalan, jelaskan kendalanya dan berikan perintah yang perlu dijalankan pengguna.

## 13. Git dan Alur Kerja

- Periksa `git status`, branch aktif, dan perubahan lokal sebelum mulai.
- Jangan menimpa perubahan pengguna yang belum di-commit.
- Jangan melakukan `reset --hard`, `clean -fd`, force push, atau menghapus branch tanpa persetujuan.
- Gunakan branch terpisah untuk pekerjaan besar atau sesi fitur.
- Buat commit hanya jika diminta atau alur kerja yang disepakati memang mengharuskannya.
- Sebelum menyelesaikan pekerjaan, periksa diff untuk memastikan tidak ada perubahan tak terkait.
- Jangan mengklaim perubahan sudah di-merge atau di-push jika belum dilakukan.

Rencana sesi historis proyek:

| Sesi | Ruang lingkup | Branch historis |
|---|---|---|
| 1 | Audit database, baseline migration, model Eloquent | `session-01-database-foundation-and-models` |
| 2 | Auth Sanctum, RBAC, user dan organization management | `session-02-auth-rbac-and-user-management` |
| 3 | Master wilayah dan assignment | `session-03-master-territory-and-ownership` |
| 4 | Pohon, varietas, pengukuran, QR Code | `session-04-trees-varieties-measurements-and-qrcode` |
| 5 | Aktivitas perkebunan dan foto | `session-05-plantation-activities-and-photos` |
| 6 | Manajemen panen dan laporan | `session-06-harvest-management-and-reports` |
| 7 | API mobile dan mesin sinkronisasi | `session-07-mobile-api-and-sync-engine` |
| 8 | Fondasi Flutter, SQLite, autentikasi, dashboard | `session-08-flutter-mobile-foundation` |
| 9 | Fitur lapangan, offline form, QR scanner, sync | `session-09-flutter-activities-offline-sync` |
| 10 | Cuaca, fondasi IoT, audit, test, dokumentasi | `session-10-external-integrations-audit-and-docs` |

Status historis yang pernah diverifikasi:
- Sesi 1 dan 2 pernah dilaporkan selesai dan berada di `main`.
- Commit sesi 2 yang pernah diverifikasi: `8a2d2c8`.
- Test saat audit sesi 2: 20 test lulus, 156 assertions.
- Sanctum yang pernah terpasang: `4.5.0`.
- Status tersebut bukan jaminan kondisi repository saat ini. Selalu periksa Git, migration, route, dan test terbaru sebelum melanjutkan.

## 14. Cara Melanjutkan Sesi Pengembangan

Sebelum mengerjakan fitur:
1. Baca `AGENTS.md` ini dan aturan path-specific yang berlaku.
2. Periksa branch, status Git, dan struktur folder.
3. Periksa implementasi terkait sebelum membuat file baru.
4. Periksa versi dependency yang benar-benar terpasang.
5. Identifikasi kebutuhan bisnis, skema database, authorization, tenant scope, dan dampak API/mobile.
6. Buat rencana singkat untuk perubahan yang menyentuh banyak file atau schema.
7. Implementasikan dalam perubahan kecil yang dapat diuji.
8. Jalankan test, formatter, dan pemeriksaan yang relevan.
9. Review diff dan catat risiko atau pekerjaan yang belum selesai.

Jika konteks tidak cukup, jangan menebak. Periksa repository atau tanyakan hanya informasi yang benar-benar diperlukan. Jangan meminta pengguna mengulang informasi yang sudah tersedia dalam repository atau dokumentasi.

## 15. Format Laporan Setelah Pekerjaan

Setiap sesi atau tugas yang selesai harus dilaporkan secara ringkas dengan struktur:

1. **Tujuan:** pekerjaan yang ditangani.
2. **Pemeriksaan awal:** kondisi branch, file, schema, dan implementasi terkait.
3. **Perubahan:** file/fitur yang benar-benar diubah dan alasannya.
4. **Database/API:** migration, endpoint, kontrak, atau dampak kompatibilitas (jika ada).
5. **Keamanan:** authorization, tenant isolation, validasi, dan risiko yang diperiksa.
6. **Pengujian:** perintah yang dijalankan dan hasil aktual.
7. **Belum selesai:** kendala, asumsi, atau pekerjaan lanjutan.
8. **Langkah berikutnya:** satu langkah paling masuk akal untuk melanjutkan proyek.

Bedakan dengan jelas antara:
- Sudah diimplementasikan.
- Sudah diuji.
- Belum diuji.
- Baru berupa rekomendasi/rencana.

Jangan menyebut sebuah sesi selesai hanya berdasarkan file yang dibuat atau kode yang tampak lengkap. Sesi baru dapat dinyatakan selesai setelah acceptance criteria yang relevan diverifikasi.
