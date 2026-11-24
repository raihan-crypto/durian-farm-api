import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/models.dart';

/// Exception thrown when platform secure storage (Keystore/Keychain) is unavailable
/// and persisting credentials safely cannot be guaranteed.
class SecureStorageUnavailableException implements Exception {
  const SecureStorageUnavailableException(this.message, {this.cause});

  final String message;
  final Object? cause;

  @override
  String toString() => 'SecureStorageUnavailableException: $message';
}

/// Pluggable interface for hardware-backed or encrypted key-value storage.
abstract class SecureVaultBackend {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

/// Production secure vault backed by Android Keystore and iOS Keychain
/// via `flutter_secure_storage`.
class PlatformSecureVault implements SecureVaultBackend {
  PlatformSecureVault({FlutterSecureStorage? storage})
      : _storage = storage ??
            const FlutterSecureStorage(
              iOptions: IOSOptions(
                accessibility: KeychainAccessibility.first_unlock_this_device,
              ),
            );

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

/// In-memory vault backend suitable for isolated tests or ephemeral-only mode.
class InMemorySecureVault implements SecureVaultBackend {
  final Map<String, String> _store = <String, String>{};

  @override
  Future<String?> read(String key) async => _store[key];

  @override
  Future<void> write(String key, String value) async {
    _store[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    _store.remove(key);
  }
}

/// Read-only decoder for legacy `enc:v1:` XOR-obfuscated credentials previously stored
/// in SharedPreferences before migration to Android Keystore / iOS Keychain (VULN-07).
class LegacyObfuscatedVaultReader {
  LegacyObfuscatedVaultReader({String? salt})
      : _salt = salt ?? 'durian_secure_vault_salt_v1';

  final String _salt;
  static const vaultPrefix = 'enc:v1:';
  static const saltKey = 'durian_vault_device_salt';

  Uint8List _deriveKey(String key, String deviceSalt) {
    final combined = utf8.encode('$_salt:$key:$deviceSalt');
    final keyBytes = Uint8List(32);
    for (var i = 0; i < 32; i++) {
      var acc = (i * 31 + 17) & 0xFF;
      for (var j = 0; j < combined.length; j++) {
        acc = (acc ^ (combined[j] * (j + 1) + i)) & 0xFF;
      }
      keyBytes[i] = acc;
    }
    return keyBytes;
  }

  Future<String?> readLegacyObfuscated(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final encrypted = prefs.getString(key);
    if (encrypted == null || !encrypted.startsWith(vaultPrefix)) {
      return null;
    }

    final deviceSalt = prefs.getString(saltKey);
    if (deviceSalt == null || deviceSalt.isEmpty) {
      return null;
    }

    try {
      final rawPayload = encrypted.substring(vaultPrefix.length);
      final cipherBytes = base64.decode(rawPayload);
      final keyBytes = _deriveKey(key, deviceSalt);

      final plainBytes = Uint8List(cipherBytes.length);
      for (var i = 0; i < cipherBytes.length; i++) {
        plainBytes[i] = cipherBytes[i] ^ keyBytes[i % keyBytes.length];
      }

      return utf8.decode(plainBytes);
    } catch (_) {
      return null;
    }
  }

  Future<void> clearLegacyObfuscated(String key) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
    await prefs.remove(saltKey);
  }
}

class SessionStorage {
  SessionStorage({
    SecureVaultBackend? vault,
    LegacyObfuscatedVaultReader? legacyReader,
    this.allowEphemeralMemoryFallback = false,
  })  : _vault = vault ?? PlatformSecureVault(),
        _legacyReader = legacyReader ?? LegacyObfuscatedVaultReader();

  final SecureVaultBackend _vault;
  final LegacyObfuscatedVaultReader _legacyReader;

  /// When true and platform secure storage fails, credentials are held ONLY in
  /// volatile process memory (never written to plaintext SharedPreferences/disk).
  /// When false (default), saveSession throws [SecureStorageUnavailableException].
  final bool allowEphemeralMemoryFallback;

  AuthSession? _ephemeralSession;

