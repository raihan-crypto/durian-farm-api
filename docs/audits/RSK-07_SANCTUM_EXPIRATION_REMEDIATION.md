# RSK-07 REMEDIATION REPORT: SANCTUM TOKEN EXPIRATION
**Target Vulnerability:** RSK-07 (P2: Indefinite API Token Lifetime in Laravel Sanctum)\
**Date:** 2026-11-24\
**Status:** **REMEDIATED & VERIFIED**

---

## 1. Vulnerability & Root Cause
- In `config/sanctum.php`, `'expiration' => null` was previously configured.
- When `expiration` is null, Laravel Sanctum personal access tokens never expire.
- If a mobile device or API bearer token is compromised or leaked, the token would remain valid indefinitely unless manually deleted from the database.
- Although mobile agricultural workers operating in remote plantations require offline resilience, indefinite token lifespans represent an unacceptable security risk under enterprise compliance standards.

## 2. Remediation Implemented
1. **Configured Sanctum Expiration Window:**
   - Updated `config/sanctum.php`:
     ```php
     'expiration' => env('SANCTUM_EXPIRATION', 43200), // 30 days in minutes (43,200 mins)
     ```
   - This 30-day window provides ample buffer for field workers operating in disconnected areas for weeks, while terminating stale/compromised credentials automatically.
2. **Explicit `expires_at` Column Generation:**
   - In `app/Http/Controllers/Api/V1/AuthController.php@login`:
     ```php
     $expirationMinutes = config('sanctum.expiration');
     $expiresAt = is_numeric($expirationMinutes) && (int) $expirationMinutes > 0
         ? now()->addMinutes((int) $expirationMinutes)
         : null;

     $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;
     ```
   - Populated the `expires_at` column in `personal_access_tokens` table and exposed `expires_at` in the login JSON response for client-side proactive token lifecycle management.
3. **Immediate Token Revocation on Logout:**
   - In `AuthController@logout`, `$user->currentAccessToken()->delete()` permanently purges the token record upon user-initiated logout.

## 3. Verification & Evidence
- **Dedicated Test Suite:** `tests/Feature/SanctumTokenExpirationTest.php` (5 tests, 19 assertions, 100% PASSED).
  - Verified login returns token and explicit `expires_at` timestamp.
  - Verified freshly issued token authenticates successfully on `/api/v1/auth/me`.
  - Verified expired token (simulated > 30 days) is immediately rejected with `401 Unauthenticated`.
  - Verified token within 30-day window authenticates successfully.
  - Verified token revocation upon logout invalidates the credential immediately (subsequent request returns 401).
- **Full Regression Test Suite:** All 130 tests passed (978 assertions passed).
- **Code Style:** Formatted cleanly with Laravel Pint.
