# SOFTWARE REQUIREMENTS SPECIFICATION (SRS)
## Sistem Informasi Manajemen Perkebunan Durian

- Versi: 1.0 (draft kerja)
- Status: Berdasarkan kebutuhan proyek; perlu validasi terhadap implementasi aktual.

## 1. Pendahuluan

### 1.1 Tujuan
Dokumen ini menetapkan kebutuhan fungsional dan nonfungsional untuk sistem manajemen perkebunan durian berbasis web, REST API, dan mobile.

### 1.2 Ruang Lingkup
Sistem mengelola organisasi, pengguna, wilayah, kebun, pohon, pemilik, petani, pengukuran, aktivitas perkebunan, panen, cuaca, QR pohon, dokumentasi foto, dan sinkronisasi mobile.

### 1.3 Istilah
- Organisasi/tenant: batas kepemilikan dan akses data.
- Kebun: unit lahan yang berada di dalam blok.
- Pohon: tanaman durian yang menjadi objek pencatatan.
- Aktivitas: kegiatan pemeliharaan atau perkembangan tanaman.
- Offline-first: aplikasi mobile tetap dapat digunakan saat tidak terhubung ke internet.
- RBAC: Role-Based Access Control.
- P95: persentil ke-95 waktu respons.

## 2. Gambaran Sistem

Sistem terdiri atas:
1. Web admin menggunakan Filament.
2. Website publik menggunakan Blade.
3. Backend REST API `/api/v1`.
4. Database MariaDB.
5. Aplikasi Flutter dengan SQLite lokal.
6. Penyimpanan file foto.
7. Cache dan integrasi eksternal.

Semua akses data bisnis harus melalui aturan otorisasi backend.

## 3. Kebutuhan Fungsional

Prioritas: MUST = wajib untuk rilis inti; SHOULD = penting; COULD = tambahan.

### FR-001 — Manajemen organisasi (MUST)
Sistem harus dapat membuat, membaca, memperbarui, dan menonaktifkan/menghapus organisasi sesuai kebijakan. Penghapusan organisasi yang memiliki data terkait harus mengikuti kebijakan soft delete, arsip, atau pembatasan penghapusan; jangan menghapus data historis tanpa persetujuan.

### FR-002 — Manajemen pengguna (MUST)
Sistem harus mengelola nama, email, password hash, nomor telepon, status aktif, organisasi, dan role. Password tidak boleh dikembalikan dalam response.

### FR-003 — Autentikasi (MUST)
Sistem harus menyediakan login, logout, dan endpoint profil pengguna. Token harus dapat dicabut. Akun nonaktif tidak boleh memperoleh akses.

### FR-004 — RBAC (MUST)
Sistem harus mengelola role dan permission. Pemeriksaan permission dilakukan di backend. Super admin dapat memiliki bypass yang dibatasi dan diaudit. Penugasan petani/pemilik ke kebun bukan pengganti permission aplikasi.

### FR-005 — Isolasi organisasi (MUST)
Semua query, endpoint, file, cache, dan proses sinkronisasi harus membatasi data berdasarkan organisasi yang berwenang. ID dari klien tidak boleh menjadi dasar tunggal untuk menentukan organisasi.

### FR-006 — Master wilayah (MUST)
Sistem harus mengelola desa, blok, dan kebun dengan relasi yang valid. Kebun berada pada blok; blok berada pada desa.

### FR-007 — Pemilik kebun (MUST)
Sistem harus mendukung relasi banyak-ke-banyak antara pemilik dan kebun.

### FR-008 — Petani dan penugasan (MUST)
Sistem harus mengelola petani dan penugasan petani ke blok dan/atau kebun. Penugasan tidak otomatis memberi seluruh permission sistem.

### FR-009 — Varietas durian (MUST)
Sistem harus menyediakan master varietas yang dapat digunakan saat membuat atau memperbarui pohon.

### FR-010 — Registrasi pohon (MUST)
Setiap pohon memiliki UUID unik dan terhubung ke satu kebun. Data meliputi varietas, tanggal tanam, koordinat, status, dan atribut lain yang disepakati.

### FR-011 — Riwayat pengukuran (MUST)
Sistem harus menyimpan pengukuran historis dengan tanggal, petugas, nilai pengukuran, catatan, dan foto bila ada.

### FR-012 — Pemupukan (MUST)
Satu catatan pemupukan dapat memiliki beberapa item produk. Simpan tanggal, pelaksana, cakupan/objek, dosis/satuan bila tersedia, dan catatan.

### FR-013 — Penyemprotan (MUST)
Satu catatan penyemprotan dapat memiliki beberapa item produk. Simpan tanggal, pelaksana, cakupan/objek, dosis/satuan bila tersedia, dan catatan.

### FR-014 — Pembungaan (MUST)
Sistem harus menyimpan catatan pembungaan dengan objek, tanggal, kondisi, pelaksana, dan catatan sesuai skema.

### FR-015 — Perkembangan buah (MUST)
Sistem harus menyimpan pengamatan perkembangan buah secara historis.

### FR-016 — Foto aktivitas (MUST)
Sistem harus mengunggah dan mengaitkan foto dengan aktivitas atau objek yang sah. Karena `activity_photos` bersifat polymorphic, tipe dan ID objek wajib divalidasi di aplikasi.

