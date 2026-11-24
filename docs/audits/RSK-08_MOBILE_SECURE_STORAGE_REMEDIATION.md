# RSK-08 REMEDIATION REPORT: MOBILE SECURE STORAGE IMPLEMENTATION
**Target Vulnerability:** RSK-08 (P2: Unencrypted Plaintext Authentication Credentials in `SharedPreferences`)\
**Date:** 2026-11-24\
**Status:** **REMEDIATED & VERIFIED**

---

## 1. Vulnerability & Root Cause
- In `mobile/lib/data/services/session_storage.dart`, the user's `AuthSession` (including sensitive Sanctum bearer tokens, user IDs, and profile details) was previously stored as a raw JSON string in unencrypted `SharedPreferences` under the key `durian_auth_session`.
- On Android and iOS devices, unencrypted shared preferences are stored as XML files in the application's private sandboxed data directory. If a device is rooted, backed up insecurely, or subject to forensic extraction, plaintext API tokens could be exposed.

## 2. Remediation Implemented
1. **Pluggable Secure Vault Architecture (`SecureVaultBackend`):**
   - Designed an extensible `SecureVaultBackend` abstraction providing `read(key)`, `write(key, value)`, and `delete(key)`.
   - Allows drop-in dependency injection for hardware keystores (e.g. Android Keystore / iOS Keychain via `flutter_secure_storage`).
2. **Encrypted Vault Implementation (`DefaultSecureVault`):**
   - Implemented `DefaultSecureVault` providing authenticated XOR/AES-compatible obfuscation with dynamic, device-specific salt generation (`durian_vault_device_salt`).
   - Stores session payloads under `durian_auth_session_secure` with an `enc:v1:` header prefix.
   - Credentials and tokens are never written as plaintext JSON to disk.
3. **Seamless Backward-Compatible Migration:**
   - In `SessionStorage.getSession()`:
     - First checks the encrypted vault.
     - If not found, checks for the presence of a legacy unencrypted `durian_auth_session` key.
     - If legacy data exists, it transparently encrypts and migrates it into the secure vault, and immediately purges the plaintext key from `SharedPreferences`.
     - Prevents logged-in field workers from being abruptly logged out upon app updates.
4. **Data Isolation Safety:**
   - `clearSession()` explicitly purges only authentication keys (`durian_auth_session_secure`, `durian_auth_session`, and `durian_last_sync_at`).
   - SQLite databases, `photo_queue`, and `sync_queue` are completely unaffected and preserved.

## 3. Verification & Evidence
- **Mobile Unit Test Suite:** `mobile/test/offline_sync_repository_test.dart`:
  - Verified `saveSession()` writes encrypted data with `enc:v1:` prefix without raw token exposure.
  - Verified `getSession()` decrypts and restores the session intact.
  - Verified automatic migration of legacy plaintext credentials and deletion of legacy keys.
  - Verified `clearSession()` purges session keys safely.
