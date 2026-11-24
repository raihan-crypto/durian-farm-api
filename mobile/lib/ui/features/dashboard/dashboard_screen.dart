import 'package:material_ui/material_ui.dart';
import '../../../data/models/models.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../../data/repositories/offline_sync_repository.dart';
import '../qr/qr_scanner_screen.dart';
import '../sync/sync_queue_screen.dart';
import '../trees/tree_list_screen.dart';
import 'dashboard_view_model.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.session,
    required this.authRepository,
    required this.syncRepository,
    required this.onLogout,
  });

  final AuthSession session;
  final AuthRepository authRepository;
  final OfflineSyncRepository syncRepository;
  final VoidCallback onLogout;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late final DashboardViewModel _viewModel;
  int _selectedIndex = 0;

  @override
  void initState() {
    super.initState();
    _viewModel = DashboardViewModel(syncRepository: widget.syncRepository);
    _viewModel.initialize();
  }

  @override
  void dispose() {
    _viewModel.dispose();
    super.dispose();
  }

  Future<void> _handleLogout(BuildContext context) async {
    final pendingActivities = _viewModel.pendingCount;
    final pendingPhotos = _viewModel.photoQueue
        .where((p) =>
            p.status == LocalSyncStatus.pending ||
            p.status == LocalSyncStatus.failed)
        .length;
    final totalPending = pendingActivities + pendingPhotos;

    final shouldLogout = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) => AlertDialog(
        icon: Icon(
          totalPending > 0
              ? Icons.warning_amber_rounded
              : Icons.help_outline_rounded,
          size: 40,
          color: totalPending > 0 ? Colors.orange.shade800 : Colors.blue.shade700,
        ),
        title: Text(
          totalPending > 0
              ? 'Peringatan: Data Belum Disinkronkan'
              : 'Konfirmasi Keluar',
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (totalPending > 0) ...[
              Text(
                'Terdapat $pendingActivities aktivitas dan $pendingPhotos foto yang belum tersinkronisasi ke server.',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 10),
              const Text(
                'Data Anda akan tetap tersimpan aman di perangkat ini dan tidak akan dihapus. Anda dapat melanjutkan sinkronisasi saat login kembali.',
                style: TextStyle(fontSize: 13, color: Colors.black87),
              ),
              const SizedBox(height: 10),
              const Text(
                'Apakah Anda tetap ingin keluar dari akun sekarang?',
                style: TextStyle(fontSize: 13),
              ),
            ] else ...[
              const Text(
                'Apakah Anda yakin ingin keluar dari akun Anda?',
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogCtx).pop(false),
            child: const Text('Batal'),
          ),
          FilledButton(
            style: totalPending > 0
                ? FilledButton.styleFrom(
                    backgroundColor: Colors.orange.shade900,
                    foregroundColor: Colors.white,
                  )
                : null,
            onPressed: () => Navigator.of(dialogCtx).pop(true),
            child: Text(totalPending > 0 ? 'Tetap Keluar' : 'Keluar'),
          ),
        ],
      ),
    );

    if (shouldLogout == true && mounted) {
      await widget.authRepository.logout();
      widget.onLogout();
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: _viewModel,
      builder: (context, _) {
        final pages = [
          _buildHomeTab(context),
          TreeListScreen(
            syncRepository: widget.syncRepository,
            onActivityRecorded: _viewModel.refreshLocalData,
          ),
          QrScannerScreen(
            syncRepository: widget.syncRepository,
            onActivityRecorded: _viewModel.refreshLocalData,
          ),
          SyncQueueScreen(
            viewModel: _viewModel,
          ),
        ];

        return Scaffold(
          appBar: AppBar(
            title: const Text('Durian Farm Mobile'),
            actions: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Chip(
                  visualDensity: VisualDensity.compact,
                  backgroundColor: _viewModel.isOnline
                      ? Colors.green.shade100
                      : Colors.orange.shade100,
                  label: Text(
                    _viewModel.isOnline ? 'Online' : 'Offline',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: _viewModel.isOnline
                          ? Colors.green.shade900
                          : Colors.orange.shade900,
                    ),
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Keluar',
                onPressed: () => _handleLogout(context),
                icon: const Icon(Icons.logout),
              ),
            ],
          ),
          body: pages[_selectedIndex],
          bottomNavigationBar: NavigationBar(
            selectedIndex: _selectedIndex,
            onDestinationSelected: (idx) => setState(() => _selectedIndex = idx),
            destinations: [
              const NavigationDestination(
                icon: Icon(Icons.dashboard_outlined),
                selectedIcon: Icon(Icons.dashboard),
                label: 'Beranda',
              ),
              const NavigationDestination(
                icon: Icon(Icons.park_outlined),
                selectedIcon: Icon(Icons.park),
                label: 'Pohon',
              ),
              const NavigationDestination(
                icon: Icon(Icons.qr_code_scanner),
                label: 'Scan QR',
              ),
              NavigationDestination(
                icon: Badge(
                  isLabelVisible: _viewModel.pendingCount > 0,
                  label: Text('${_viewModel.pendingCount}'),
                  child: const Icon(Icons.sync),
                ),
                label: 'Sinkron',
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildHomeTab(BuildContext context) {
    final theme = Theme.of(context);

    return RefreshIndicator(
      onRefresh: _viewModel.syncNow,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Halo, ${widget.session.userName}',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    widget.session.userEmail,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: Colors.grey[700],
                    ),
                  ),
                  if (_viewModel.lastSyncAt != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Sinkron terakhir: ${_viewModel.lastSyncAt}',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                  if (_viewModel.statusMessage != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      _viewModel.statusMessage!,
                      style: TextStyle(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: _viewModel.isSyncing ? null : _viewModel.syncNow,
                    icon: _viewModel.isSyncing
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.sync),
                    label: Text(
                      _viewModel.isSyncing
                          ? 'Menyinkronkan...'
                          : 'Sinkronkan Sekarang',
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _StatCard(
                  label: 'Kebun Tugas',
                  value: '${_viewModel.gardens.length}',
                  icon: Icons.map_outlined,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatCard(
                  label: 'Pohon Lokal',
                  value: '${_viewModel.trees.length}',
                  icon: Icons.park_outlined,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatCard(
                  label: 'Antrean Lokal',
                  value: '${_viewModel.pendingCount}',
                  icon: Icons.cloud_upload_outlined,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            'Kebun Penugasan Anda',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          if (_viewModel.gardens.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Text(
                  'Belum ada data kebun lokal. Tekan "Sinkronkan Sekarang" saat terhubung ke server.',
                ),
              ),
            )
          else
            ..._viewModel.gardens.map(
              (garden) => Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: const Icon(Icons.forest_outlined),
                  title: Text(garden.name),
                  subtitle: Text(
                    [garden.blockName, garden.villageName]
                        .where((e) => e != null && e.isNotEmpty)
                        .join(' • '),
                  ),
                  trailing: garden.areaHectares != null
                      ? Text('${garden.areaHectares!.toStringAsFixed(2)} Ha')
                      : null,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Icon(icon, color: theme.colorScheme.primary),
            const SizedBox(height: 6),
            Text(
              value,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}
