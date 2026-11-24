# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## Sistem Informasi Manajemen Perkebunan Durian

- Versi dokumen: 1.0 (draft kerja)
- Tanggal: 24 November 2026
- Status: Draft berdasarkan kebutuhan proyek yang telah dibahas
- Pemilik produk: Perlu ditetapkan
- Platform: Web application, REST API, Flutter mobile application

## 1. Ringkasan Produk

Sistem Informasi Manajemen Perkebunan Durian adalah aplikasi terintegrasi untuk mengelola data organisasi perkebunan, wilayah, kebun, blok, pohon durian, petani, pemilik kebun, aktivitas pemeliharaan, perkembangan tanaman, dan hasil panen. Sistem menyediakan dashboard web untuk administrator serta aplikasi mobile untuk petani dan petugas lapangan. Aplikasi mobile dirancang dengan pendekatan offline-first agar pencatatan lapangan tetap dapat dilakukan ketika koneksi internet terbatas.

## 2. Masalah yang Ingin Diselesaikan

1. Data kebun, blok, pohon, pemilik, dan petani perlu dikelola secara terpusat.
2. Riwayat pengukuran dan aktivitas setiap pohon perlu terdokumentasi.
3. Pencatatan pemupukan, penyemprotan, pembungaan, perkembangan buah, dan panen perlu konsisten.
4. Data lapangan perlu dapat dikumpulkan melalui perangkat mobile.
5. Pengelola membutuhkan ringkasan dan laporan untuk memantau kondisi perkebunan.
6. Data antarorganisasi harus terisolasi.
7. Foto dokumentasi dan proses sinkronisasi perlu dikelola dengan aman.

## 3. Tujuan Produk

- Menyediakan sumber data perkebunan yang terpusat dan konsisten.
- Memudahkan pengelolaan struktur wilayah: desa, blok, kebun, dan pohon.
- Menyediakan riwayat lengkap perkembangan dan aktivitas pohon.
- Memudahkan petani mencatat kegiatan melalui aplikasi mobile.
- Menyediakan laporan aktivitas dan hasil panen.
- Mendukung pencatatan offline dan sinkronisasi saat jaringan tersedia.
- Menjaga keamanan, auditabilitas, dan isolasi data organisasi.

## 4. Bukan Tujuan / Di Luar Cakupan Awal

Fitur berikut tidak termasuk rilis awal kecuali disetujui sebagai perubahan ruang lingkup:
- Akuntansi dan pembukuan keuangan lengkap.
- Penggajian dan payroll.
- Marketplace dan penjualan online.
- Manajemen biaya operasional secara menyeluruh.
- Sistem inventori komersial lengkap.
- Prediksi hasil panen berbasis AI.
- Pengendalian perangkat IoT secara operasional.
- Otomasi penyiraman atau perangkat fisik.

Fondasi tabel monitoring area, perangkat IoT, dan telemetry telah direncanakan/disiapkan untuk pengembangan lanjutan, bukan berarti integrasi IoT sudah aktif.

## 5. Pengguna dan Peran

| Peran | Tanggung jawab utama |
|---|---|
| Super Admin | Mengelola konfigurasi lintas organisasi sesuai kebijakan sistem |
| Admin Organisasi | Mengelola pengguna dan data dalam organisasinya |
| Pengelola/Manajer | Memantau kebun, aktivitas, dan laporan sesuai izin |
| Petani/Petugas Lapangan | Melakukan pencatatan kegiatan pada area yang ditugaskan |
| Pemilik Kebun | Menjadi pihak pemilik satu atau beberapa kebun; akses aplikasi mengikuti akun dan izin yang diberikan |
| Pengunjung Publik | Melihat informasi terbatas melalui halaman QR pohon |

Role dan permission merupakan mekanisme otorisasi. Status sebagai petani, pemilik, atau penugasan ke blok/kebun adalah hubungan bisnis yang terpisah dari role aplikasi.

## 6. Ruang Lingkup Fitur

### 6.1 Organisasi dan pengguna
- Mendukung lebih dari satu organisasi.
- Mengelola akun pengguna, status aktif, role, dan permission.
- Memastikan pengguna hanya dapat mengakses data organisasi yang berhak diakses.
- Menyediakan audit untuk perubahan data penting.

### 6.2 Wilayah dan kebun
- Mengelola desa.
- Mengelola blok yang berada dalam desa.
- Mengelola kebun dalam blok.
- Mengelola pemilik kebun dengan relasi banyak-ke-banyak.
- Mengelola petani dan penugasannya ke blok dan/atau kebun.
- Memastikan satu kebun dapat memiliki lebih dari satu pemilik dan satu pemilik dapat memiliki lebih dari satu kebun.

### 6.3 Pohon durian
- Setiap pohon memiliki identitas unik UUID.
- Pohon terhubung langsung ke satu kebun.
- Data pohon meliputi varietas, tanggal tanam, koordinat, status, dan foto.
- Pohon tidak dihubungkan langsung ke blok; hubungan blok diperoleh melalui kebun.
- Menyediakan QR Code untuk membuka halaman publik dengan informasi yang dibatasi.

### 6.4 Pengukuran pohon
- Menyimpan riwayat pengukuran, bukan hanya nilai terbaru.
- Data dapat mencakup diameter batang, tinggi pohon, lebar tajuk, tanggal pengukuran, petugas, catatan, dan foto.
- Pengukuran baru tidak boleh menghapus riwayat sebelumnya.

