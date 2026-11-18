import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import '../../../data/models/models.dart';
import '../../../data/repositories/offline_sync_repository.dart';

class DashboardViewModel extends ChangeNotifier {
  DashboardViewModel({
    required this.syncRepository,
    Connectivity? connectivity,
  }) : _connectivity = connectivity ?? Connectivity();

  final OfflineSyncRepository syncRepository;
  final Connectivity _connectivity;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _isSyncing = false;
  bool get isSyncing => _isSyncing;

  bool _isOnline = true;
  bool get isOnline => _isOnline;

  String? _statusMessage;
  String? get statusMessage => _statusMessage;

  String? _lastSyncAt;
  String? get lastSyncAt => _lastSyncAt;

  List<LocalGarden> _gardens = const [];
  List<LocalGarden> get gardens => _gardens;

  List<LocalTree> _trees = const [];
  List<LocalTree> get trees => _trees;

  List<SyncQueueItem> _queue = const [];
  List<SyncQueueItem> get queue => _queue;

  List<PendingPhotoItem> _photoQueue = const [];
  List<PendingPhotoItem> get photoQueue => _photoQueue;

  int get pendingCount => _queue
      .where((q) =>
          q.status == LocalSyncStatus.pending ||
          q.status == LocalSyncStatus.failed)
      .length;

  int get conflictCount =>
      _queue.where((q) => q.status == LocalSyncStatus.conflict).length;

  Future<void> initialize() async {
    _connectivitySub?.cancel();
    _connectivitySub = _connectivity.onConnectivityChanged.listen((results) {
      _isOnline = results.any((r) => r != ConnectivityResult.none);
      notifyListeners();
    });

    try {
      final initial = await _connectivity.checkConnectivity();
      _isOnline = initial.any((r) => r != ConnectivityResult.none);
    } catch (_) {
      _isOnline = true;
    }

    await refreshLocalData();
  }

  Future<void> refreshLocalData() async {
    _isLoading = true;
    notifyListeners();
    try {
      _gardens = await syncRepository.getGardens();
      _trees = await syncRepository.getTrees();
      _queue = await syncRepository.getSyncQueue();
      _photoQueue = await syncRepository.getPhotoQueue();
      _lastSyncAt = await syncRepository.getLastSyncAt();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> syncNow() async {
    if (_isSyncing) return;
    _isSyncing = true;
    _statusMessage = null;
    notifyListeners();

    try {
      final result = await syncRepository.syncPending();
      await syncRepository.bootstrapFromServer();
      await refreshLocalData();

      _statusMessage =
          'Sinkronisasi selesai: ${result.syncedCount} terkirim, '
          '${result.photosUploaded} foto, ${result.conflictCount} konflik, '
          '${result.failedCount} gagal.';
    } catch (e) {
      _statusMessage = 'Gagal sinkronisasi: $e';
    } finally {
      _isSyncing = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _connectivitySub?.cancel();
    super.dispose();
  }
}
