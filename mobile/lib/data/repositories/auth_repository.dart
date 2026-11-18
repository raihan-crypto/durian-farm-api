import '../local/local_database.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/session_storage.dart';

class AuthRepository {
  AuthRepository({
    required this.apiClient,
    required this.sessionStorage,
    required this.localDatabase,
  });

  final ApiClient apiClient;
  final SessionStorage sessionStorage;
  final LocalDatabase localDatabase;

  Future<AuthSession?> restoreSession() => sessionStorage.getSession();

  Future<String> getBaseUrl() => sessionStorage.getBaseUrl();

  Future<AuthSession> login({
    required String baseUrl,
    required String email,
    required String password,
  }) async {
    await sessionStorage.saveBaseUrl(baseUrl);
    final normalizedUrl = await sessionStorage.getBaseUrl();

    final response = await apiClient.login(
      baseUrl: normalizedUrl,
      email: email,
      password: password,
    );

    final data = response['data'] as Map<String, Object?>;
    final user = data['user'] as Map<String, Object?>;
    final roles = (data['roles'] as List<Object?>? ?? []).cast<String>();

    final session = AuthSession(
      token: data['token'] as String,
      userId: user['id'] as String,
      userName: user['name'] as String,
      userEmail: user['email'] as String,
      roles: roles,
    );

    await sessionStorage.saveSession(session);
    return session;
  }

  Future<void> logout() async {
    final session = await sessionStorage.getSession();
    final baseUrl = await sessionStorage.getBaseUrl();
    if (session != null) {
      try {
        await apiClient.logout(baseUrl: baseUrl, token: session.token);
      } catch (_) {
        // Offline logout still clears local credentials safely.
      }
    }
    await sessionStorage.clearSession();
    await localDatabase.clearAllData();
  }
}
