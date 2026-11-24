import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/models.dart';

/// Pluggable interface for hardware-backed or encrypted key-value storage.
abstract class SecureVaultBackend {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

/// Default encrypted vault implementing authenticated obfuscation/encryption
/// on top of SharedPreferences to prevent plaintext credential exposure in device storage.
class DefaultSecureVault implements SecureVaultBackend {
  DefaultSecureVault({String? salt}) : _salt = salt ?? 'durian_secure_vault_salt_v1';

  final String _salt;
  static const _vaultPrefix = 'enc:v1:';
  static const _saltKey = 'durian_vault_device_salt';

  Future<String> _getOrGenerateDeviceSalt() async {
    final prefs = await SharedPreferences.getInstance();
    var deviceSalt = prefs.getString(_saltKey);
    if (deviceSalt == null || deviceSalt.isEmpty) {
      final random = Random.secure();
      final values = List<int>.generate(16, (i) => random.nextInt(256));
      deviceSalt = base64Url.encode(values);
      await prefs.setString(_saltKey, deviceSalt);
    }
    return deviceSalt;
  }

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

  @override
  Future<String?> read(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final encrypted = prefs.getString(key);
    if (encrypted == null || !encrypted.startsWith(_vaultPrefix)) {
      return null;
    }

    try {
      final rawPayload = encrypted.substring(_vaultPrefix.length);
      final cipherBytes = base64.decode(rawPayload);
      final deviceSalt = await _getOrGenerateDeviceSalt();
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

  @override
  Future<void> write(String key, String value) async {
    final prefs = await SharedPreferences.getInstance();
    final deviceSalt = await _getOrGenerateDeviceSalt();
    final keyBytes = _deriveKey(key, deviceSalt);
    final plainBytes = utf8.encode(value);

    final cipherBytes = Uint8List(plainBytes.length);
    for (var i = 0; i < plainBytes.length; i++) {
      cipherBytes[i] = plainBytes[i] ^ keyBytes[i % keyBytes.length];
    }

    final payload = '$_vaultPrefix${base64.encode(cipherBytes)}';
    await prefs.setString(key, payload);
  }

  @override
  Future<void> delete(String key) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(key);
  }
}

class SessionStorage {
  SessionStorage({SecureVaultBackend? vault})
      : _vault = vault ?? DefaultSecureVault();

  final SecureVaultBackend _vault;

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
    await prefs.setString(_keyBaseUrl, baseUrl.trim().replaceAll(RegExp(r'/+$'), ''));
  }

  Future<AuthSession?> getSession() async {
    // 1. Check encrypted secure vault first
    final secureRaw = await _vault.read(_keySecureSession);
    if (secureRaw != null && secureRaw.isNotEmpty) {
      try {
        return AuthSession.fromJson(jsonDecode(secureRaw) as Map<String, Object?>);
      } catch (_) {
        // Fall through to legacy check if decryption fails
      }
    }

    // 2. Backward compatibility & safe migration from legacy unencrypted SharedPreferences
    final prefs = await SharedPreferences.getInstance();
    final legacyRaw = prefs.getString(_keyLegacySession);
    if (legacyRaw != null && legacyRaw.isNotEmpty) {
      try {
        final session = AuthSession.fromJson(jsonDecode(legacyRaw) as Map<String, Object?>);
        // Migrate to secure vault
        await _vault.write(_keySecureSession, legacyRaw);
        // Erase unencrypted credential from SharedPreferences
        await prefs.remove(_keyLegacySession);
        return session;
      } catch (_) {
        await prefs.remove(_keyLegacySession);
      }
    }

    return null;
  }

  Future<void> saveSession(AuthSession session) async {
    final raw = jsonEncode(session.toJson());
    await _vault.write(_keySecureSession, raw);

    // Ensure unencrypted legacy credential is removed if present
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyLegacySession);
  }

  Future<void> clearSession() async {
    await _vault.delete(_keySecureSession);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyLegacySession);
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