  static const _keySecureSession = 'durian_auth_session_secure';
  static const _keyLegacySession = 'durian_auth_session';
  static const _keyBaseUrl = 'durian_api_base_url';
  static const _keyLastSync = 'durian_last_sync_at';

  /// Default Android emulator loopback to local Laravel server.
  static const defaultBaseUrl = 'http://10.0.2.2:8000';

  Future<String> getBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyBaseUrl) ?? defaultBaseUrl;
  }

  Future<void> saveBaseUrl(String baseUrl) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _keyBaseUrl,
      baseUrl.trim().replaceAll(RegExp(r'/+$'), ''),
    );
  }

  Future<AuthSession?> getSession() async {
    // 1. Check hardware-backed platform secure vault first
    try {
      final secureRaw = await _vault.read(_keySecureSession);
      if (secureRaw != null && secureRaw.isNotEmpty) {
        try {
          return AuthSession.fromJson(
            jsonDecode(secureRaw) as Map<String, Object?>,
          );
        } catch (_) {
          // Corrupted payload; fall through to migration checks
        }
      }
    } catch (_) {
      // Platform vault temporarily unavailable; check ephemeral memory or legacy migration
      if (_ephemeralSession != null) {
        return _ephemeralSession;
      }
    }

    // 2. Safe migration from legacy `enc:v1:` obfuscated SharedPreferences vault
    final legacyObfuscatedRaw =
        await _legacyReader.readLegacyObfuscated(_keySecureSession);
    if (legacyObfuscatedRaw != null && legacyObfuscatedRaw.isNotEmpty) {
      try {
        final session = AuthSession.fromJson(
          jsonDecode(legacyObfuscatedRaw) as Map<String, Object?>,
        );
        try {
          await _vault.write(_keySecureSession, legacyObfuscatedRaw);
          await _legacyReader.clearLegacyObfuscated(_keySecureSession);
        } catch (_) {
          // Migration failed: preserve legacy entry and keep active session in memory
          // so user does not lose access unexpectedly.
          _ephemeralSession = session;
        }
        return session;
      } catch (_) {
        // Malformed legacy entry
      }
    }

    // 3. Safe migration from legacy unencrypted SharedPreferences key
    final prefs = await SharedPreferences.getInstance();
    final legacyRaw = prefs.getString(_keyLegacySession);
    if (legacyRaw != null && legacyRaw.isNotEmpty) {
      try {
        final session = AuthSession.fromJson(
          jsonDecode(legacyRaw) as Map<String, Object?>,
        );
        try {
          await _vault.write(_keySecureSession, legacyRaw);
          await prefs.remove(_keyLegacySession);
        } catch (_) {
          // Migration failed: do NOT silently discard user session; hold in memory
          // without writing any new plaintext copies.
          _ephemeralSession = session;
        }
        return session;
      } catch (_) {
        await prefs.remove(_keyLegacySession);
      }
    }

    return _ephemeralSession;
  }

  Future<void> saveSession(AuthSession session) async {
    final raw = jsonEncode(session.toJson());
    try {
      await _vault.write(_keySecureSession, raw);
      _ephemeralSession = null;
    } catch (e) {
      if (allowEphemeralMemoryFallback) {
        // Safe non-plaintext fallback: volatile memory only, never persisted to disk
        _ephemeralSession = session;
      } else {
        throw SecureStorageUnavailableException(
          'Penyimpanan aman perangkat (Keystore/Keychain) tidak tersedia.',
          cause: e,
        );
      }
    }

    // Clean up any legacy plaintext or legacy obfuscated credentials in SharedPreferences
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyLegacySession);
    await _legacyReader.clearLegacyObfuscated(_keySecureSession);
  }

  Future<void> clearSession() async {
    _ephemeralSession = null;
    try {
      await _vault.delete(_keySecureSession);
    } catch (_) {
      // Best-effort vault deletion during logout
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyLegacySession);
    await _legacyReader.clearLegacyObfuscated(_keySecureSession);
    await prefs.remove(_keyLastSync);
  }

  Future<String?> getLastSyncAt() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyLastSync);
  }

  Future<void> saveLastSyncAt(String isoTimestamp) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyLastSync, isoTimestamp);
  }
}
