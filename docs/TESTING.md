# TESTING STRATEGY
## Sistem Informasi Manajemen Perkebunan Durian

- Versi: 1.0
- Tujuan: memastikan fungsi, keamanan, integritas, performa, dan sinkronisasi bekerja sesuai kebutuhan.

## 1. Strategi Pengujian

Lapisan pengujian:
1. Unit test: fungsi kecil, aturan, service, dan helper.
2. Feature/API test: endpoint, validasi, auth, permission, response.
3. Integration test: relasi database, storage, queue, cache, dan provider.
4. UI test: alur web dan mobile.
5. Offline/sync test: operasi tanpa jaringan dan rekonsiliasi.
6. Security test: tenant isolation, akses ilegal, upload.
7. Performance/load test: latency, throughput, resource.
8. Regression test: fitur lama tetap berfungsi.

## 2. Lingkungan dan Data Uji

- Gunakan database testing terpisah.
- Jangan menjalankan test destruktif pada database produksi.
- Gunakan factory/seed data yang dapat diulang.
- Buat minimal dua organisasi (A dan B).
- Buat user dengan role berbeda.
- Buat data kebun, pohon, aktivitas, dan panen yang saling terhubung.
- Simpan kredensial test hanya di environment test.

## 3. Functional Test Cases

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| F-001 | Login dengan kredensial benar | Token diberikan |
| F-002 | Login password salah | Ditolak |
| F-003 | Login akun nonaktif | Ditolak |
| F-004 | Logout | Token aktif dicabut |
| F-005 | Request tanpa token ke endpoint privat | 401 |
| F-006 | User tanpa permission | 403 |
| F-007 | Super admin melakukan aksi sesuai kebijakan | Diizinkan dan diaudit bila sensitif |
| F-008 | Membuat desa, blok, kebun | Relasi tersimpan benar |
| F-009 | Membuat pohon pada kebun valid | Berhasil |
| F-010 | Membuat pohon pada kebun organisasi lain | Ditolak |
| F-011 | Satu kebun memiliki beberapa pemilik | Relasi tersimpan |
| F-012 | Petani ditugaskan ke blok/kebun | Scope penugasan benar |
| F-013 | Menambah pengukuran pohon | Histori baru tersimpan |
| F-014 | Menambah pemupukan dengan beberapa produk | Header dan item konsisten |
| F-015 | Menambah penyemprotan dengan beberapa produk | Header dan item konsisten |
| F-016 | Menambah pembungaan/perkembangan buah | Histori tersimpan |
| F-017 | Mencatat panen | Terhubung ke sesi, kebun, dan pohon yang benar |
| F-018 | Sesi dan pohon berbeda kebun | Ditolak |
| F-019 | Membuat QR pohon | QR mengarah ke halaman yang dibatasi |
| F-020 | Upload file valid | File dan metadata tersimpan |
| F-021 | Upload file berbahaya/tidak valid | Ditolak |
| F-022 | Dashboard user | Data sesuai scope akses |

## 4. Security Test Cases

### Tenant isolation
- User organisasi A meminta daftar data organisasi B.
- User A mengganti UUID pada URL menjadi ID milik B.
- User A mengirim `organization_id` B pada payload create/update.
- User A mengakses foto milik B.
- User A meminta sync pull dengan cursor/ID yang dimanipulasi.
- Cache user A dan B diuji agar tidak saling tertukar.

Hasil yang diharapkan: akses ditolak atau data tidak ditemukan sesuai kebijakan keamanan; tidak ada kebocoran informasi.

### RBAC
- Role tanpa permission tidak dapat melakukan aksi.
- Permission dicabut dan cache permission diinvalidasi.
- User nonaktif tidak dapat mengakses endpoint terlindungi.
- Penugasan petani tidak otomatis memberi permission admin.

### Upload
- Uji MIME palsu, ekstensi palsu, file terlalu besar, file kosong, file rusak, dan nama file berbahaya.
- Pastikan path file tidak dapat digunakan untuk path traversal.
- Pastikan file privat tidak dapat diakses publik tanpa otorisasi.

## 5. Database dan Integrity Test

- Foreign key menolak parent yang tidak ada.
- Constraint unik bekerja.
- Composite foreign key panen memastikan garden/session/tree konsisten.
- Transaksi rollback saat salah satu item gagal.
- Penghapusan mengikuti kebijakan relasi dan tidak menghilangkan histori penting.
- Index yang ditambahkan benar-benar digunakan oleh query target jika sesuai.

## 6. Mobile Offline and Sync Tests

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| S-001 | Buat draft tanpa internet | Data tersimpan di SQLite |
| S-002 | Tutup dan buka ulang aplikasi | Draft tetap tersedia |
| S-003 | Koneksi pulih | Sync berjalan |
| S-004 | Push request dikirim ulang | Tidak membuat duplikasi |
| S-005 | Upload foto terputus | Dapat dilanjutkan/retry |
| S-006 | Server menolak validasi | Item ditandai gagal dengan alasan |
| S-007 | Terjadi konflik versi | Konflik ditandai dan ditangani sesuai kebijakan |
| S-008 | User kehilangan penugasan | Data tidak lagi tersedia pada pull berikutnya |
| S-009 | Sebagian batch gagal | Item sukses tidak diduplikasi saat retry |

## 7. Performance Testing

Target awal:
- API P95 ≤ 800 ms untuk endpoint yang disepakati.
- P50, P95, P99 dicatat.
- Error rate dan throughput dilaporkan.
- Ukuran payload dan query time dipantau.

Skenario:
- Baseline satu pengguna.
- Beban 10 pengguna virtual.
- Beban 50 pengguna virtual.
- Beban 100 pengguna virtual jika kapasitas dan lingkungan memungkinkan.
- Uji endpoint list dengan pagination.
- Uji dashboard.
- Uji upload foto secara terpisah.
- Uji sinkronisasi batch.

Gunakan k6/JMeter atau alat setara. Catat spesifikasi server, versi aplikasi, ukuran dataset, durasi, ramp-up, dan konfigurasi test. Jangan menyamakan latency upload file besar dengan endpoint JSON biasa.

## 8. Cache Testing

- Cache key dipisah berdasarkan organisasi dan konteks.
- Data berubah menyebabkan invalidasi.
- Permission berubah tidak menggunakan cache lama.
- Cache kedaluwarsa sesuai TTL.
- Cache miss tidak menyebabkan response salah.
- Data sensitif tidak bocor antar-user.

## 9. Regression Testing

Jalankan seluruh test suite sebelum dan sesudah perubahan. Catat:
- jumlah test;
- jumlah pass/fail/skip;
- assertion;
- durasi;
- perubahan test;
- alasan test yang diubah.

Jangan menghapus test yang gagal untuk membuat pipeline hijau tanpa memperbaiki akar masalah.

## 10. Exit Criteria

Rilis dapat dipertimbangkan jika:
- Tidak ada bug blocker/critical yang terbuka.
- Seluruh test wajib lulus.
- Tenant isolation lulus.
- Alur utama web dan mobile lulus.
- Sync dan retry lulus.
- Target performa telah diukur pada lingkungan dan beban yang disepakati.
- Backup dan pemulihan telah diuji.
- Dokumentasi dan known issues tersedia.

## 11. Format Laporan Test

Untuk setiap test, catat:
- ID dan nama test.
- Build/commit.
- Lingkungan.
- Data uji.
- Langkah.
- Hasil aktual.
- Hasil yang diharapkan.
- Status PASS/FAIL/BLOCKED.
- Bukti/log.
- Bug ID jika gagal.
