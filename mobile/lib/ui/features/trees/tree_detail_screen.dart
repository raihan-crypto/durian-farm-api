import 'package:material_ui/material_ui.dart';
import '../../../data/models/models.dart';
import '../../../data/repositories/offline_sync_repository.dart';
import '../activities/activity_form_screen.dart';

class TreeDetailScreen extends StatelessWidget {
  const TreeDetailScreen({
    super.key,
    required this.tree,
    required this.syncRepository,
    required this.onActivityRecorded,
  });

  final LocalTree tree;
  final OfflineSyncRepository syncRepository;
  final VoidCallback onActivityRecorded;

  Future<void> _openForm(BuildContext context, FieldActivityKind kind) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => ActivityFormScreen(
          tree: tree,
          kind: kind,
          syncRepository: syncRepository,
        ),
      ),
    );
    if (saved == true) {
      onActivityRecorded();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text('Pohon ${tree.treeCode}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    tree.treeName ?? tree.treeCode,
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  _InfoRow(label: 'Kode Pohon', value: tree.treeCode),
                  _InfoRow(
                    label: 'Varietas',
                    value: tree.varietyName ?? 'Belum ditentukan',
                  ),
                  _InfoRow(label: 'Kebun', value: tree.gardenName ?? '-'),
                  _InfoRow(label: 'Status', value: tree.status),
                  if (tree.plantedAt != null)
                    _InfoRow(label: 'Tanggal Tanam', value: tree.plantedAt!),
                  if (tree.notes != null && tree.notes!.isNotEmpty)
                    _InfoRow(label: 'Catatan', value: tree.notes!),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Catat Aktivitas Lapangan (Offline-Ready)',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 10),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.55,
            children: [
              _ActionTile(
                icon: Icons.straighten,
                label: 'Pengukuran',
                onTap: () => _openForm(context, FieldActivityKind.measurement),
              ),
              _ActionTile(
                icon: Icons.science_outlined,
                label: 'Pemupukan',
                onTap: () =>
                    _openForm(context, FieldActivityKind.fertilization),
              ),
              _ActionTile(
                icon: Icons.water_drop_outlined,
                label: 'Penyemprotan',
                onTap: () => _openForm(context, FieldActivityKind.spraying),
              ),
              _ActionTile(
                icon: Icons.local_florist_outlined,
                label: 'Pembungaan',
                onTap: () => _openForm(context, FieldActivityKind.flowering),
              ),
              _ActionTile(
                icon: Icons.spa_outlined,
                label: 'Perkembangan Buah',
                onTap: () =>
                    _openForm(context, FieldActivityKind.fruitDevelopment),
              ),
              _ActionTile(
                icon: Icons.agriculture_outlined,
                label: 'Panen Pohon',
                onTap: () => _openForm(context, FieldActivityKind.harvest),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(label, style: TextStyle(color: Colors.grey[700])),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: theme.colorScheme.primary, size: 28),
              const SizedBox(height: 6),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