### FR-017 — Musim panen (MUST)
Sistem harus mengelola periode musim panen.

### FR-018 — Sesi panen (MUST)
Sesi panen harus terkait dengan kebun dan musim yang sesuai.

### FR-019 — Catatan panen per pohon (MUST)
Catatan panen menyimpan pohon, kebun, sesi, jumlah buah, berat total, dan atribut tambahan sesuai kebutuhan. Validasi harus memastikan pohon dan sesi berasal dari kebun yang sama.

### FR-020 — Detail/grade panen (SHOULD)
Sistem dapat menyimpan detail mutu atau grade jika fitur ini diaktifkan.

### FR-021 — QR pohon (MUST)
Sistem harus menghasilkan atau mengelola QR Code unik per pohon. Halaman publik hanya menampilkan field yang secara eksplisit diizinkan.

### FR-022 — Dashboard dan laporan (MUST)
Sistem menyediakan ringkasan sesuai organisasi, role, dan permission. Angka laporan harus dapat ditelusuri ke data sumber.

### FR-023 — Cuaca (SHOULD)
Sistem dapat mengambil dan menyimpan data cuaca dari provider yang ditentukan. Kegagalan provider tidak boleh menggagalkan fungsi inti pencatatan.

### FR-024 — Fondasi IoT (COULD)
Sistem dapat menerima telemetry perangkat pada tahap pengembangan lanjutan. Integrasi operasional belum menjadi prasyarat rilis inti.

### FR-025 — Offline mobile (MUST)
Mobile menyimpan data yang diperlukan di SQLite dan dapat melakukan pencatatan offline.

### FR-026 — Sinkronisasi (MUST)
Sistem menyediakan proses bootstrap/pull dan push data lokal. Setiap item sinkronisasi memiliki identitas stabil, status, waktu, dan hasil. Konflik ditangani dengan kebijakan eksplisit.

### FR-027 — Audit log (MUST)
Perubahan penting harus mencatat aktor, waktu, jenis aksi, objek, dan informasi perubahan yang aman. Hindari menyimpan password, token, atau rahasia.

## 4. Kebutuhan Nonfungsional

### NFR-001 — Performa
Target awal API: P95 ≤ 800 ms pada endpoint yang disepakati, di bawah profil beban yang terdokumentasi. Ukur P50, P95, P99, throughput, error rate, CPU, memory, dan query time.

### NFR-002 — Keamanan
Gunakan HTTPS di production, hashing password yang aman, validasi input, otorisasi backend, rate limiting untuk endpoint sensitif, dan pengelolaan rahasia melalui environment/secret manager.

### NFR-003 — Isolasi tenant
Tidak boleh ada akses lintas organisasi tanpa izin eksplisit yang sah.

### NFR-004 — Integritas data
Gunakan foreign key, transaksi, validasi relasi, constraint, dan migrasi yang aman.

### NFR-005 — Ketersediaan
Kegagalan API cuaca atau layanan eksternal tidak boleh menghentikan pencatatan inti. Strategi backup dan pemulihan harus ditetapkan.

### NFR-006 — Usability
UI harus konsisten, responsif, memiliki status proses yang jelas, dan menghindari form/dashboard yang membebani pengguna.

### NFR-007 — Offline
Mobile harus menunjukkan status lokal, antrean, berhasil, gagal, dan konflik. Pengguna tidak boleh mengira data telah tersimpan di server sebelum server mengonfirmasi sinkronisasi.

### NFR-008 — Maintainability
Gunakan pemisahan controller, service/action, model, request validation, resource, policy/middleware, dan test sesuai kebutuhan proyek.

### NFR-009 — Observability
Sediakan log terstruktur, pemantauan error, query lambat, performa endpoint, dan proses sinkronisasi.

### NFR-010 — Compatibility
Kunci versi dependency yang kompatibel dan verifikasi terhadap `composer.lock` serta versi Flutter/Dart aktual.

## 5. Aturan Validasi Umum

- UUID harus valid dan tidak boleh digunakan untuk objek organisasi lain.
- Tanggal harus masuk akal dan mengikuti zona waktu yang ditetapkan.
- Berat, diameter, tinggi, lebar tajuk, jumlah buah, dan dosis harus memiliki validasi rentang dan satuan.
- File harus divalidasi berdasarkan MIME, ukuran, dan isi file.
- Relasi pohon-kebun-sesi panen harus konsisten.
- Data yang dihapus/diarsipkan tidak boleh menghilangkan jejak audit.

## 6. Antarmuka Eksternal

- Web browser melalui HTTPS.
- Flutter mobile melalui REST JSON API.
- MariaDB melalui koneksi backend.
- File storage melalui Laravel filesystem/object storage.
- Provider cuaca melalui HTTP API.
- Potensi MQTT untuk IoT tahap berikutnya.

## 7. Kriteria Verifikasi

Setiap FR harus memiliki minimal satu test positif, satu test validasi negatif, dan test otorisasi yang relevan. Kebutuhan tenant isolation harus memiliki test lintas organisasi. Kebutuhan offline harus diuji dengan jaringan terputus dan pemulihan koneksi.
