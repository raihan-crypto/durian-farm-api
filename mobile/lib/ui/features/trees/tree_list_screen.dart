import 'dart:async';
import 'package:material_ui/material_ui.dart';
import '../../../data/models/models.dart';
import '../../../data/repositories/offline_sync_repository.dart';
import 'tree_detail_screen.dart';

class TreeListScreen extends StatefulWidget {
  const TreeListScreen({
    super.key,
    required this.syncRepository,
    required this.onActivityRecorded,
  });

  final OfflineSyncRepository syncRepository;
  final VoidCallback onActivityRecorded;

  @override
  State<TreeListScreen> createState() => _TreeListScreenState();
}

class _TreeListScreenState extends State<TreeListScreen> {
  final _searchController = TextEditingController();
  Timer? _debounceTimer;
  List<LocalTree> _trees = const [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadTrees();
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadTrees([String? query]) async {
    setState(() => _isLoading = true);
    final result = await widget.syncRepository.getTrees(search: query);
    if (mounted) {
      setState(() {
        _trees = result;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            controller: _searchController,
            decoration: const InputDecoration(
              hintText: 'Cari kode pohon, nama, atau varietas...',
              prefixIcon: Icon(Icons.search),
            ),
            onChanged: (val) {
              _debounceTimer?.cancel();
              _debounceTimer = Timer(const Duration(milliseconds: 50), () {
                _loadTrees(val);
              });
            },
          ),
        ),
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator())
              : _trees.isEmpty
                  ? const Center(
                      child: Text('Belum ada data pohon tersimpan lokal.'),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      itemCount: _trees.length,
                      itemBuilder: (context, index) {
                        final tree = _trees[index];
                        return Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          child: ListTile(
                            leading: CircleAvatar(
                              child: Text(
                                tree.treeCode.isNotEmpty
                                    ? tree.treeCode.substring(0, 1)
                                    : 'P',
                              ),
                            ),
                            title: Text(
                              '${tree.treeCode}${tree.treeName != null ? ' — ${tree.treeName}' : ''}',
                            ),
                            subtitle: Text(
                              '${tree.varietyName ?? 'Varietas -'} • ${tree.gardenName ?? ''}',
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () async {
                              await Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => TreeDetailScreen(
                                    tree: tree,
                                    syncRepository: widget.syncRepository,
                                    onActivityRecorded:
                                        widget.onActivityRecorded,
                                  ),
                                ),
                              );
                            },
                          ),
                        );
                      },
                    ),
        ),
      ],
    );
  }
}
