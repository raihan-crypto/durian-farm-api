import 'package:material_ui/material_ui.dart';
import 'data/local/local_database.dart';
import 'data/models/models.dart';
import 'data/repositories/auth_repository.dart';
import 'data/repositories/offline_sync_repository.dart';
import 'data/services/api_client.dart';
import 'data/services/session_storage.dart';
import 'ui/core/app_theme.dart';
import 'ui/features/auth/login_screen.dart';
import 'ui/features/dashboard/dashboard_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const DurianFarmApp());
}

class DurianFarmApp extends StatefulWidget {
  const DurianFarmApp({super.key});

  @override
  State<DurianFarmApp> createState() => _DurianFarmAppState();
}

class _DurianFarmAppState extends State<DurianFarmApp> {
  late final LocalDatabase _localDatabase;
  late final ApiClient _apiClient;
  late final SessionStorage _sessionStorage;
  late final AuthRepository _authRepository;
  late final OfflineSyncRepository _syncRepository;

  AuthSession? _session;
  bool _restoring = true;

  @override
  void initState() {
    super.initState();
    _localDatabase = LocalDatabase();
    _apiClient = ApiClient();
    _sessionStorage = SessionStorage();
    _authRepository = AuthRepository(
      apiClient: _apiClient,
      sessionStorage: _sessionStorage,
      localDatabase: _localDatabase,
    );
    _syncRepository = OfflineSyncRepository(
      localDatabase: _localDatabase,
      apiClient: _apiClient,
      sessionStorage: _sessionStorage,
    );
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    try {
      await _syncRepository.recoverInterruptedOnStartup();
    } catch (_) {
      // Non-fatal: startup recovery will also run before the next sync cycle.
    }
    final saved = await _authRepository.restoreSession();
    if (mounted) {
      setState(() {
        _session = saved;
        _restoring = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Durian Farm Mobile',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      home: _restoring
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _session == null
              ? LoginScreen(
                  authRepository: _authRepository,
                  syncRepository: _syncRepository,
                  onLoginSuccess: (session) =>
                      setState(() => _session = session),
                )
              : DashboardScreen(
                  session: _session!,
                  authRepository: _authRepository,
                  syncRepository: _syncRepository,
                  onLogout: () => setState(() => _session = null),
                ),
    );
  }
}
