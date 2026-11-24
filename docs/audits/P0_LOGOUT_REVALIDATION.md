# REVALIDATION REPORT: RSK-01 (P0)
## Data Loss Offline Saat Logout pada Aplikasi Mobile Durian Farm

- **Status Revalidasi**: **VERIFIED** (100% Terverifikasi Aktual dari Source Code)
- **Modul Terdampak**: Flutter Mobile App (`mobile/`)
- **Tingkat Keparahan**: **P0 — Critical (Irreversible Offline Data Loss)**
- **Tanggal Revalidasi**: 24 November 2026
- **Auditor**: Principal Software Architect, Senior Flutter Engineer, QA Engineer

---

## 1. Berkas yang Diperiksa (Files Inspected)

1. [mobile/lib/data/repositories/auth_repository.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/auth_repository.dart) (L51-L64)
2. [mobile/lib/data/local/local_database.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart) (L136-L186)
3. [mobile/lib/data/repositories/offline_sync_repository.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart) (L141-L240, L468-L483)
4. [mobile/lib/ui/features/dashboard/dashboard_screen.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/ui/features/dashboard/dashboard_screen.dart) (L80-L90)
5. [mobile/lib/ui/features/dashboard/dashboard_view_model.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/ui/features/dashboard/dashboard_view_model.dart) (L43-L52)
6. [mobile/lib/ui/features/sync/sync_queue_screen.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/ui/features/sync/sync_queue_screen.dart) (L15-L95)
7. [mobile/test/offline_sync_repository_test.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/test/offline_sync_repository_test.dart) (L1-L135)

---

## 2. Hasil Evaluasi 11 Poin Revalidasi

