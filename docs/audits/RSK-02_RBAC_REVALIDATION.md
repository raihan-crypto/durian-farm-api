# RSK-02 — RBAC SECURITY REVALIDATION REPORT
## Excessive `territory.manage` Permission on `petani` Role

**Document Reference:** `docs/audits/RSK-02_RBAC_REVALIDATION.md`\
**Date:** 2026-11-24\
**Project:** Sistem Informasi Manajemen Perkebunan Durian\
**Target Vulnerability:** RSK-02 (P1: Privilege Escalation & Excessive Permissions on `petani` Role)\
**Review Team:** Principal Software Architect, Senior Laravel Security Engineer\
**Status:** **AUDIT COMPLETE (READ-ONLY INVESTIGATION)**

---

## 1. EXECUTIVE SUMMARY

During the enterprise security audit of the Durian Farm Management System, vulnerability **RSK-02** was identified: the `petani` (farm manager/garden owner) role was assigned the high-privilege permission `territory.manage`.

This comprehensive investigation confirms that:
1. **The vulnerability is VERIFIED and presents severe privilege escalation risks.**
2. A user with role `petani` can create, update, and delete **Villages (Desa)** and **Blocks (Blok)** across the entire system.
3. A `petani` can approve or reject block addition/deletion requests submitted by local village governments (`pemerintah_desa`).
4. A `petani` can mutate or delete **Gardens (Kebun)** belonging to other farmers without ownership validation in API write endpoints.
5. In addition to `territory.manage`, `petani` is also assigned **`ownership.manage`** and **`trees.manage`**, allowing farmers to create/delete system-wide `DurianVariety` (varietas durian) master data and mutate `Owner` and `Farmer` profiles via REST API.
6. **Authorization enforcement is inconsistent:** Filament UI applies ad-hoc role restrictions on some resources (e.g. `OwnerResource`), but the REST API does not enforce these checks, allowing users to bypass UI boundaries via direct API calls.
7. **Zero automated tests** currently verify that `petani` is blocked from unauthorized village, block, or master data mutations.

---

## 2. PHASE 1 — CODEBASE & ARCHITECTURAL INVESTIGATION

### 2.1 Technology Stack & RBAC Architecture
- **Laravel Framework:** `v13.43.0` running on PHP `8.3.14`.
- **RBAC Package:** **Custom Eloquent Implementation**. Spatie Laravel Permission is **not used**.
  - Database tables: `roles`, `permissions`, `user_roles`, `role_permissions`.
  - Models: `App\Models\Role`, `App\Models\Permission`, `App\Models\User`.
  - Methods on `User`: `hasRole(string $roleName)`, `hasPermission(string $permissionName)`.
  - Middleware: `App\Http\Middleware\CheckPermission` mapped to `permission` alias in `bootstrap/app.php`.
  - Policies: **No Laravel Policies exist** (`app/Policies/` is not implemented).
  - Gates: **No Gates exist** in `AppServiceProvider`. Authorization relies entirely on route middleware and Filament `can*` methods.

### 2.2 Trace of Root Cause: Why did `petani` receive `territory.manage`?
In `database/seeders/RbacSeeder.php` (lines 82–105) and `database/seeders/RoleRedesignSeeder.php` (lines 48–72):
```php
// Role description:
$petani = Role::firstOrCreate(
    ['name' => 'petani'],
    ['description' => 'Petani (Pengelola Kebun) yang memiliki/mengelola kebun, menambahkan kebun dan tenaga kerja']
);
```
- **Business Goal:** The system designers intended for `petani` to be a garden manager who could add and manage their own gardens ("menambahkan kebun") and assign farm laborers ("tenaga kerja").
- **Architectural Flaw:** The permission catalog is coarse-grained. There is **no granular `gardens.manage` permission**. Instead, line 26 of `RbacSeeder.php` bundles everything under a single permission:
  ```php
  ['name' => 'territory.manage', 'description' => 'Mengelola wilayah, blok, dan kebun']
  ```
- **Unintended Consequence:** To allow garden creation, the developers granted `territory.manage` to `petani`. Because the same permission guards Villages, Blocks, Weather, and IoT devices, `petani` was inadvertently granted administrative powers over the entire geographic hierarchy.

