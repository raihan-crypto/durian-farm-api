# RSK-03 REMEDIATION REPORT: FILAMENT TERRITORIAL DATA SCOPING
**Target Vulnerability:** RSK-03 (P1: Filament Territorial Data Scoping Gaps)\
**Date:** 2026-11-24\
**Status:** **REMEDIATED & VERIFIED**

---

## 1. Vulnerability & Root Cause
- Filament resources (`VillageResource`, `BlockResource`, `GardenResource`, `TreeResource`, `FarmerResource`, `TreeMeasurementResource`, and activity resources) previously returned un-scoped queries in `getEloquentQuery()`.
- Dashboard widgets (`PlantationStatsOverview`, `LatestHarvestRecordsWidget`, `HarvestTrendChart`) queried un-scoped aggregate metrics and cached them globally (`filament:plantation:stats_overview`), exposing data across different organizations and non-assigned territories.

## 2. Remediation Implemented
1. **Resource Query Scoping (`getEloquentQuery()`):**
   - `VillageResource`: Scoped to `getAccessibleVillageIds($user)` (restricting `pemerintah_desa` strictly to their assigned village).
   - `BlockResource`: Scoped to `getAccessibleBlockIds($user)`.
   - `GardenResource`: Scoped to accessible block IDs for `petani`, and accessible garden IDs for other scoped roles.
   - `TreeResource`: Scoped to `garden_id IN accessibleGardenIds`.
   - `FarmerResource`: Scoped so `petani` only views laborers assigned to their owned gardens.
   - Operational Resources (`TreeMeasurementResource`, `FertilizationRecordResource`, `SprayingRecordResource`, `FloweringRecordResource`, `FruitDevelopmentRecordResource`, `HarvestSessionResource`, `HarvestRecordResource`): All scoped via `getAccessibleGardenIds($user)`.
2. **Form Relationship Scoping:**
   - In `TreeResource`, the `garden_id` select dropdown query was scoped so farmers can only select their own gardens.
3. **Dashboard Widget Isolation:**
   - `PlantationStatsOverview`: Cache key scoped per user (`filament:plantation:stats_overview:{userId}`). Tree, harvest, village, block, and garden metrics are filtered by territory scope. `sync_issues` is restricted to administrators.
   - `LatestHarvestRecordsWidget`: Filtered by `whereIn('garden_id', $accessibleGardenIds)`.
   - `HarvestTrendChart`: Filtered by `whereIn('garden_id', $accessibleGardenIds)`.

## 3. Verification & Evidence
- **Dedicated Negative & Scoping Test Suite:** `tests/Feature/FilamentTerritorialScopingTest.php` (5 tests, 25 assertions, 100% PASSED).
- **Existing Filament Tests:** `TerritoryRevisionAndDetailFeaturesTest` (7 tests, 51 assertions, 100% PASSED).
- **Full Test Suite:** 111 tests, 919 assertions (100% PASSED).
- **Code Formatting:** Laravel Pint passed cleanly.
