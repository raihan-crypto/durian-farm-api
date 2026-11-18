import 'package:material_ui/material_ui.dart';
import 'package:image_picker/image_picker.dart';
import '../../../data/models/models.dart';
import '../../../data/repositories/offline_sync_repository.dart';

enum FieldActivityKind {
  measurement,
  fertilization,
  spraying,
  flowering,
  fruitDevelopment,
  harvest,
}

class ActivityFormScreen extends StatefulWidget {
  const ActivityFormScreen({
    super.key,
    required this.tree,
    required this.kind,
    required this.syncRepository,
  });

  final LocalTree tree;
  final FieldActivityKind kind;
  final OfflineSyncRepository syncRepository;

  @override
  State<ActivityFormScreen> createState() => _ActivityFormScreenState();
}

class _ActivityFormScreenState extends State<ActivityFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _notesController = TextEditingController();
  final _num1Controller = TextEditingController();
  final _num2Controller = TextEditingController();
  final _num3Controller = TextEditingController();
  final _methodController = TextEditingController();
  final _picker = ImagePicker();

  String _selectedOption = 'tabur';
  String? _selectedProductId;
  String? _selectedSessionId;
  List<LocalProduct> _products = const [];
  List<LocalHarvestSession> _sessions = const [];
  final List<String> _photoPaths = [];
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _selectedOption = switch (widget.kind) {
      FieldActivityKind.fertilization => 'tabur',
      FieldActivityKind.flowering => 'mulai',
      FieldActivityKind.fruitDevelopment => 'pentil',
      _ => '',
    };
    _loadReferences();
  }

  Future<void> _loadReferences() async {
    if (widget.kind == FieldActivityKind.fertilization ||
        widget.kind == FieldActivityKind.spraying) {
      final products = await widget.syncRepository.getProducts();
      if (mounted) {
        setState(() {
          _products = products;
          if (products.isNotEmpty) _selectedProductId = products.first.id;
        });
      }
    } else if (widget.kind == FieldActivityKind.harvest) {
      final sessions = await widget.syncRepository
          .getHarvestSessionsForGarden(widget.tree.gardenId);
      if (mounted) {
        setState(() {
          _sessions = sessions;
          if (sessions.isNotEmpty) _selectedSessionId = sessions.first.id;
        });
      }
    }
  }

  @override
  void dispose() {
    _notesController.dispose();
    _num1Controller.dispose();
    _num2Controller.dispose();
    _num3Controller.dispose();
    _methodController.dispose();
    super.dispose();
  }

  String get _title => switch (widget.kind) {
        FieldActivityKind.measurement => 'Pengukuran Pohon',
        FieldActivityKind.fertilization => 'Catat Pemupukan',
        FieldActivityKind.spraying => 'Catat Penyemprotan',
        FieldActivityKind.flowering => 'Catat Pembungaan',
        FieldActivityKind.fruitDevelopment => 'Perkembangan Buah',
        FieldActivityKind.harvest => 'Catat Panen Pohon',
      };

  Future<void> _pickPhoto(ImageSource source) async {
    final file = await _picker.pickImage(
      source: source,
      imageQuality: 80,
      maxWidth: 1600,
    );
    if (file != null && mounted) {
      setState(() => _photoPaths.add(file.path));
    }
  }

  Future<void> _saveOffline() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);

    try {
      final nowIso = DateTime.now().toIso8601String();
      final todayStr = nowIso.substring(0, 10);
      late final String entityType;
      late final Map<String, Object?> payload;

      switch (widget.kind) {
        case FieldActivityKind.measurement:
          entityType = 'tree_measurement';
          payload = {
            'tree_id': widget.tree.id,
            'measured_at': nowIso,
            if (_num1Controller.text.isNotEmpty)
              'diameter_cm': double.parse(_num1Controller.text),
            if (_num2Controller.text.isNotEmpty)
              'height_m': double.parse(_num2Controller.text),
            if (_num3Controller.text.isNotEmpty)
              'canopy_width_m': double.parse(_num3Controller.text),
            'notes': _notesController.text.trim(),
          };
        case FieldActivityKind.fertilization:
          entityType = 'fertilization';
          payload = {
            'tree_id': widget.tree.id,
            'activity_date': nowIso,
            'method': _selectedOption,
            'notes': _notesController.text.trim(),
            'items': [
              {
                'product_id': _selectedProductId,
                'quantity': double.parse(_num1Controller.text),
              },
            ],
          };
        case FieldActivityKind.spraying:
          entityType = 'spraying';
          payload = {
            'tree_id': widget.tree.id,
            'activity_date': nowIso,
            'spraying_method': _methodController.text.trim(),
            'notes': _notesController.text.trim(),
            'items': [
              {
                'product_id': _selectedProductId,
                'quantity': double.parse(_num1Controller.text),
              },
            ],
          };
        case FieldActivityKind.flowering:
          entityType = 'flowering';
          payload = {
            'tree_id': widget.tree.id,
            'flowering_date': todayStr,
            'flowering_status': _selectedOption,
            if (_num1Controller.text.isNotEmpty)
              'flower_count': int.parse(_num1Controller.text),
            'notes': _notesController.text.trim(),
          };
        case FieldActivityKind.fruitDevelopment:
          entityType = 'fruit_development';
          payload = {
            'tree_id': widget.tree.id,
            'observation_date': todayStr,
            'development_stage': _selectedOption,
            if (_num1Controller.text.isNotEmpty)
              'fruit_count': int.parse(_num1Controller.text),
            'notes': _notesController.text.trim(),
          };
        case FieldActivityKind.harvest:
          entityType = 'harvest_record';
          payload = {
            'session_id': _selectedSessionId,
            'tree_id': widget.tree.id,
            'fruit_count': int.parse(_num1Controller.text),
            'total_weight_kg': double.parse(_num2Controller.text),
            'notes': _notesController.text.trim(),
          };
      }

      await widget.syncRepository.enqueueActivity(
        entityType: entityType,
        payload: payload,
        photoFilePaths: _photoPaths,
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Tersimpan lokal (Pending Sync). Siap disinkronkan.'),
          ),
        );
        Navigator.of(context).pop(true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal menyimpan: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('$_title • ${widget.tree.treeCode}')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            ..._buildSpecificFields(),
            const SizedBox(height: 14),
            TextFormField(
              controller: _notesController,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Catatan Lapangan',
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                OutlinedButton.icon(
                  onPressed: () => _pickPhoto(ImageSource.camera),
                  icon: const Icon(Icons.camera_alt_outlined),
                  label: const Text('Kamera'),
                ),
                const SizedBox(width: 10),
                OutlinedButton.icon(
                  onPressed: () => _pickPhoto(ImageSource.gallery),
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('Galeri'),
                ),
                const Spacer(),
                Text('${_photoPaths.length} foto'),
              ],
            ),
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: _saving ? null : _saveOffline,
              icon: const Icon(Icons.save_outlined),
              label: Text(_saving ? 'Menyimpan...' : 'Simpan Lokal (Offline)'),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _buildSpecificFields() {
    switch (widget.kind) {
      case FieldActivityKind.measurement:
        return [
          TextFormField(
            controller: _num1Controller,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration:
                const InputDecoration(labelText: 'Diameter Batang (cm)'),
            validator: (v) {
              if ((v == null || v.isEmpty) &&
                  _num2Controller.text.isEmpty &&
                  _num3Controller.text.isEmpty) {
                return 'Isi minimal satu ukuran pohon';
              }
              return null;
            },
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num2Controller,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Tinggi Pohon (m)'),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num3Controller,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Lebar Tajuk (m)'),
          ),
        ];

      case FieldActivityKind.fertilization:
        return [
          DropdownButtonFormField<String>(
            initialValue: _selectedOption,
            decoration: const InputDecoration(labelText: 'Metode Pemupukan'),
            items: const [
              DropdownMenuItem(value: 'tabur', child: Text('Tabur')),
              DropdownMenuItem(value: 'kocor', child: Text('Kocor')),
              DropdownMenuItem(
                value: 'semprot_daun',
                child: Text('Semprot Daun'),
              ),
              DropdownMenuItem(value: 'lainnya', child: Text('Lainnya')),
            ],
            onChanged: (v) => setState(() => _selectedOption = v ?? 'tabur'),
          ),
          const SizedBox(height: 12),
          _buildProductDropdown(),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num1Controller,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Jumlah Dosis'),
            validator: (v) =>
                (double.tryParse(v ?? '') ?? 0) <= 0 ? 'Harus > 0' : null,
          ),
        ];

      case FieldActivityKind.spraying:
        return [
          TextFormField(
            controller: _methodController,
            decoration: const InputDecoration(labelText: 'Metode Penyemprotan'),
          ),
          const SizedBox(height: 12),
          _buildProductDropdown(),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num1Controller,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Jumlah Dosis'),
            validator: (v) =>
                (double.tryParse(v ?? '') ?? 0) <= 0 ? 'Harus > 0' : null,
          ),
        ];

      case FieldActivityKind.flowering:
        return [
          DropdownButtonFormField<String>(
            initialValue: _selectedOption,
            decoration: const InputDecoration(labelText: 'Status Pembungaan'),
            items: const [
              DropdownMenuItem(value: 'mulai', child: Text('Mulai Berbunga')),
              DropdownMenuItem(
                value: 'berbunga',
                child: Text('Berbunga Penuh'),
              ),
              DropdownMenuItem(
                value: 'selesai',
                child: Text('Selesai Berbunga'),
              ),
            ],
            onChanged: (v) => setState(() => _selectedOption = v ?? 'mulai'),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num1Controller,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Perkiraan Jumlah Bunga (opsional)',
            ),
          ),
        ];

      case FieldActivityKind.fruitDevelopment:
        return [
          DropdownButtonFormField<String>(
            initialValue: _selectedOption,
            decoration:
                const InputDecoration(labelText: 'Tahap Perkembangan Buah'),
            items: const [
              DropdownMenuItem(value: 'pentil', child: Text('Pentil / Bakal')),
              DropdownMenuItem(value: 'pembesaran', child: Text('Pembesaran')),
              DropdownMenuItem(value: 'pematangan', child: Text('Pematangan')),
              DropdownMenuItem(value: 'siap_panen', child: Text('Siap Panen')),
              DropdownMenuItem(value: 'lainnya', child: Text('Lainnya')),
            ],
            onChanged: (v) => setState(() => _selectedOption = v ?? 'pentil'),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num1Controller,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Perkiraan Jumlah Buah (opsional)',
            ),
          ),
        ];

      case FieldActivityKind.harvest:
        return [
          DropdownButtonFormField<String>(
            initialValue: _selectedSessionId,
            decoration: const InputDecoration(labelText: 'Sesi Panen'),
            items: _sessions
                .map(
                  (s) => DropdownMenuItem(
                    value: s.id,
                    child: Text(s.sessionName ?? s.harvestDate),
                  ),
                )
                .toList(),
            onChanged: (v) => setState(() => _selectedSessionId = v),
            validator: (v) =>
                v == null ? 'Pilih sesi panen terlebih dahulu' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num1Controller,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Jumlah Buah'),
            validator: (v) =>
                (int.tryParse(v ?? '') ?? -1) < 0 ? 'Tidak boleh negatif' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _num2Controller,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Total Berat (kg)'),
            validator: (v) => (double.tryParse(v ?? '') ?? -1) < 0
                ? 'Tidak boleh negatif'
                : null,
          ),
        ];
    }
  }

  Widget _buildProductDropdown() {
    return DropdownButtonFormField<String>(
      initialValue: _selectedProductId,
      decoration: const InputDecoration(labelText: 'Produk Pertanian'),
      items: _products
          .map(
            (p) => DropdownMenuItem(
              value: p.id,
              child: Text('${p.name}${p.unit != null ? ' (${p.unit})' : ''}'),
            ),
          )
          .toList(),
      onChanged: (v) => setState(() => _selectedProductId = v),
      validator: (v) => v == null ? 'Pilih produk pertanian' : null,
    );
  }
}
