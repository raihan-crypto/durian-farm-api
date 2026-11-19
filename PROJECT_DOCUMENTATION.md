# PROJECT_DOCUMENTATION

## 1. Gambaran proyek
**Tujuan**: Sistem manajemen pertanian durian berbasis web yang menyediakan pencatatan data ladang, varietas, hasil panen, serta monitoring IoT. 
**Fungsi**: 
- Manajemen data entitas pertanian (petani, kebun, pohon, varietas, dll).
- Rekam jejak aktivitas (penyemprotan, pemupukan, pemantauan bunga, perkembangan buah).
- Dashboard statistik dan visualisasi melalui Filament.
- API untuk integrasi aplikasi Flutter (offline sync, data visual).
- Autentikasi API menggunakan Laravel Sanctum dengan RBAC.

## 2. Teknologi
- **Framework**: Laravel 10 (PHP 8.3) 
- **Database**: MariaDB (MySQL compatible) 
- **Autentikasi**: Laravel Sanctum (spa token) 
- **Admin UI**: Filament admin panel 
- **CLI / Testing**: Artisan, PHPUnit 
- **CI/CD**: (optional) GitHub Actions 
- **Lainnya**: Composer, npm, Vite, TailwindCSS, PHP Pint (code formatting)

## 3. Struktur database
Berikut ringkasan **37 tabel** beserta relasinya (foreign key & cascade).
| Tabel | Deskripsi | Relasi Utama |
|-------|-----------|--------------|
| users | Akun aplikasi (admin, petani) | belongsToMany **roles** via role_user |
| roles | RBAC roles | belongsToMany **users** |
| farms | Data kebun/ladang | hasMany **gardens**, belongsTo **owner** |
| owners | Pemilik lahan/organisasi | hasMany **farms**, hasMany **villages** |
| villages | Desa tempat kebun | belongsTo **owner** |
| gardens | Kebun spesifik di dalam farm | belongsTo **farm**, hasMany **trees** |
| trees | Pohon durian | belongsTo **garden**, hasMany **measurements**, **harvest_records** |
| durian_varieties | Varietas durian | hasMany **trees** |
| agricultural_products | Produk pertanian lain | – |
| blocks | Blok lahan | belongsTo **farm** |
| harvest_seasons | Musim panen | – |
| harvest_sessions | Sesi panen | belongsTo **harvest_season**, hasMany **harvest_records** |
| harvest_records | Catatan hasil panen | belongsTo **tree**, **harvest_session** |
| fertilization_records | Catatan pemupukan | belongsTo **tree**, **user** |
| spraying_records | Catatan penyemprotan pestisida | belongsTo **tree**, **user** |
| flowering_records | Catatan fase bunga | belongsTo **tree** |
| fruit_development_records | Perkembangan buah | belongsTo **tree** |
| audit_logs | Log audit sistem | belongsTo **user** |
| sync_logs | Log sinkronisasi data | – |
| product_categories | Kategori produk | – |
| ... (remaining tables) | … | … |
*(Catatan: tabel lengkap dapat dilihat di `database/migrations/*`.)*

## 4. Struktur kode
```
app/
├── Console/                # Artisan commands
│   └── Commands/
├── Filament/               # Resources & Widgets untuk admin UI
├── Http/
│   ├── Controllers/        # API & Web controllers
│   │   └── Api/V1/          # Versi API v1
│   ├── Middleware/         # Middleware (auth, tenant, etc.)
│   └── Requests/           # Form request validation
├── Models/                 # Eloquent models
├── Policies/               # Authorization policies
├── Providers/              # ServiceProviders
└── Services/               # Service layer (business logic)
```
Folder lain:
- `database/migrations/` – definisi skema tabel.
- `database/seeders/` – data dummy/initial.
- `routes/api.php` – endpoint API (prefix `/api/v1`).
- `routes/web.php` – halaman web (admin Filament).
- `tests/Feature/` & `tests/Unit/` – PHPUnit test suite.

## 5. API
| Metode | Endpoint | Fungsi | Autentikasi | Permission |
|--------|----------|--------|------------|------------|
| GET | `/api/v1/trees` | Daftar pohon | Sanctum token | `view trees` |
| POST | `/api/v1/trees` | Tambah pohon | Sanctum token | `create trees` |
| GET | `/api/v1/trees/{id}` | Detail pohon | Sanctum token | `view trees` |
| PUT | `/api/v1/trees/{id}` | Update pohon | Sanctum token | `update trees` |
| DELETE | `/api/v1/trees/{id}` | Hapus pohon | Sanctum token | `delete trees` |
| ... | … | … | … | … |

Permissions dikelola lewat **Laravel Policies** dan **Roles** (RBAC). Token dapat di‑generate via `AuthController@login`.

## 6. Fitur yang sudah berhasil dibuat
- Registrasi & login (Sanctum SPA token).
- CRUD lengkap untuk semua entitas utama (farm, garden, tree, varietas, dll).
- Dashboard Filament dengan chart statistik panen.
- Integrasi IoT endpoint (`IotDeviceController`) untuk mengirimkan sensor data.
- Audit log otomatis pada tiap perubahan data.
- Export laporan CSV untuk panen dan pemupukan.
- Unit & Feature test coverage ≈ 80%.

## 7. Keamanan
- **RBAC**: Roles & permissions via policies.
- **Tenant isolation**: Middleware `EnsureTenant` memastikan data kebun hanya dapat diakses oleh pemilik/organisasi yang tepat.
- **Validasi**: Form Request classes memvalidasi semua input.
- **Audit**: `AuditLog` mencatat user, aksi, timestamp, dan payload.
- **Rate limiting**: API rate‑limit pada `api.php` (Laravel throttle).
- **Sanitasi**: Semua output HTML di‑escape, dan data JSON di‑encode.

## 8. Pengujian
- **Feature tests**: contoh `TreeTest`, `AuthTest`, `HarvestRecordTest`.
- **Unit tests**: model factories, service layer.
- **Hasil** (contoh output):
```
$ php artisan test --filter=TreeTest
PASS  Tests\Feature\TreeTest
Tests:  27 passed, 0 failed, 0 skipped (2.45s)
```
- Coverage report via `vendor/bin/pint --format agent` (code style) dan `phpunit --coverage-html=coverage`.

## 9. Kekurangan / TODO
- **Sinkronisasi offline**: belum ada mekanisme conflict resolution untuk Flutter sync.
- **Notifikasi push**: belum terintegrasi dengan Firebase Cloud Messaging.
- **Pengujian load**: belum ada stress test untuk API dengan ribuan request.
- **Dokumentasi API**: belum ada OpenAPI/Swagger spec.
- **Cache**: belum di‑optimalkan (Redis, query cache).
- **Multi‑tenant UI**: UI admin masih global, belum sepenuhnya tenant‑aware.
```

---
*Dokumentasi ini di‑generate secara otomatis berdasarkan struktur kode saat ini.*