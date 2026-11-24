# RSK-02 REMEDIATION REPORT: RBAC PRIVILEGE ESCALATION
**Target Vulnerability:** RSK-02 (P1: Excessive Permissions on `petani` Role)\
**Date:** 2026-11-24\
**Status:** **REMEDIATED & VERIFIED**

---

## 1. Vulnerability & Root Cause
- In `RbacSeeder.php` and `RoleRedesignSeeder.php`, the `petani` role was assigned `territory.manage`, `ownership.manage`, and `trees.manage`.
- Because `territory.manage` was monolithic (bundled Villages, Blocks, Gardens, Weather, and IoT), `petani` users could create, edit, and delete Villages and Blocks, approve/reject municipal requests, and trigger weather/IoT mutations.
- `ownership.manage` enabled `petani` to mutate global `Owner` and `Farmer` master records via API.
- `trees.manage` enabled `petani` to mutate botanical `DurianVariety` master data.

## 2. Remediation Implemented
1. **Granular Permissions Introduced:**
   - Added `gardens.manage` ("Mengelola data kebun dan penugasan tenaga kerja kebun").
   - Added `varieties.manage` ("Mengelola master varietas durian").
2. **Role Permissions Realignment (`RbacSeeder.php` & `RoleRedesignSeeder.php`):**
   - Removed `territory.manage` from `petani`.
   - Added `gardens.manage` to `petani`.
   - Removed `ownership.manage` from `petani`.
   - `varieties.manage` assigned exclusively to `super_admin` and `admin`.
3. **Route Middleware Hardening (`routes/api.php`):**
   - Protected `/gardens` write operations with `permission:gardens.manage,territory.manage`.
   - Protected `/gardens/{id}/farmers` with `permission:gardens.manage,ownership.manage`.
   - Protected `/durian-varieties` write operations with `permission:varieties.manage`.
   - Added role guard on `BlockController::approve` and `reject` requiring `super_admin` or `admin`.
4. **Filament Resource Hardening:**
   - `VillageResource`: Restricted create/edit/delete explicitly to administrators with `territory.manage`.
   - `BlockResource`: Restricted create/delete to admins (`territory.manage`) or village officers (`blocks.request`), and edit exclusively to admins.
   - `GardenResource`: Aligned permissions with `gardens.manage` and strictly checked owner profile on edit/delete.
   - `DurianVarietyResource`: Switched create/edit/delete gates to `varieties.manage`.

## 3. Verification & Evidence
- **Dedicated Negative Test Suite:** `tests/Feature/RbacPrivilegeEscalationTest.php` (8 tests, 20 assertions, all PASSED).
- **Full Test Suite:** 106 tests, 894 assertions (100% PASSED).
- **Code Formatting:** Laravel Pint passed.
