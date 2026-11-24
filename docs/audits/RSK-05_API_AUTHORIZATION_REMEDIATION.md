# RSK-05 REMEDIATION REPORT: API CONTROLLER AUTHORIZATION HARDENING
**Target Vulnerability:** RSK-05 (P1: Missing Scoped Authorization and IDOR Risk in Territory and IoT Controllers)\
**Date:** 2026-11-24\
**Status:** **REMEDIATED & VERIFIED**

---

## 1. Vulnerability & Root Cause
- Several API controllers in `app/Http/Controllers/Api/V1/` relied on permission middleware (`permission:territory.manage`, `permission:gardens.manage`) without verifying the calling user's territorial boundary or assignment scope (`TerritoryScopeService`).
- **Identified Gaps:**
  1. `VillageController@update` and `@destroy`: Did not verify `$this->territoryScope->canAccessVillage($request->user(), $village->id)`.
  2. `BlockController@store`, `@update`, and `@destroy`:
     - `@store`: Allowed creating blocks in arbitrary villages without verifying `$this->territoryScope->canAccessVillage()`.
     - `@update`: Allowed updating blocks without verifying `$this->territoryScope->canAccessBlock()` or moving blocks into unauthorized villages.
     - `@destroy`: Did not check `canAccessBlock()` before deletion.
     - Farmer assignments on blocks (`assignFarmer`, `updateFarmerAssignment`, `removeFarmerAssignment`) lacked block scoping checks.
  3. `GardenController@store`, `@update`, and `@destroy`:
     - `@store`: Allowed creating gardens in arbitrary blocks without verifying `$this->territoryScope->canAccessBlock()`.
     - `@update`: Allowed modifying gardens without checking `$this->territoryScope->canAccessGarden()` or moving them to unauthorized blocks.
     - `@destroy`: Did not check `canAccessGarden()` before deletion.
     - Ownership management (`assignOwner`, `updateOwner`, `removeOwner`) and Farmer assignments (`assignFarmer`, `updateFarmerAssignment`, `removeFarmerAssignment`) lacked `canAccessGarden()` verification.
  4. `MonitoringAreaController@update` and `@destroy`: Did not verify `canAccessGarden()` on the current garden or target garden during updates and deletions.
  5. `IotDeviceController@store` and `@update`: Did not verify that the user had access to the garden of the `monitoring_area_id`.

## 2. Remediation Implemented
1. **VillageController Hardening:**
   - Injected `canAccessVillage()` checks on `update()` and `destroy()`, rejecting unauthorized access with `403 Forbidden`.
2. **BlockController Hardening:**
   - Verified `canAccessVillage()` on `store()`.
   - Verified `canAccessBlock()` on `update()` and `destroy()`, as well as on target village changes.
   - Enforced `canAccessBlock()` on `assignFarmer()`, `updateFarmerAssignment()`, and `removeFarmerAssignment()`.
3. **GardenController Hardening:**
   - Verified `canAccessBlock()` on `store()`.
   - Verified `canAccessGarden()` on `update()` and `destroy()`, as well as on target block changes.
   - Enforced `canAccessGarden()` on all owner management (`assignOwner`, `updateOwner`, `removeOwner`) and farmer management (`assignFarmer`, `updateFarmerAssignment`, `removeFarmerAssignment`) endpoints.
4. **MonitoringAreaController Hardening:**
   - Enforced `canAccessGarden()` on `update()` and `destroy()`.
   - Verified access to new garden when updating `garden_id`.
5. **IotDeviceController Hardening:**
   - Verified user access to the parent garden of `monitoring_area_id` on `store()` and `update()`.
   - Verified access to new monitoring area when updating `monitoring_area_id`.

## 3. Verification & Evidence
- **Dedicated Test Suite:** `tests/Feature/ApiControllerAuthorizationHardeningTest.php` (9 tests, 24 assertions, 100% PASSED).
  - Verified user cannot update or delete unauthorized villages (403).
  - Verified user cannot create, update, or move blocks to unauthorized villages (403).
  - Verified user cannot delete unauthorized blocks or assign farmers to unauthorized blocks (403).
  - Verified user cannot create gardens in unauthorized blocks (403).
  - Verified user cannot update or delete unauthorized gardens (403).
  - Verified user cannot assign, update, or delete owners/farmers on unauthorized gardens (403).
  - Verified user cannot create, update, or delete monitoring areas or register IoT devices on unauthorized gardens (403).
  - Verified authorized IoT device creation succeeds (201).
  - Verified admin retains unrestricted access across all territories and IoT devices (200).
- **Full Regression Test Suite:** All 125 tests passed (959 assertions passed).
- **Code Style:** Formatted cleanly with Laravel Pint.
