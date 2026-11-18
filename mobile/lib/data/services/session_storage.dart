import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/models.dart';

class SessionStorage {
  static const _keySession = 'durian_auth_session';
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
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_keySession);
    if (raw == null || raw.isEmpty) return null;
    return AuthSession.fromJson(jsonDecode(raw) as Map<String, Object?>);
  }

  Future<void> saveSession(AuthSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keySession, jsonEncode(session.toJson()));
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keySession);
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