---

### 2.3 Exposed Operations via `territory.manage`

#### A. REST API Endpoints Exposed to `petani` (17 Endpoints)
All endpoints below are guarded exclusively by `middleware('permission:territory.manage')`:

| HTTP Method | Route Endpoint | Controller Action | Security Impact for `petani` |
|---|---|---|---|
| **POST** | `/api/v1/villages` | `VillageController@store` | **CRITICAL:** Farmer can create new village administrative entities. |
| **PUT** | `/api/v1/villages/{id}` | `VillageController@update` | **CRITICAL:** Farmer can modify any village name/district/province. |
| **DELETE** | `/api/v1/villages/{id}` | `VillageController@destroy` | **CRITICAL:** Farmer can delete any village with zero blocks. |
| **POST** | `/api/v1/blocks` | `BlockController@store` | **HIGH:** Farmer creates block with immediate `approved` status (bypassing village approval). |
| **PUT** | `/api/v1/blocks/{id}` | `BlockController@update` | **HIGH:** Farmer can edit any block in any village. |
| **DELETE** | `/api/v1/blocks/{id}` | `BlockController@destroy` | **HIGH:** Farmer can delete any block with zero gardens. |
| **POST** | `/api/v1/blocks/{id}/approve` | `BlockController@approve` | **CRITICAL:** Farmer can approve village government block addition/deletion requests. |
| **POST** | `/api/v1/blocks/{id}/reject` | `BlockController@reject` | **CRITICAL:** Farmer can reject village government requests. |
| **POST** | `/api/v1/gardens` | `GardenController@store` | **HIGH:** Farmer can create a garden in any block without validation. |
| **PUT** | `/api/v1/gardens/{id}` | `GardenController@update` | **CRITICAL:** Farmer can modify *any* garden (no ownership check in controller). |
| **DELETE** | `/api/v1/gardens/{id}` | `GardenController@destroy` | **CRITICAL:** Farmer can delete *any* garden with zero trees. |
| **POST** | `/api/v1/weather/fetch` | `WeatherController@fetch` | **MEDIUM:** Farmer can trigger upstream weather API sync calls. |
| **POST** | `/api/v1/monitoring-areas` | `MonitoringAreaController@store` | **MEDIUM:** Farmer can create IoT monitoring areas. |
| **PUT** | `/api/v1/monitoring-areas/{id}` | `MonitoringAreaController@update` | **MEDIUM:** Farmer can update IoT monitoring areas. |
| **DELETE** | `/api/v1/monitoring-areas/{id}` | `MonitoringAreaController@destroy` | **MEDIUM:** Farmer can delete IoT monitoring areas. |
| **POST** | `/api/v1/iot-devices` | `IotDeviceController@store` | **MEDIUM:** Farmer can register hardware IoT devices. |
| **PUT** | `/api/v1/iot-devices/{id}` | `IotDeviceController@update` | **MEDIUM:** Farmer can update hardware IoT device configurations. |

#### B. Filament Admin Panel Authorization
- `User::canAccessPanel(Panel $panel)` explicitly permits `'petani'` to enter the Filament panel:
  ```php
  return $this->roles->pluck('name')->intersect([
      'super_admin', 'admin', 'petani', 'pemerintah_desa', 'owner', 'plantation_manager'
  ])->isNotEmpty();
  ```
- **`VillageResource.php`:**
  - `canCreate()`: checks `hasPermission('territory.manage')` -> **Allowed for `petani`**.
  - `canEdit()`: checks `hasPermission('territory.manage')` -> **Allowed for `petani`**.
  - `canDelete()`: checks `hasPermission('territory.manage')` -> **Allowed for `petani`**.
  - `getEloquentQuery()`: No scoping applied.
- **`BlockResource.php`:**
  - `canCreate()`: checks `territory.manage` or `blocks.request` -> **Allowed for `petani`**.
  - `canEdit()`: checks `territory.manage` -> **Allowed for `petani`**.
  - `canDelete()`: checks `territory.manage` or `blocks.request` -> **Allowed for `petani`**.
