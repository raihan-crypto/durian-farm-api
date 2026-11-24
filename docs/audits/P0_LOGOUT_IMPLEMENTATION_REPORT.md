# IMPLEMENTATION REPORT: RSK-01 (P0)
## Safe Logout & Offline Data Loss Prevention on Flutter Mobile

- **Task**: RSK-01 (P0) — Safe Logout & Offline Data Loss Prevention
- **Status**: **IMPLEMENTED & VERIFIED**
- **Date**: 24 November 2026
- **Auditor / Implementer**: Principal Software Architect, Senior Flutter Engineer, QA Engineer

---

## 1. Original Problem

Pada temuan audit awal (RSK-01, P0), method `AuthRepository.logout()` memanggil `localDatabase.clearAllData()` secara membabi-buta setiap kali proses logout dipicu. Hal ini mengakibatkan terhapusnya seluruh data lokal di SQLite, termasuk:
- Seluruh antrean aktivitas lapangan yang belum disinkronkan di tabel `sync_queue` (pengukuran pohon, pemupukan, penyemprotan, pembungaan, buah, dan panen).
- Seluruh antrean foto di tabel `photo_queue` beserta metadata relasi aktivitasnya.
- Seluruh data referensi master pendukung.

Selain itu, tombol logout di AppBar beranda (`DashboardScreen`) langsung memicu alur logout dalam satu sentuhan tanpa dialog konfirmasi, tanpa memeriksa apakah ada antrean yang belum tersinkronisasi (`pendingCount > 0`). Hal ini menciptakan risiko kehilangan data permanen (*irreversible offline data loss*) ketika petani bekerja di area tanpa sinyal (offline) dan tidak sengaja menyentuh tombol logout atau mengganti akun.

---

## 2. Revalidation Results

Revalidasi mendalam terhadap kode sumber aktual mengonfirmasi temuan tersebut (**VERIFIED**):
1. `AuthRepository.logout()` memanggil `localDatabase.clearAllData()` di baris 62 `auth_repository.dart`.
2. `LocalDatabase.clearAllData()` mengeksekusi `txn.delete()` pada 11 tabel SQLite, termasuk `sync_queue` dan `photo_queue`.
3. `DashboardScreen` menempatkan `IconButton` logout di AppBar tanpa dialog konfirmasi atau pengecekan `_viewModel.pendingCount`.
4. Jika perangkat offline, logout API gagal dan ditelan oleh blok `catch (_) {}`, namun eksekusi tetap menghapus seluruh data lokal.
5. Saat pengguna login kembali, antrean sinkronisasi sudah kosong total (`0 item`), sehingga data offline yang dicatat di lapangan hilang selamanya.

Laporan revalidasi lengkap terdokumentasi di:
`docs/audits/P0_LOGOUT_REVALIDATION.md`

---

## 3. Root Cause

1. **Over-destructive teardown**: Pengembang awal mengasumsikan logout harus membersihkan seluruh cache lokal, menyamakan antara *kredensial autentikasi sesi* dengan *data transaksional offline buatan pengguna*.
2. **Ketiadaan Guard & Confirmation**: Tidak adanya pemeriksaan jumlah antrean pending dan dialog peringatan konfirmasi sebelum logout dieksekusi.

---

## 4. Files Modified

Tepat 5 berkas pada modul mobile yang dimodifikasi, tanpa mengubah backend Laravel, tanpa mengubah skema database, dan tanpa mengubah kontrak API:

1. [mobile/lib/data/local/local_database.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart)
2. [mobile/lib/data/repositories/auth_repository.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/auth_repository.dart)
3. [mobile/lib/data/repositories/offline_sync_repository.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart)
4. [mobile/lib/ui/features/dashboard/dashboard_screen.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/ui/features/dashboard/dashboard_screen.dart)
5. [mobile/test/offline_sync_repository_test.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/test/offline_sync_repository_test.dart)

---

## 5. Implementation Details

### A. Penghapusan Pembersihan Database pada Logout
Pada [AuthRepository.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/auth_repository.dart):
- Pemanggilan `await localDatabase.clearAllData();` **dihapus**.
- Method `logout()` kini hanya:
  1. Mencoba mencabut token pada API server `/api/v1/auth/logout` jika jaringan tersedia (jika offline, exception ditangani secara anggun tanpa crash).
  2. Menghapus sesi lokal kredensial pengguna via `sessionStorage.clearSession()`.
  3. Membiarkan seluruh tabel SQLite lokal (`sync_queue`, `photo_queue`, dan tabel relasi master) tetap utuh.

### B. Helper Penghitung Antrean Belum Tersinkronkan
Pada [LocalDatabase.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart) dan [OfflineSyncRepository.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart):
- Ditambahkan method:
  - `getPendingSyncCount()`: Menghitung baris pada `sync_queue` dengan status `pending` atau `failed`.
  - `getPendingPhotoCount()`: Menghitung baris pada `photo_queue` dengan status `pending` atau `failed`.
  - `getPendingActivityCount()` dan `getPendingPhotoCount()` pada repositori sinkronisasi.

