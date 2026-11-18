import 'package:material_ui/material_ui.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../data/repositories/offline_sync_repository.dart';
import '../trees/tree_detail_screen.dart';

class QrScannerScreen extends StatefulWidget {
  const QrScannerScreen({
    super.key,
    required this.syncRepository,
    required this.onActivityRecorded,
  });

  final OfflineSyncRepository syncRepository;
  final VoidCallback onActivityRecorded;

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  final _manualController = TextEditingController();
  bool _handlingScan = false;

  @override
  void dispose() {
    _manualController.dispose();
    super.dispose();
  }

  Future<void> _lookupAndOpen(String rawValue) async {
    if (_handlingScan || rawValue.trim().isEmpty) return;
    _handlingScan = true;

    try {
      final tree = await widget.syncRepository.findTreeByQrOrCode(rawValue);
      if (!mounted) return;

      if (tree == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Pohon tidak ditemukan di database lokal. Lakukan sinkronisasi terlebih dahulu.',
            ),
          ),
        );
        return;
      }

      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => TreeDetailScreen(
            tree: tree,
            syncRepository: widget.syncRepository,
            onActivityRecorded: widget.onActivityRecorded,
          ),
        ),
      );
    } finally {
      _handlingScan = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(
          flex: 3,
          child: ClipRRect(
            borderRadius: const BorderRadius.vertical(
              bottom: Radius.circular(18),
            ),
            child: MobileScanner(
              onDetect: (capture) {
                final raw = capture.barcodes.firstOrNull?.rawValue;
                if (raw != null) {
                  _lookupAndOpen(raw);
                }
              },
            ),
          ),
        ),
        Expanded(
          flex: 2,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Arahkan kamera ke QR Code pohon atau masukkan kode pohon secara manual (bekerja offline):',
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _manualController,
                  decoration: InputDecoration(
                    labelText: 'Kode Pohon / Token QR',
                    suffixIcon: IconButton(
                      onPressed: () => _lookupAndOpen(_manualController.text),
                      icon: const Icon(Icons.search),
                    ),
                  ),
                  onSubmitted: _lookupAndOpen,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
