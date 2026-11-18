import 'package:material_ui/material_ui.dart';
import '../../../data/models/models.dart';
import '../dashboard/dashboard_view_model.dart';

class SyncQueueScreen extends StatelessWidget {
  const SyncQueueScreen({super.key, required this.viewModel});

  final DashboardViewModel viewModel;

  Color _statusColor(LocalSyncStatus status) => switch (status) {
        LocalSyncStatus.synced => Colors.green,
        LocalSyncStatus.pending => Colors.orange,
        LocalSyncStatus.syncing => Colors.blue,
        LocalSyncStatus.conflict => Colors.deepPurple,
        LocalSyncStatus.failed => Colors.red,
      };

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        FilledButton.icon(
          onPressed: viewModel.isSyncing ? null : viewModel.syncNow,
          icon: const Icon(Icons.cloud_sync_outlined),
          label: Text(
            viewModel.isSyncing
                ? 'Menyinkronkan...'
                : 'Kirim Antrean & Tarik Data Baru',
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Antrean Aktivitas (${viewModel.queue.length})',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        if (viewModel.queue.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text('Belum ada aktivitas lokal tercatat.'),
            ),
          )
        else
          ...viewModel.queue.map(
            (item) => Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor:
                      _statusColor(item.status).withValues(alpha: 0.15),
                  child: Icon(Icons.sync, color: _statusColor(item.status)),
                ),
                title: Text(item.entityType),
                subtitle: Text(
                  item.errorMessage ?? item.createdAt,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                trailing: Chip(
                  label: Text(
                    item.status.name.toUpperCase(),
                    style: const TextStyle(fontSize: 11),
                  ),
                ),
              ),
            ),
          ),
        if (viewModel.photoQueue.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(
            'Antrean Foto (${viewModel.photoQueue.length})',
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          ...viewModel.photoQueue.map(
            (photo) => Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: const Icon(Icons.photo_outlined),
                title: Text(photo.activityType),
                subtitle: Text(photo.errorMessage ?? photo.localFilePath),
                trailing: Text(photo.status.name.toUpperCase()),
              ),
            ),
          ),
        ],
      ],
    );
  }
}