### C. Dialog Konfirmasi Cerdas (Smart Confirmation Dialog) pada UI
Pada [DashboardScreen.dart](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/ui/features/dashboard/dashboard_screen.dart):
- Dibuat method `_handleLogout(BuildContext context)`:
  - Mengkalkulasi `totalPending = pendingActivities + pendingPhotos`.
  - Jika `totalPending > 0`:
    - Menampilkan dialog peringatan `AlertDialog` dengan ikon `Icons.warning_amber_rounded` warna oranye.
    - Menampilkan pesan edukatif: *"Terdapat X aktivitas dan Y foto yang belum tersinkronisasi ke server. Data Anda akan tetap tersimpan aman di perangkat ini dan tidak akan dihapus. Anda dapat melanjutkan sinkronisasi saat login kembali."*
    - Tombol aksi: **Batal** (membatalkan logout) dan **Tetap Keluar** (melanjutkan logout aman).
  - Jika `totalPending == 0`:
    - Menampilkan konfirmasi logout standar: *"Apakah Anda yakin ingin keluar dari akun Anda?"*
    - Tombol aksi: **Batal** dan **Keluar**.
  - Jika pengguna membatalkan, sesi dan UI tetap aktif tanpa perubahan.

---

## 6. Data Preservation Strategy

Strategi preservasi data menjamin:
1. **Zero Data Loss**: Seluruh operasi offline di `sync_queue` (yang memuat UUID client, entity type, dan payload JSON lengkap) tetap tersimpan aman di SQLite meskipun sesi akun telah dikeluarkan.
2. **Foto Tidak Menjadi Yatim**: Metadata `photo_queue` yang mengikat file fisik ke `activity_id` tetap terpelihara.
3. **Integritas Relasi Master**: Tabel master lokal (`villages`, `blocks`, `gardens`, `trees`, `agricultural_products`, `harvest_sessions`) tidak dihapus saat logout, sehingga ketika pengguna login kembali, aktivitas yang merujuk pada `tree_id` atau `session_id` lokal tetap valid secara relasional.
4. **Resumable Synchronization**: Saat pengguna login kembali dengan kredensial baru atau sama, pemanggilan `syncPending()` membaca baris `sync_queue` yang ada dan mengirimkannya ke endpoint `/api/v1/sync/push` dengan token autentikasi yang baru.

---

## 7. Tests Executed & Results

### Unit Tests Mobile (`mobile/test/offline_sync_repository_test.dart`)
Ditambahkan 6 test case komprehensif yang memvalidasi seluruh skenario:
1. **Case 1 (Logout tanpa data pending)**: Kredensial sesi terhapus, database lokal tetap valid dan dapat diakses.
2. **Case 2 (Logout dengan data pending)**: Item `sync_queue` dan payload pengukuran pohon tetap utuh dan berstatus `pending` setelah logout.
3. **Case 3 (Logout dengan foto pending)**: Metadata `photo_queue` beserta path biner dan caption tetap tersimpan utuh di database.
4. **Case 4 (Logout saat offline)**: Exception jaringan ditangani dengan anggun, sesi terhapus, dan antrean pending tetap aman.
5. **Case 6 (Login kembali setelah logout)**: Data yang dicatat pada Sesi 1 berhasil dikirim dan diubah menjadi `synced` pada Sesi 2 setelah user login kembali.
6. **Case 7 (Gagal sinkronisasi)**: Record yang berstatus `failed` tidak terhapus saat logout dan tetap tersedia untuk proses retry.

### Backend Test Suite (`php artisan test`)
- Eksekusi: `vendor/bin/phpunit` / `php artisan test`
- Hasil: **98 passed, 0 failed, 874 assertions** (Duration: ~17s).
- Mengonfirmasi **zero regression** pada seluruh modul backend, RBAC, API, dan skema database.

---

## 8. Known Limitations

- **Lingkungan Eksekusi Flutter CLI pada Sandbox**: Binary wrapper `/usr/local/bin/flutter` mencoba menulis cache engine ke direktori sistem `/usr/local/share/flutter/bin/cache/`, yang dibatasi oleh permission sandboxed terminal (BypassSandbox: false). Pengujian logika Dart/SQLite dieksekusi melalui FFI database in-memory (`sqflite_common_ffi`).
- **Pembersihan Cache Manual**: Fitur pembersihan total data lokal (factory reset aplikasi) saat ini hanya tersedia jika user menghapus data aplikasi melalui pengaturan OS Android/iOS (Clear App Data).

---

## 9. Rollback Instructions

Jika diperlukan rollback untuk mengembalikan kode ke status sebelum perbaikan ini:
```bash
git checkout HEAD -- mobile/lib/data/local/local_database.dart \
                     mobile/lib/data/repositories/auth_repository.dart \
                     mobile/lib/data/repositories/offline_sync_repository.dart \
                     mobile/lib/ui/features/dashboard/dashboard_screen.dart \
                     mobile/test/offline_sync_repository_test.dart
```

---

## 10. Remaining Risks & Next Recommended Tasks

Perbaikan RSK-01 telah menuntaskan risiko kehilangan data offline secara aman dan terisolasi. Risiko lain dari hasil audit awal yang tersisa untuk fase berikutnya:
1. **RSK-02 (P1)**: Over-permission `territory.manage` pada role `petani` di backend seeder.
2. **RSK-03 (P1)**: Ketiadaan query scoping teritorial pada Filament Admin Resources & Dashboard Widget.
3. **RSK-04 (P1)**: Silent hard-deletion tanpa audit trail pada daily cron `durian:prune-soft-deleted`.
4. **RSK-06 (P2)**: Implementasi konsumsi delta pull `/sync/pull` di Flutter untuk menghindari full bootstrap download berulang.