- **`GardenResource.php`:**
  - `canCreate()`: checks `territory.manage` -> **Allowed for `petani`**.
  - `canDelete()`: checks `territory.manage` -> **Allowed for `petani`** (can delete any garden).

---

### 2.4 Questions Answered

#### 1. Why does the `petani` role receive `territory.manage`?
Because the initial RBAC design lacked a dedicated `gardens.manage` permission. To enable farmers to manage their own gardens, developers assigned the monolithic `territory.manage` permission to `petani`.

#### 2. Which operations become accessible because of this permission?
All 17 API endpoints listed in Section 2.3 and full CRUD actions in Filament for `VillageResource`, `BlockResource`, and `GardenResource`.

#### 3. Can a farmer create, edit, or delete villages?
**YES.** Both the REST API (`VillageController`) and Filament (`VillageResource`) grant create, update, and delete capabilities to `petani`. The controller methods perform zero role or ownership checks.

#### 4. Can a farmer create, edit, or delete blocks?
**YES.** In API, a farmer creates blocks that are immediately marked `approved`, updates any block, deletes unlinked blocks, and can even execute administrative `approve` / `reject` actions on block requests submitted by village officials.

#### 5. Are permissions enforced consistently in API and Filament?
**NO.**
- In `OwnerResource.php`, Filament restricts management to `super_admin` or `admin`. However, the API route `/api/v1/owners` only checks `ownership.manage`, which `petani` holds.
- In `GardenResource.php`, Filament attempts to check `$record->owners()` on edit, but API `GardenController@update` has **zero** ownership checks.
- In `BlockResource.php`, block approvals are handled in Filament, while in API `approve`/`reject` routes are exposed to anyone with `territory.manage`.

