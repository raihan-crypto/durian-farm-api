# FINAL SECURITY SPRINT 2 — PRE-COMMIT VERIFICATION AUDIT
**Sistem Informasi Manajemen Perkebunan Durian**

---

## 1. INFORMASI AUDIT & REPOSITORY

| Parameter | Keterangan |
|---|---|
| **Tanggal Audit** | 2026-11-25 |
| **Status Verifikasi** | PRE-COMMIT FINAL AUDIT (READ ONLY) |
| **Baseline Git Commit** | `ab30642aa40479dd775d7da9bccecafa1675a852` (`security: complete RSK-01 through RSK-08 remediation`) |
| **HEAD Git Commit** | `ab30642aa40479dd775d7da9bccecafa1675a852` (Working tree uncommitted) |
| **Branch** | `main` (Up to date dengan `origin/main`) |
| **Environment Backend** | Laravel 13.43.0, PHP 8.3.14, MariaDB 10.4.28 (Test: SQLite `:memory:`) |
| **Environment Mobile** | Flutter 3.x / Dart 3.14, `flutter_secure_storage: 11.2.0`, `sqflite: 2.4.1` |
| **Verdict** | **READY TO COMMIT** |

---

## 2. STATUS TEMUAN VULN-01 SAMPAI VULN-08

