# RSK-04 REMEDIATION REPORT: SOFT DELETE AND AUDIT TRAIL
**Target Vulnerability:** RSK-04 (P1: Untraceable Mass Permanent Deletion in `durian:prune-soft-deleted`)\
**Date:** 2026-11-24\
**Status:** **REMEDIATED & VERIFIED**

---

## 1. Vulnerability & Root Cause
- The `durian:prune-soft-deleted` command previously executed raw query `$query->forceDelete()` directly on database queries.
- Raw query force deletes completely bypassed Eloquent model events and the `Auditable` trait.
- Permanent deletion left zero trace in `audit_logs`, destroying accountability.
- It did not inspect foreign key parent-child dependencies, risking constraint crashes or cascading data loss.

## 2. Remediation Implemented
1. **Audit Snapshot Logging:**
   - Before every force delete, an explicit `AuditLog` entry with `action: 'pruned'` is generated capturing the full `old_values` model snapshot, retention policy parameters, table name, and record ID.
2. **Chunked Model Iteration:**
   - Switched from raw query execution to `chunkById(100)` and individual `$record->forceDelete()`, safely triggering model events and handling physical file cleanup for `ActivityPhoto`.
3. **Referential Integrity Safety Guard:**
   - Implemented `hasActiveDependencies(Model $record)`: parent records (e.g. `Village`, `Block`, `Garden`, `Tree`, `HarvestSeason`, `ProductCategory`) are skipped and preserved if active or un-pruned dependent records remain.
4. **Resilient Failure Handling:**
   - Wrapped per-record deletion in `try/catch` with clear warning output, preventing a single locked record from halting the entire batch.

## 3. Verification & Evidence
- **Dedicated Test Suite:** `tests/Feature/SoftDeleteAndAuditTrailPruneTest.php` (5 tests, 16 assertions, 100% PASSED).
  - Verified eligible records deleted > 30 days are pruned.
  - Verified audit logs created with old values snapshot.
  - Verified records < 30 days preserved.
  - Verified parent records with child dependencies skipped.
  - Verified physical storage files deleted for `ActivityPhoto`.
  - Verified idempotency on repeated execution.
- **Code Formatting:** Laravel Pint passed.
