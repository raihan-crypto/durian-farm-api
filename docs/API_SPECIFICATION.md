# API SPECIFICATION
## Sistem Informasi Manajemen Perkebunan Durian

- Versi API: `/api/v1`
- Format: JSON
- Authentication: Laravel Sanctum untuk endpoint terlindungi
- Status: kontrak awal; endpoint aktual harus diverifikasi dengan `php artisan route:list -v --path=api/v1`.

## 1. Standar Umum

### Base path
`/api/v1`

### Header
- `Accept: application/json`
- `Content-Type: application/json` untuk payload JSON
- `Authorization: Bearer {token}` untuk endpoint token-authenticated

### Response sukses (contoh)
```json
{
  "success": true,
  "message": "Data berhasil diambil",
  "data": {}
}
```

### Response validasi (contoh)
```json
{
  "success": false,
  "message": "Data tidak valid",
  "errors": {
    "name": ["Nama wajib diisi."]
  }
}
```

Bentuk response final harus mengikuti implementasi `ApiResponse` dan API Resource proyek. Jangan mengubah kontrak client tanpa versioning atau rencana migrasi.

## 2. Authentication

| Method | Endpoint | Tujuan | Akses |
|---|---|---|---|
| POST | `/auth/login` | Login dan memperoleh token | Publik + rate limit |
| POST | `/auth/logout` | Mencabut token aktif | Sanctum |
| GET | `/auth/me` | Profil pengguna saat ini | Sanctum |

Login harus memeriksa kredensial dan status aktif. Logout mencabut token yang sedang digunakan. Response tidak boleh mengandung password hash atau token yang tidak diperlukan.

## 3. Organisasi

| Method | Endpoint | Tujuan | Permission |
|---|---|---|---|
| GET | `/organizations` | Daftar organisasi sesuai scope | `organizations.view` |
| POST | `/organizations` | Membuat organisasi | `organizations.manage` |
| GET | `/organizations/{id}` | Detail organisasi | `organizations.view` |
| PUT/PATCH | `/organizations/{id}` | Memperbarui organisasi | `organizations.manage` |
| DELETE | `/organizations/{id}` | Menonaktifkan/menghapus sesuai kebijakan | `organizations.manage` |

Akses lintas organisasi hanya boleh untuk aktor yang secara eksplisit berwenang.

## 4. Users, Roles, Permissions

Endpoint yang telah dilaporkan tersedia:
| Method | Endpoint | Tujuan | Permission |
|---|---|---|---|
| GET | `/users` | Daftar pengguna | `users.view` |
| POST | `/users` | Membuat pengguna | `users.manage` |
| GET | `/users/{id}` | Detail pengguna | `users.view` |
| PUT/PATCH | `/users/{id}` | Memperbarui pengguna | `users.manage` |
| DELETE | `/users/{id}` | Menonaktifkan/menghapus sesuai kebijakan | `users.manage` |
| GET | `/roles` | Daftar role | `roles.view` |
| GET | `/permissions` | Daftar permission | `roles.view` |

Endpoint pengelolaan role, permission, dan assignment role belum terkonfirmasi dalam route list yang pernah dibagikan. Jika belum ada, rancang endpoint terotorisasi seperti:
- `POST /roles`
- `PUT /roles/{id}`
- `POST /roles/{id}/permissions`
- `POST /users/{id}/roles`

Endpoint usulan tersebut bukan klaim bahwa route sudah tersedia.

## 5. Endpoint Domain yang Direncanakan

Route domain berikut adalah rancangan kontrak, bukan daftar endpoint yang sudah terbukti tersedia. Sesuaikan nama controller, resource, dan permission dengan implementasi.

### Wilayah dan penugasan
- `GET|POST /villages`
- `GET|PUT|DELETE /villages/{id}`
- `GET|POST /blocks`
- `GET|PUT|DELETE /blocks/{id}`
- `GET|POST /gardens`
- `GET|PUT|DELETE /gardens/{id}`
- `POST /gardens/{id}/owners`
- `POST /gardens/{id}/farmers`
- `POST /blocks/{id}/farmers`

### Pohon dan pengukuran
- `GET|POST /durian-varieties`
- `GET|POST /trees`
- `GET|GET /trees/{id}`
- `PUT|PATCH /trees/{id}`
- `GET|POST /trees/{id}/measurements`
- `GET /trees/{id}/history`
- `GET /trees/{id}/qr`

### Aktivitas
- `GET|POST /fertilization-records`
- `GET|POST /spraying-records`
- `GET|POST /flowering-records`
- `GET|POST /fruit-development-records`
- `POST /activity-photos`

### Panen
- `GET|POST /harvest-seasons`
- `GET|POST /harvest-sessions`
- `GET|POST /harvest-records`
- `GET /harvest-reports`

### Mobile sync
- `GET /mobile/bootstrap`
- `GET /mobile/sync/pull?cursor={cursor}`
- `POST /mobile/sync/push`
- `GET /mobile/sync/status/{batch_id}`

### Dashboard dan integrasi
- `GET /dashboard/summary`
- `GET /weather`
- endpoint IoT hanya ditambahkan setelah protokol dan otorisasi perangkat ditetapkan.

## 6. Aturan API

- Semua endpoint domain terlindungi harus memeriksa autentikasi, permission, organisasi, dan penugasan yang relevan.
- Gunakan pagination untuk koleksi.
- Gunakan filter dan sort yang diizinkan secara eksplisit.
- Validasi nested resource agar relasinya berada dalam organisasi dan parent yang sama.
- Gunakan HTTP status yang konsisten: 200, 201, 204, 400, 401, 403, 404, 409, 422, 429, 500.
- Jangan mengungkap keberadaan data tenant lain melalui pesan error yang berbeda jika hal itu menimbulkan risiko enumerasi.
- Endpoint login harus memiliki rate limiting.
- Operasi yang dapat terkirim ulang perlu idempotency strategy.

## 7. Pagination dan Filter

Contoh:
`GET /trees?page=1&per_page=25&garden_id={uuid}&status=productive`

Batasi `per_page` maksimum. Validasi filter dan sort; jangan langsung memasukkan input pengguna ke nama kolom SQL.

## 8. Kontrak Sinkronisasi Mobile

Push harus membawa client-generated UUID/idempotency key, tipe operasi, entitas, payload tervalidasi, dan metadata versi. Server mengembalikan hasil per item: accepted, rejected, conflict, atau retryable error.

Pull harus memakai cursor yang stabil dan mengembalikan perubahan sesuai scope pengguna. Jangan mengirim data di luar penugasan/organisasi.

## 9. OpenAPI

Buat file OpenAPI 3.x setelah route dan request/response aktual diverifikasi. Dokumentasi harus menyertakan skema request, response, status error, auth scheme, permission, pagination, dan contoh. Jangan menandai endpoint rancangan sebagai implemented.
