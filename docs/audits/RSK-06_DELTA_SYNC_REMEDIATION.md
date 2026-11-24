# RSK-06 REMEDIATION REPORT: MOBILE DELTA SYNC IMPLEMENTATION
**Target Vulnerability:** RSK-06 (P2: Unused Delta Sync Parameter / Inefficient Re-Bootstrap Wiping Local SQLite Tables)\
**Date:** 2026-11-24\
**Status:** **REMEDIATED & VERIFIED**

---

## 1. Vulnerability & Root Cause
- The mobile application's sync workflow (`mobile/lib/ui/features/dashboard/dashboard_view_model.dart`) executed `await syncRepository.bootstrapFromServer()` after pushing pending items.
- In `OfflineSyncRepository`, `bootstrapFromServer()` ran `_replaceRows()` which executed `await txn.delete(table)` for every table (`villages`, `blocks`, `gardens`, `trees`, `harvest_seasons`, etc.), wiping and redownloading the entire database snapshot.
- Although the Laravel backend provided `/api/v1/sync/pull` and `/api/v1/sync/changes` accepting a `since` timestamp cursor, the mobile client lacked incremental delta merging logic.
- **Risks:** High mobile bandwidth consumption, battery drain, potential race conditions/temporary data disappearance while tables are dropped and re-inserted, and failure under intermittent rural connectivity.

## 2. Remediation Implemented
1. **Incremental Delta Pull (`pullDeltaFromServer`):**
   - Implemented `pullDeltaFromServer()` in `OfflineSyncRepository`.
   - Checks `_storage.getLastSyncAt()`. If null (first-time login or fresh installation), safely falls back to initial `bootstrapFromServer()`.
   - If `lastSyncAt` is present, queries `/api/v1/sync/pull` with `since: lastSyncAt`.
   - Updates `lastSyncAt` with the returned `server_time`.
2. **Non-Destructive SQLite Upsert (`persistDeltaChanges` & `_upsertRows`):**
   - Implemented `persistDeltaChanges(Map<String, Object?> data)` and `_upsertRows()`.
   - Uses `txn.insert(table, mapper(row), conflictAlgorithm: ConflictAlgorithm.replace)` to update changed records or insert new records based on primary key (`id`).
   - **Crucially removes `txn.delete(table)`**: Existing records that were not modified on the server remain completely intact and available offline.
3. **Dashboard Integration:**
   - Updated `DashboardViewModel.syncNow()` to invoke `syncRepository.pullDeltaFromServer()` instead of wiping with `bootstrapFromServer()`.
   - Updated status feedback message to indicate how many delta changes were pulled.
4. **API Client Payload Fix:**
   - Corrected payload serialization in `ApiClient.pullSyncChanges()` to pass `if (since != null) 'since': since`.

## 3. Verification & Evidence
- **Backend Test Suite:** `tests/Feature/OfflineSyncEngineTest.php` (4 tests, 46 assertions, 100% PASSED).
  - Verified `POST /api/v1/sync/pull` returns delta records created/updated after cursor.
  - Verified territorial scoping is enforced on pulled delta changes.
- **Mobile Unit Test Suite:** `mobile/test/offline_sync_repository_test.dart`:
  - Added `RSK-06: pullDeltaFromServer uses since timestamp and updates SQLite without wiping existing records`.
  - Verified `since` parameter sent properly.
  - Verified local database updates existing rows (`t-2`), inserts new delta rows (`t-3`), and preserves existing un-updated rows (`t-1`).
  - Verified `lastSyncAt` updated to new server timestamp.