### 6.5 Aktivitas perkebunan
- Pencatatan pemupukan dengan satu atau lebih produk.
- Pencatatan penyemprotan dengan satu atau lebih produk.
- Pencatatan pembungaan.
- Pencatatan perkembangan buah.
- Dokumentasi foto aktivitas.
- Aktivitas dapat dikaitkan dengan pohon atau cakupan area sesuai rancangan data dan aturan bisnis.

### 6.6 Panen
- Pengelolaan musim panen.
- Pengelolaan sesi panen pada kebun.
- Pencatatan hasil panen per pohon.
- Data minimal meliputi jumlah buah dan berat total.
- Detail mutu/grade bersifat opsional jika diaktifkan.
- Data panen harus konsisten dengan kebun dan sesi panen terkait.

### 6.7 Cuaca dan IoT
- Menyimpan data cuaca dari penyedia API eksternal yang akan ditentukan.
- Menyimpan data pemantauan area dan telemetry perangkat sebagai fondasi.
- Integrasi IoT operasional ditunda sampai perangkat, protokol, dan kebutuhan ditetapkan.

### 6.8 Aplikasi mobile
- Flutter/Dart.
- SQLite lokal untuk penyimpanan offline.
- Login dan pengambilan data sesuai penugasan.
- Form aktivitas lapangan.
- Foto dan QR scanner.
- Antrean sinkronisasi push/pull.
- Status data lokal: menunggu, tersinkron, gagal, atau konflik.

## 7. Kebutuhan Dashboard dan Laporan

Dashboard dapat menampilkan ringkasan sesuai role dan organisasi, misalnya jumlah kebun, blok, pohon, petani, aktivitas terbaru, status kegiatan, ringkasan musim panen, dan hasil panen. Data harus difilter berdasarkan izin dan organisasi.

Laporan awal:
- Data kebun dan pohon.
- Riwayat pengukuran pohon.
- Aktivitas pemeliharaan.
- Perkembangan tanaman.
- Hasil panen per musim, sesi, kebun, dan pohon.
- Aktivitas per petani jika data penugasan tersedia.

## 8. Prinsip Produk

- Mobile-first untuk kegiatan lapangan.
- Web-first untuk administrasi dan pengawasan.
- API sebagai lapisan layanan bersama.
- Offline-first pada mobile.
- Data historis tidak ditimpa tanpa jejak.
- Multi-tenant dengan isolasi organisasi.
- Keamanan ditegakkan di backend, bukan hanya UI.
- UX sederhana dan menghindari information overload.

## 9. Indikator Keberhasilan

- Pengguna dapat menyelesaikan alur utama tanpa bantuan teknis berulang.
- Data pohon dan aktivitas dapat ditelusuri berdasarkan kebun dan pohon.
- Tidak terjadi akses lintas organisasi yang tidak sah.
- Pencatatan mobile dapat dilakukan offline dan disinkronkan dengan benar.
- Laporan menghasilkan angka yang konsisten dengan data sumber.
- Target performa API P95 ≤ 800 ms dapat dicapai pada endpoint dan beban uji yang telah disepakati. Target ini harus dibuktikan melalui pengujian, bukan diasumsikan.
- Tidak ada regresi kritis setelah rilis.

## 10. Teknologi yang Ditetapkan

| Lapisan | Teknologi |
|---|---|
| Backend | Laravel 13.43.0 |
| Bahasa backend | PHP 8.3.14 |
| Database | MariaDB 10.4.28, InnoDB, utf8mb4 |
| Admin dashboard | Laravel Filament |
| Website publik | Laravel Blade |
| REST API | Laravel, prefix `/api/v1` |
| API authentication | Laravel Sanctum |
| Mobile | Flutter / Dart |
| Offline database | SQLite |
| Cache | Laravel Cache; Redis dapat dievaluasi |
| File storage | Laravel filesystem; object storage dapat dievaluasi |
| Version control | Git |

Versi aktual pada repository harus diverifikasi sebelum perubahan dependency.

## 11. Asumsi dan Hal yang Perlu Dikonfirmasi

1. Penyedia API cuaca belum ditetapkan.
2. Domain dan topologi deployment final belum ditetapkan.
3. Batas ukuran dan retensi foto perlu ditentukan.
4. Kebijakan konflik sinkronisasi perlu disepakati berdasarkan jenis data.
5. Definisi SLA perlu menetapkan endpoint, beban, lingkungan, dan periode pengukuran.
6. Detail kolom database harus disalin dari skema aktual, bukan dibuat berdasarkan perkiraan.
7. Hak akses pemilik kebun pada aplikasi perlu dipastikan: apakah hanya data publik/laporan atau juga login khusus.

## 12. Kriteria Penerimaan Produk

Produk dapat diterima jika:
- Seluruh alur utama yang disepakati berfungsi.
- Hak akses dan isolasi organisasi lolos pengujian.
- Riwayat pengukuran dan aktivitas tersimpan dengan benar.
- Hasil panen terhubung ke sesi, kebun, dan pohon yang tepat.
- Upload foto aman dan dapat ditampilkan kembali.
- Aplikasi mobile dapat menyimpan data offline dan menyinkronkan data.
- Dokumentasi API, database, arsitektur, dan pengujian tersedia.