#### 6. Can users bypass UI restrictions by directly calling API endpoints?
**YES.** Any action blocked in the Filament frontend (such as modifying `owners` or editing another farmer's garden) can be executed directly by issuing HTTP requests with the farmer's Sanctum Bearer token.

#### 7. Are there other excessive permissions assigned to the farmer role?
**YES:**
1. **`ownership.manage`:** Assigned to `petani`. Enables `petani` to create/update/delete `Owner` records, create/update/delete `Farmer` records, and manage farmer assignments on blocks.
2. **`trees.manage`:** Assigned to `petani`. Enables `petani` to create, edit, and delete global botanical `DurianVariety` records (`/api/v1/durian-varieties`), which are master data intended for agronomists/administrators.

---

## 3. PHASE 2 — SECURITY IMPACT & PERMISSION MATRIX

### 3.1 Role Clarification
*Note on `Supervisor`:* The prompt asked for a matrix covering Super Admin, Administrator, Supervisor, and Petani. An exhaustive search of the codebase, models, migrations, and seeders revealed that **`supervisor` DOES NOT EXIST** in this project. The actual roles in the database are:
1. `super_admin`
2. `admin`
3. `petani` (absorbed legacy `owner` and `plantation_manager`)
4. `tenaga_kerja` (absorbed legacy `farmer`)
5. `pemerintah_desa`

### 3.2 System Permission Matrix (Actual vs. Intended)

| Module / Resource | Action | Super Admin | Administrator | Petani (Current) | Petani (Intended) | Tenaga Kerja | Pemerintah Desa |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **Villages (Desa)** | Read | Unrestricted | Unrestricted | Unrestricted | Scoped (View Only) | Scoped (View Only) | Scoped (Own Village) |
| | Create / Edit / Delete | Allowed | Allowed | **ALLOWED (DEFECT)** | **DENIED** | Denied | Denied |
| **Blocks (Blok)** | Read | Unrestricted | Unrestricted | Unrestricted | Scoped (Assigned) | Scoped (Assigned) | Scoped (Own Village) |
| | Create / Edit / Delete | Allowed | Allowed | **ALLOWED (DEFECT)** | **DENIED** | Denied | Request Only (`blocks.request`) |
| | Approve / Reject Request | Allowed | Allowed | **ALLOWED (DEFECT)** | **DENIED** | Denied | Denied |
| **Gardens (Kebun)** | Read | Unrestricted | Unrestricted | Scoped (Own) | Scoped (Own) | Scoped (Assigned) | Scoped (Own Village) |
| | Create | Allowed | Allowed | **ALLOWED (All Blocks)** | **Scoped (Own Blocks)** | Denied | Denied |
| | Edit / Delete | Allowed | Allowed | **ALLOWED (All Gardens)** | **Scoped (Own Gardens Only)** | Denied | Denied |
| **Owners (Pemilik)** | Read | Unrestricted | Unrestricted | Allowed | Allowed | Denied | Denied |
| | Create / Edit / Delete | Allowed | Allowed | **ALLOWED (API Byp.)** | **DENIED** | Denied | Denied |
| **Farmers (Petani Lapangan)** | Read | Unrestricted | Unrestricted | Allowed | Allowed | Denied | Denied |
| | Create / Edit / Delete | Allowed | Allowed | **ALLOWED (All)** | **Scoped (Own Laborers)** | Denied | Denied |
| **Durian Varieties** | Read | Unrestricted | Unrestricted | Allowed | Allowed | Allowed | Denied |
| | Create / Edit / Delete | Allowed | Allowed | **ALLOWED (DEFECT)** | **DENIED (Admin Only)** | Denied | Denied |
| **Trees (Pohon)** | Read | Unrestricted | Unrestricted | Scoped (Own Gardens) | Scoped (Own Gardens) | Scoped (Assigned) | Denied |
| | Create / Edit / Delete | Allowed | Allowed | Scoped (Checked in Ctrl) | Scoped (Checked in Ctrl) | Denied | Denied |
| **Weather & IoT** | Trigger Fetch / Manage | Allowed | Allowed | **ALLOWED (DEFECT)** | **DENIED** | Denied | Denied |

---

## 4. PHASE 3 — TEST COVERAGE GAP ANALYSIS

### 4.1 State of Existing Tests
Inspection of the existing test suite reveals:
1. `tests/Feature/AuthAndRbacTest.php`: Tests basic authentication, token revocation, and tests that `farmer` (mobile worker) cannot view `/api/v1/roles`. **Does not test `petani` or territory mutations.**
2. `tests/Feature/TerritoryManagementTest.php`: Tests `super_admin` creating and deleting villages, blocks, and gardens, and tests that a field worker's GET request is scoped. **Does not test unauthorized POST/PUT/DELETE by `petani`.**
3. `tests/Feature/RoleRedesignAndNewFeaturesTest.php`: Tests `pemerintah_desa` block request workflow and `admin` approval. **Does not test whether `petani` can approve blocks or tamper with villages.**
4. `tests/Feature/FilamentAdminPanelTest.php`: Tests that a field worker cannot access the panel. **Does not test `petani` role permissions inside the panel.**

### 4.2 Required Negative Test Scenarios (Proposed)
To guarantee security, the following tests must be implemented once remediation is approved:

```text
[ ] test_petani_cannot_create_village_via_api (Expect 403)
[ ] test_petani_cannot_update_or_delete_village_via_api (Expect 403)
[ ] test_petani_cannot_create_or_delete_block_via_api (Expect 403)
[ ] test_petani_cannot_approve_or_reject_block_requests (Expect 403)
[ ] test_petani_cannot_update_or_delete_garden_owned_by_another_user (Expect 403)
[ ] test_petani_cannot_create_update_or_delete_durian_varieties (Expect 403)
[ ] test_petani_cannot_manage_owners_via_direct_api (Expect 403)
[ ] test_petani_cannot_trigger_weather_fetch_or_configure_iot_devices (Expect 403)
[ ] test_petani_cannot_mount_create_edit_delete_actions_in_village_resource (Filament)
[ ] test_petani_cannot_mount_create_edit_delete_actions_in_block_resource (Filament)
```

---

## 5. PHASE 4 — EXPLOITATION SCENARIOS

### Scenario 1: Malicious or Accidental Village Deletion
1. Attacker authenticates with a compromised `petani` account.
2. Attacker sends `GET /api/v1/villages` to locate empty or newly created villages.
3. Attacker sends `DELETE /api/v1/villages/{id}`.
4. `CheckPermission` passes because the user has `territory.manage`.
5. `VillageController@destroy` executes `$village->delete()` without checking user tenant or role. The administrative village record is destroyed.

### Scenario 2: Rogue Block Approval & Bypassing Municipal Oversight
1. A Village Officer (`pemerintah_desa`) submits a block request via `POST /api/v1/blocks` (status `pending_addition`).
2. A `petani` user calls `POST /api/v1/blocks/{id}/approve`.
3. The API checks `permission:territory.manage` which `petani` possesses.
4. The request status is set to `approved` without any administrator or municipal authority review.

### Scenario 3: Cross-Tenant Garden Tampering via Direct API
1. Farmer Alpha logs in and inspects the API response for gardens.
2. Farmer Alpha sends `PUT /api/v1/gardens/{gardenBetaId}` changing the garden name, coordinates, or block assignment.
3. Filament UI would have blocked this, but the REST API controller does not verify ownership.
4. Garden Beta is overwritten with Farmer Alpha's payload.

---

## 6. PHASE 5 — RECOMMENDED REMEDIATION PLAN

### Step 1: Granular Permission Separation
1. Introduce granular permissions in the RBAC catalog:
   - `gardens.manage`: Allows creating and editing gardens.
   - `gardens.view`: Allows viewing gardens.
2. Update `database/seeders/RbacSeeder.php` and `database/seeders/RoleRedesignSeeder.php`:
   - **Revoke `territory.manage` from the `petani` role.**
   - Retain `territory.view` (or `gardens.view`).
   - Assign `gardens.manage` to `petani`.
   - Remove `ownership.manage` from `petani` (replace with scoped laborer management if needed).
   - Remove `trees.manage` from `petani` (split so `durian-varieties` requires `varieties.manage` held only by Admin).

### Step 2: Route & Controller Hardening
1. In `routes/api.php`:
   - Guard `/villages` write operations with `permission:territory.manage` AND role restriction (`super_admin`, `admin`).
   - Guard `/blocks` write operations with `permission:territory.manage` AND role restriction (`super_admin`, `admin`).
   - Guard `/blocks/{id}/approve` and `/reject` with explicit admin role checks.
   - Guard `/gardens` write operations with `permission:gardens.manage`.
2. In `GardenController.php`:
   - In `update()` and `destroy()`, add explicit ownership validation:
     ```php
     if (! $this->territoryScope->canAccessGarden($request->user(), $garden->id)) {
         return $this->errorResponse('Anda tidak memiliki izin untuk mengubah kebun ini', 403);
     }
     ```

### Step 3: Filament Resource Policy Alignment
1. In `VillageResource.php`:
   - Restrict `canCreate`, `canEdit`, and `canDelete` to `auth()->user()?->hasRole(['super_admin', 'admin'])`.
2. In `BlockResource.php`:
   - Restrict `canEdit` and `canDelete` to `auth()->user()?->hasRole(['super_admin', 'admin'])`.
3. In `GardenResource.php`:
   - Restrict `canEdit` and `canDelete` so that a `petani` can only act on records matching their `ownerProfile->id`.

---

## 7. POTENTIAL REGRESSION RISKS

| Component | Potential Risk | Mitigation |
|---|---|---|
| **Mobile App Synchronization** | If the mobile sync engine sends garden mutations under `petani`, removing `territory.manage` could reject sync payloads. | Verified: The mobile app currently only pushes activities (`sync_queue`) and photos (`photo_queue`). Mobile does NOT push village or block updates. |
| **Existing Garden Creation by Petani** | If `territory.manage` is removed without adding `gardens.manage`, farmers cannot register new gardens. | Add migration/seeder updating existing `petani` role permissions to include `gardens.manage`. |
| **Legacy Role Seeders** | Running `RoleRedesignSeeder` in staging/production could restore the vulnerability if not updated. | Update both `RbacSeeder.php` and `RoleRedesignSeeder.php` concurrently. |

---

## 8. CONCLUSION & NEXT STEPS

Vulnerability **RSK-02** is confirmed as a critical architectural authorization defect stemming from coarse-grained permissions.

Per instructions, **no modifications have been made to application code, seeders, or database tables.**

**Next Action:** Awaiting user approval to proceed with the minimal remediation implementation and corresponding security regression test suite.