| No | Pertanyaan Revalidasi | Temuan Aktual | Bukti Baris Kode |
|---|---|---|---|
| 1 | Apakah `AuthRepository.logout()` memanggil `localDatabase.clearAllData()`? | **YA**. Dipanggil secara langsung tanpa syarat pada method `logout()`. | [auth_repository.dart:62](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/auth_repository.dart#L62) |
| 2 | Tabel SQLite apa saja yang dihapus oleh `clearAllData()`? | **Seluruh 11 tabel SQLite**: `villages`, `blocks`, `gardens`, `durian_varieties`, `product_categories`, `agricultural_products`, `trees`, `harvest_seasons`, `harvest_sessions`, `sync_queue`, dan `photo_queue`. | [local_database.dart:168-180](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart#L168-L180) |
| 3 | Apakah `sync_queue` ikut terhapus? | **YA**. Tabel `sync_queue` dimasukkan secara eksplisit dalam daftar array tabel yang di-`txn.delete()`. | [local_database.dart:178](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart#L178) |
| 4 | Apakah `photo_queue` ikut terhapus? | **YA**. Tabel `photo_queue` ikut dihapus secara eksplisit. | [local_database.dart:179](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart#L179) |
| 5 | Apakah aktivitas lokal yang belum disinkronkan ikut terhapus? | **YA**. Seluruh aktivitas offline (pengukuran pohon, pemupukan, penyemprotan, pembungaan, buah, panen) disimpan di `sync_queue`. Begitu tabel dihapus, seluruh data aktivitas hilang permanen. | [offline_sync_repository.dart:154-161](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart#L154-L161) |
| 6 | Apakah UI logout menampilkan dialog konfirmasi? | **TIDAK**. `IconButton` di AppBar langsung mengeksekusi `await widget.authRepository.logout(); widget.onLogout();` saat ditekan, tanpa `showDialog` atau konfirmasi apapun. | [dashboard_screen.dart:84-87](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/ui/features/dashboard/dashboard_screen.dart#L84-L87) |
| 7 | Apakah aplikasi memeriksa antrean sinkronisasi pending sebelum logout? | **TIDAK**. Tidak ada pengecekan terhadap `_viewModel.pendingCount` maupun query hitung terhadap record dengan status `pending` / `failed`. | [dashboard_screen.dart:83-88](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/ui/features/dashboard/dashboard_screen.dart#L83-L88) |
| 8 | Apa yang terjadi ketika perangkat sedang offline? | Pemanggilan API logout ke server gagal dan ditelan oleh blok `catch (_) {}`. Alur tetap melanjutkan eksekusi ke `clearSession()` dan `clearAllData()`. Seluruh data lokal tetap dihapus padahal belum pernah dikirim ke server. | [auth_repository.dart:57-62](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/auth_repository.dart#L57-L62) |
| 9 | Apa yang terjadi jika pengunggahan gagal sebelumnya? | Record dengan status `failed` di `sync_queue` dan `photo_queue` yang seharusnya di-retry otomatis dihapus permanen saat logout. | [offline_sync_repository.dart:280-285](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart#L280-L285) |
| 10 | Apakah foto tertunda dan relasinya tetap terjaga? | **TIDAK**. Baris database di `photo_queue` terhapus. File biner foto di storage menjadi file yatim (orphaned files) tanpa asosiasi ID aktivitas (`activity_id`) dan caption. | [local_database.dart:179](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart#L179) |
| 11 | Bisakah pengguna login kembali dan melanjutkan sinkronisasi data lama? | **TIDAK**. Saat pengguna login kembali, antrean sinkronisasi sudah kosong total (`0 item`). Data tidak dapat dipulihkan. | [local_database.dart:182-185](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart#L182-L185) |

---

## 3. Akar Masalah (Exact Root Cause)

Akar masalah terdiri atas tiga lapis defek desain:
1. **Pembersihan Database yang Terlalu Luas (Over-destructive teardown)**:
   Method `AuthRepository.logout()` dirancang dengan asumsi naif bahwa logout harus membersihkan seluruh cache lokal perangkat (`clearAllData()`), tanpa membedakan antara **kredensial sesi (auth session)**, **data referensi/master yang dapat diunduh ulang (read-only master data)**, dan **data transaksional buatan pengguna yang belum disinkronkan (user-generated offline mutations)**.
2. **Ketiadaan Validasi Guard pada Application Layer**:
   `AuthRepository` tidak memiliki parameter atau penjaga (guard) untuk mendeteksi apakah masih ada baris di `sync_queue` atau `photo_queue` dengan status `pending` atau `failed`.
3. **Ketiadaan Konfirmasi dan Peringatan pada Presentation Layer**:
   `DashboardScreen` menempatkan tombol logout di AppBar beranda dan langsung mengeksekusi logout dalam satu sentuhan tanpa dialog konfirmasi, tanpa mengecek `_viewModel.pendingCount`.

---

## 4. Skenario Reproduksi (Reproduction Scenario)

1. Petani login di aplikasi Flutter dalam kondisi online.
2. Petani menuju kebun durian di daerah blank spot / tanpa sinyal (mode offline).
3. Melalui menu aplikasi, petani mencatat 10 pengukuran pohon, 2 pemupukan, dan mengambil 5 foto durian. Seluruh record masuk ke `sync_queue` dan `photo_queue` berstatus `pending`.
4. Di beranda, badge sinkronisasi menunjukkan angka `10` antrean pending.
5. Petani secara tidak sengaja menyentuh ikon `Keluar` (logout) di sudut kanan atas AppBar, atau bermaksud berganti koneksi/refresh akun.
6. Aplikasi langsung kembali ke halaman `LoginScreen`.
7. Petani login kembali.
8. **Hasil**: Seluruh 10 pengukuran, pemupukan, dan 5 foto hilang tanpa jejak. Antrean sinkronisasi menjadi `0`. Tidak ada data yang pernah sampai ke server Laravel.

---

## 5. Dampak Kehilangan Data (Data-Loss Impact)

- **Kehilangan Hasil Kerja Lapangan (Irreversible Loss)**: Data operasional perkebunan durian yang dicatat berjam-jam di lapangan hilang seketika.
- **Integritas Panen & Pemeliharaan Rusak**: Catatan panen per pohon yang hilang menyebabkan selisih berat dan buah fisik dengan pembukuan.
- **Hilangnya Kepercayaan Pengguna**: Petani enggan menggunakan aplikasi mobile jika data mereka mudah hilang hanya karena salah tekan tombol.

---

## 6. Rekomendasi Solusi Aman (Recommended Safe Solution)

Berdasarkan batasan arsitektur (Phase 2):
1. **Prinsip Utama**: Logout **hanya boleh menghapus kredensial sesi autentikasi** (`SessionStorage.clearSession()`), dan **TIDAK BOLEH menghapus data antrean offline (`sync_queue`, `photo_queue`) maupun data master pendukung relasinya**.
2. **Preservasi Relasi Lokal**: Karena `sync_queue` menyimpan foreign key seperti `tree_id`, `session_id`, `product_id`, maka tabel master (`trees`, `harvest_sessions`, `agricultural_products`, dll.) juga harus tetap dipertahankan di SQLite lokal agar saat user login kembali, record dapat ditampilkan dan disinkronkan secara konsisten.
3. **Dialog Konfirmasi Logout pada UI**:
   - Jika `pendingCount == 0`: Tampilkan konfirmasi standar: *"Apakah Anda yakin ingin keluar?"* dengan tombol Batal dan Keluar.
   - Jika `pendingCount > 0`: Tampilkan peringatan keras dengan warna peringatan (Orange/Merah):
     *"Terdapat {N} aktivitas dan {M} foto yang belum disinkronkan ke server. Jika Anda keluar sekarang, data akan tetap tersimpan di perangkat ini dan dapat disinkronkan kembali saat Anda login berikutnya."*
   - Berikan tombol: **Batal** dan **Tetap Keluar**.
4. **Metode Khusus di LocalDatabase**:
   Hapus pemanggilan `clearAllData()` dari alur `logout()`. Tambahkan method `hasPendingSync()` di `LocalDatabase` atau `OfflineSyncRepository` untuk mendeteksi apakah ada antrean belum tersinkronisasi.
5. **Kemampuan Melanjutkan Sinkronisasi**:
   Saat pengguna login kembali (baik pengguna yang sama atau pengguna lain dengan penugasan kebun yang sama), method `syncPending()` tetap dapat membaca `sync_queue` yang tersimpan dan mengirimkannya ke server dengan token baru.

---

## 7. Rencana Pengujian (Test Cases Required)

1. **Test 1**: Logout tanpa data pending -> Sesi terhapus, database lokal tidak rusak.
2. **Test 2**: Logout dengan data pending di `sync_queue` -> Sesi terhapus, data `sync_queue` tetap utuh.
3. **Test 3**: Logout dengan foto pending di `photo_queue` -> Sesi terhapus, data `photo_queue` tetap utuh.
4. **Test 4**: Logout dalam kondisi offline (API throw exception) -> Sesi terhapus, data lokal tetap utuh.
5. **Test 5**: User membatalkan dialog logout -> Sesi tetap aktif, tidak ada data berubah.
6. **Test 6**: Login kembali setelah logout -> Data pending dapat dibaca kembali dan `syncPending()` berhasil mengirimkannya ke server.
7. **Test 7**: Sinkronisasi gagal karena koneksi -> Status berubah menjadi `failed`, data tidak dihapus dari `sync_queue`, dan tetap tersedia untuk dicoba kembali.