| ID | Kerentanan / Risiko Asli | Solusi Teknis Terimplementasi | Bukti File & Baris Kode | Status |
|---|---|---|---|---|
| **VULN-01** | Kontaminasi antrean sinkronisasi multi-pengguna & migrasi destruktif SQLite lokal | Migrasi v1 $\to$ v2 non-destruktif (`ALTER TABLE ADD COLUMN owner_user_id`), data lama diisolasi/diberi label `'quarantined_legacy'` tanpa dihapus. Seluruh operasi antrean (`enqueue`, `read`, `sync`) terikat ketat ke `session.userId`. | [`mobile/lib/data/local/local_database.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart#L44-L95), [`mobile/lib/data/repositories/offline_sync_repository.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart#L160-L195) | **RESOLVED & TESTED** |
| **VULN-02** | Risiko kegagalan parsial & rollback tidak konsisten pada batch push sinkronisasi | `SyncEngineService::pushBatch()` menggunakan pemrosesan atomik per item dengan pencatatan `SyncLog`, penanganan exception deterministik, dan respons terstruktur tanpa partial corruption. | [`app/Services/SyncEngineService.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Services/SyncEngineService.php#L630-L750) | **RESOLVED & TESTED** |
| **VULN-03** | Potensi infinite loop & cursor pagination rusak pada mobile delta sync | Pemrosesan delta di mobile dibatasi batas aman (`maxPages: 50`), pengecekan eksplisit `has_more` & `next_cursor != null`. Kegagalan transaksi pada halaman perantara membatalkan pembaruan cursor lokal agar delta dapat diulang aman. | [`mobile/lib/data/repositories/offline_sync_repository.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart#L780-L865) | **RESOLVED & TESTED** |
| **VULN-04** | Penghapusan massal soft-deleted records berpotensi merusak integritas referensial | `PruneSoftDeletedRecordsCommand` menerapkan pengecekan dependensi rekursif (`hasActiveDependencies()`), pencatatan `AuditLog` di dalam transaksi database sebelum `forceDelete()`, dan pembersihan file media fisik hanya setelah commit berhasil. | [`app/Console/Commands/PruneSoftDeletedRecordsCommand.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Console/Commands/PruneSoftDeletedRecordsCommand.php#L115-L258) | **RESOLVED & TESTED** |
| **VULN-05** | Zombie syncing leases: antrean menggantung tanpa batas jika aplikasi mobile crash | Ditambahkan kolom `syncing_started_at` dan `sync_lease_id` (UUID). Mekanisme startup auto-recovery (`recoverInterruptedOnStartup()`) me-reset lease kedaluwarsa (> 5 menit) ke status `pending`, sambil melindungi lease aktif in-flight. | [`mobile/lib/data/local/local_database.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/local/local_database.dart#L70-L85), [`mobile/lib/data/repositories/offline_sync_repository.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart#L28-L45) | **RESOLVED & TESTED** |
| **VULN-06** | Eskalasi hak akses pada pembuatan dan penugasan Role / Permission | `RoleHierarchyService` mengisolasi role `super_admin` (`PROTECTED_ROLES`). Admin dilarang memodifikasi super admin, dilarang menugaskan super admin, dilarang mengubah role sendiri, dan dilarang memberikan permission di luar hak miliknya sendiri. | [`app/Services/RoleHierarchyService.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Services/RoleHierarchyService.php#L16-L118), [`app/Filament/Resources/Roles/RoleResource.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Filament/Resources/Roles/RoleResource.php#L85-L110), [`app/Http/Controllers/Api/V1/UserController.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Http/Controllers/Api/V1/UserController.php#L78-L185) | **RESOLVED & TESTED** |
| **VULN-07** | Fallback diam-diam ke plaintext storage jika secure storage mobile gagal | `SessionStorage` menggunakan `FlutterSecureStorage` (Keychain di iOS, KeyStore/EncryptedSharedPreferences di Android). Mengotomatisasi migrasi transparan dari key lama plaintext, menghapus key lama, dan melempar typed exception jika vault platform tidak dapat diakses (tanpa silent plaintext fallback). | [`mobile/lib/data/services/session_storage.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/services/session_storage.dart#L30-L140) | **RESOLVED & TESTED** |
| **VULN-08** | Inkonsistensi zona waktu dan race condition pada cursor delta sync | Penyelarasan zona waktu server deterministik menggunakan `config('app.timezone')` / UTC, format ISO-8601 standar, serta pembatasan rentang query terikat waktu (`applyDeltaWindow`) dengan pengurutan komposit deterministik `(timestamp ASC, id ASC)`. | [`app/Services/SyncEngineService.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Services/SyncEngineService.php#L226-L380) | **RESOLVED & TESTED** |

---

## 3. STATUS SELURUH POST-AUDIT FIXES & MICRO-FIX

### 3.1. TrashBin Dependency Guard & Allowlist
- **File**: [`app/Filament/Pages/TrashBin.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Filament/Pages/TrashBin.php)
- **Implementasi**:
  1. Didefinisikan konstanta `ALLOWED_MODELS` (hanya 11 model master/entitas resmi). Class di luar allowlist ditolak dengan notifikasi bahaya.
  2. Implementasi `getActiveDependencyDescriptions()` memeriksa seluruh relasi anak (misal `Village` $\to$ `blocks`, `Garden` $\to$ `trees`/`harvest`, `Tree` $\to$ `measurements`/`harvest`/`photos`).
  3. Force delete dibatalkan jika terdapat data terhubung aktif/historis dan menampilkan rincian relasi kepada Super Admin.
  4. Force delete yang sah dicatat ke dalam `AuditLog` di dalam transaksi database.
  5. File fisik media `ActivityPhoto` dihapus melalui `PhotoStorageService::deleteFile()` hanya setelah transaksi database berhasil di-commit.
- **Status**: **VERIFIED & TESTED** (5 unit/feature tests passing).

### 3.2. Penyelarasan QR Token pada Delta Sync Backend
- **File**: [`app/Services/SyncEngineService.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Services/SyncEngineService.php#L309-L318), [`app/Models/Tree.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Models/Tree.php#L65-L82)
- **Implementasi**:
  - `Tree` memiliki `$appends = ['qr_token']` dan accessor `getQrTokenAttribute()`.
  - Pada `pullChanges()`, koleksi pohon dipetakan sehingga menyertakan `'qr_token' => $tree->qrCode?->is_active ? $tree->qrCode->public_token : null`, selaras dengan output endpoint `bootstrap()`.
- **Status**: **VERIFIED & TESTED**.

### 3.3. Validasi Malformed Cursor pada Pull Sync (HTTP 422)
- **File**: [`app/Services/SyncEngineService.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Services/SyncEngineService.php#L548-L605)
- **Implementasi**:
  - `decodePullCursor()` memvalidasi base64 padding, struktur JSON, keberadaan key `snapshot_time`, validitas parsing tanggal via Carbon, rentang tahun wajar ($2000 \le \text{year} \le 2100$), serta format array `entities` dan `tombstones`.
  - Seluruh kegagalan struktur cursor melempar `ValidationException` sehingga menghasilkan respons standar **HTTP 422 Unprocessable Entity** (bukan HTTP 500 Uncaught Exception).
- **Status**: **VERIFIED & TESTED** (4 negative tests + 1 positive pagination test passing).

### 3.4. Validasi Server-Side Permissions pada RoleResource
- **File**: [`app/Filament/Resources/Roles/RoleResource.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Filament/Resources/Roles/RoleResource.php#L95-L110)
- **Implementasi**:
  - Ditambahkan rule penutupan (closure rule) pada form component `Select::make('permissions')`.
  - Jika pengguna yang login bukan `super_admin`, sistem memeriksa seluruh ID permission yang dikirimkan. Jika ada ID di luar himpunan hak akses pengguna (`$actor->roles->flatMap->permissions`), request ditolak dengan pesan kesalahan validasi.
- **Status**: **VERIFIED & TESTED** (3 Livewire feature tests passing).

### 3.5. Micro-Fix: Semantik QR Token Deactivation di Mobile SQLite
- **File**: [`mobile/lib/data/repositories/offline_sync_repository.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/lib/data/repositories/offline_sync_repository.dart#L959-L1025)
- **Implementasi**:
  Pembedaan 3 keadaan deterministik di dalam `_upsertTrees()`:
  1. *QR field tidak dikirimkan* (`!hasQrTokenKey && !hasQrCodeKey`): Nilai `qr_token` lokal dari SQLite dipertahankan.
  2. *QR tersedia dan aktif* (`qr_code.is_active == true` atau `qr_token` bertipe string): Nilai token aktif baru disimpan ke SQLite.
  3. *QR tersedia dan secara eksplisit tidak aktif* (`qr_code.is_active == false` atau `qr_token: null` atau `qr_code: null`): Nilai kolom `qr_token` lokal diatur menjadi `null` tanpa fallback ke token lama.
- **Status**: **VERIFIED & TESTED** (`offline_sync_repository_test.dart` passing).

### 3.6. Git Hygiene & Build Artifacts
- **File**: [`.gitignore`](file:///.gitignore), [`mobile/.gitignore`](file:///mobile/.gitignore)
- **Implementasi**:
  - Ditambahkan pola `/mobile/build/`, `/mobile/android/build/`, `/mobile/android/app/build/`, `/mobile/android/.gradle/`, dan `.kotlin/`.
  - Folder `mobile/android/build/` telah dihapus secara bersih.
  - `git diff --check` bersih (exit code 0).
- **Status**: **VERIFIED & CLEAN**.

---

## 4. HASIL PENGUJIAN AKTUAL

### 4.1. Laravel Pint Formatter
```bash
vendor/bin/pint --test
```
**Output**:
```json
{"tool":"pint","result":"passed"}
```
*Hasil*: **0 pelanggaran gaya kode PHP.**

### 4.2. Backend PHPUnit Feature & Unit Test Suite
```bash
php artisan test
```
**Output**:
```json
{"tool":"phpunit","result":"passed","tests":152,"passed":152,"assertions":1130,"duration_ms":25473}
```
*Hasil*: **152 tests lulus (1.130 assertions), 0 failures, 0 errors.**
*Isolasi Database*: Seluruh pengujian berjalan pada database in-memory SQLite (`:memory:`) sesuai `phpunit.xml`. Database MariaDB development `durian_farm_db` sama sekali tidak tersentuh.

### 4.3. Flutter Static Analysis
```bash
cd mobile && flutter analyze
```
**Output**:
```
Analyzing mobile...
No issues found! (ran in 2.3s)
```
*Hasil*: **0 lint issues / static warnings.**

### 4.4. Mobile Flutter Test Suite
```bash
cd mobile && flutter test
```
**Output**:
```
00:00 +0: loading /Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/test/security_hardening_vuln_test.dart
...
00:03 +18: All tests passed!
```
*Hasil*: **18 tests lulus, 0 failures.**
*Cakupan Pengujian Mobile*:
- `mobile/test/security_hardening_vuln_test.dart` (6 tests: migrasi SQLite v1->v2, isolasi antrean multi-user, delta sync pagination & tombstones, stale lease recovery, secure vault fallback protection).
- `mobile/test/offline_sync_repository_test.dart` (11 tests: bootstrap QR, enqueue offline activity, logout case 1-7, delta sync since, secure storage migration, QR token 3-state semantics).
- `mobile/test/widget_test.dart` (1 test: UI rendering & unauthenticated redirect).

---

## 5. DAFTAR PERUBAHAN GIT (STATISTIK & FILE LIST)

### 5.1. Ringkasan Perubahan (`git diff --stat`)
Total: **29 file termodifikasi, 5 file baru (untracked)**.
Penambahan baris: **2.295 insertions**, Pengurangan baris: **398 deletions**.

```
 .gitignore                                         |   8 +
 app/Console/Commands/PruneSoftDeletedRecordsCommand.php    | 113 +++-
 app/Filament/Pages/TrashBin.php                    | 239 ++++++-
 app/Filament/Resources/Blocks/BlockResource.php    |  40 +-
 app/Filament/Resources/Gardens/GardenResource.php  |  65 +-
 app/Filament/Resources/Roles/RoleResource.php      |  57 +-
 app/Filament/Resources/Trees/TreeResource.php      |  51 +-
 app/Filament/Resources/Users/UserResource.php      |  41 +-
 app/Http/Controllers/Api/V1/SyncController.php     |   3 +-
 app/Http/Controllers/Api/V1/UserController.php     |  36 +-
 app/Http/Requests/Api/V1/SyncPullRequest.php       |   1 +
 app/Models/Tree.php                                |  17 +
 app/Providers/AppServiceProvider.php               |  35 +
 app/Services/SyncEngineService.php                 | 487 +++++++++++---
 config/durian.php                                  |   3 +
 mobile/.gitignore                                  |   4 +
 mobile/lib/data/local/local_database.dart          |  85 ++-
 mobile/lib/data/models/models.dart                 |  22 +
 mobile/lib/data/repositories/offline_sync_repository.dart | 749 ++++++++++++++++-----
 mobile/lib/data/services/api_client.dart           |   4 +
 mobile/lib/data/services/session_storage.dart      | 220 ++++--
 mobile/lib/main.dart                               |   5 +
 mobile/pubspec.lock                                |  96 +++
 mobile/pubspec.yaml                                |   1 +
 mobile/test/offline_sync_repository_test.dart      | 211 +++++-
 mobile/test/widget_test.dart                       |   2 +
 routes/api.php                                     |   3 +-
 routes/console.php                                 |   1 +
 tests/Feature/OfflineSyncEngineTest.php            |  94 +++
 29 files changed, 2295 insertions(+), 398 deletions(-)
```

### 5.2. File Baru / Untracked (5 file)
1. [`app/Services/RoleHierarchyService.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/app/Services/RoleHierarchyService.php) — Layanan hirarki hak akses dan otorisasi role terproteksi.
2. [`mobile/test/security_hardening_vuln_test.dart`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/mobile/test/security_hardening_vuln_test.dart) — Test suite pengujian VULN-01, VULN-03, VULN-05, VULN-07 pada mobile.
3. [`tests/Feature/RoleResourceSecurityTest.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/tests/Feature/RoleResourceSecurityTest.php) — Test suite validasi server-side form permission RoleResource Filament.
4. [`tests/Feature/SecurityHardeningVulnRemediationTest.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/tests/Feature/SecurityHardeningVulnRemediationTest.php) — Test suite komprehensif VULN-01 sampai VULN-08 pada backend Laravel.
5. [`tests/Feature/TrashBinSecurityTest.php`](file:///Applications/XAMPP/xamppfiles/htdocs/durian-farm-api/tests/Feature/TrashBinSecurityTest.php) — Test suite proteksi allowlist, dependency guard, dan audit trail TrashBin Filament.

---

## 6. VERIFIKASI KEAMANAN & INTEGRITAS SISTEM

1. **Pemeriksaan File Rahasia**:
   Pemeriksaan menyeluruh terhadap `git diff` memastikan tidak ada secret, private key, token autentikasi riil, API key eksternal, atau file `.env` yang terbawa.
2. **Kesesuaian Lingkup**:
   Seluruh 34 file yang termodifikasi dan bertambah berkaitan 100% dengan kebutuhan mitigasi keamanan, penegakan integritas data, penyelarasan sinkronisasi offline, dan penambahan automated tests. Tidak ada refactoring di luar lingkup.
3. **Integritas Database**:
   Tidak ada migration destruktif yang dijalankan pada environment MariaDB. Seluruh migration bersifat *additive* dan kompatibel ke belakang.
4. **Semantik QR Token**:
   Telah diuji dan diverifikasi membedakan 3 keadaan (omitted $\to$ retain, active $\to$ update, inactive/null $\to$ clear), menutup seluruh celah retensi token usang pada SQLite lokal.

---

## 7. TEMUAN TERSISA & RISIKO YANG BELUM DAPAT DIVERIFIKASI

- **Temuan Tersisa**: **NIHIL (0 open vulnerability)**. Seluruh temuan VULN-01 s/d VULN-08 serta observasi audit pasca-remediasi telah diselesaikan.
- **Risiko Lingkungan di Luar Sandbox**:
  1. *Hardware/Physical Device Keychain*: Pengujian Flutter dijalankan menggunakan environment test desktop mac dengan mock platform storage dan SQLite FFI. Perilaku `flutter_secure_storage` pada perangkat fisik Android (Android Keystore / EncryptedSharedPreferences) dan iOS (Keychain Services) perlu diverifikasi melalui smoke test saat instalasi APK/IPA pertama kali di perangkat fisik.
  2. *Koneksi Jaringan Seluler Lemah*: Seluruh alur retry dan timeout telah teruji secara deterministik melalui mock HTTP client, namun pengujian lapangan (field testing) dengan latensi tinggi tetap dianjurkan sebelum rilis publik penuh.

---

## 8. KESIMPULAN AKHIR

```
================================================================================
VERDICT: READY TO COMMIT
================================================================================
- VULN-01 sampai VULN-08: RESOLVED & VERIFIED (100%)
- Post-Audit Fixes & Micro-Fixes: RESOLVED & VERIFIED (100%)
- Backend Tests: 152 PASSED (1130 assertions)
- Mobile Tests: 18 PASSED (0 failures)
- Static Code Analysis & Formatter: 0 ISSUES
- Git Hygiene: CLEAN (0 whitespace issues, no build artifacts, no secrets)
================================================================================
```

*Working directory saat ini berada dalam kondisi aman, konsisten, dan siap untuk dilakukan commit oleh pengguna.*
*(Sesuai aturan kerja, proses dihentikan di sini — STOP).*
