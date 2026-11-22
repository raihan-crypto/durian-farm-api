import 'dart:convert';
import 'dart:io';
import 'package:sqflite/sqflite.dart';
import 'package:uuid/uuid.dart';
import '../local/local_database.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/session_storage.dart';

/// Core offline-first repository: persists master data & queued field activities
/// in local SQLite, assigns client UUIDs, and syncs idempotently with Laravel API (SRS Bab 12).
class OfflineSyncRepository {
  OfflineSyncRepository({
    required LocalDatabase localDatabase,
    required ApiClient apiClient,
    required SessionStorage sessionStorage,
    Uuid? uuid,
  })  : _db = localDatabase,
        _api = apiClient,
        _storage = sessionStorage,
        _uuid = uuid ?? const Uuid();

  final LocalDatabase _db;
  final ApiClient _api;
  final SessionStorage _storage;
  final Uuid _uuid;

  /// Downloads initial territory, trees, varieties, products and harvest seasons into SQLite.
  Future<void> bootstrapFromServer() async {
    final session = await _storage.getSession();
    if (session == null) return;
    final baseUrl = await _storage.getBaseUrl();

    final response = await _api.fetchBootstrap(
      baseUrl: baseUrl,
      token: session.token,
    );
    final data = response['data'] as Map<String, Object?>;
    await persistBootstrapSnapshot(data);

    final serverTime = data['server_time'] as String?;
    if (serverTime != null) {
      await _storage.saveLastSyncAt(serverTime);
    }
  }

  /// Persists a bootstrap payload into local SQLite inside a single atomic transaction.
  Future<void> persistBootstrapSnapshot(Map<String, Object?> data) async {
    final db = await _db.database;

    await db.transaction((txn) async {
      await _replaceRows(txn, 'villages', data['villages'], (r) => {
            'id': r['id'],
            'name': r['name'],
            'district': r['district'],
            'regency': r['regency'],
            'province': r['province'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'blocks', data['blocks'], (r) => {
            'id': r['id'],
            'village_id': r['village_id'],
            'name': r['name'],
            'area_hectares': (r['area_hectares'] as num?)?.toDouble(),
            'description': r['description'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'gardens', data['gardens'], (r) => {
            'id': r['id'],
            'block_id': r['block_id'],
            'name': r['name'],
            'area_hectares': (r['area_hectares'] as num?)?.toDouble(),
            'address': r['address'],
            'latitude': (r['latitude'] as num?)?.toDouble(),
            'longitude': (r['longitude'] as num?)?.toDouble(),
            'description': r['description'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'durian_varieties', data['durian_varieties'], (r) => {
            'id': r['id'],
            'code': r['code'],
            'name': r['name'],
            'origin': r['origin'],
            'description': r['description'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'product_categories', data['product_categories'], (r) => {
            'id': r['id'],
            'name': r['name'],
            'description': r['description'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'agricultural_products', data['agricultural_products'], (r) => {
            'id': r['id'],
            'category_id': r['category_id'],
            'name': r['name'],
            'brand': r['brand'],
            'unit': r['unit'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'trees', data['trees'], (r) => {
            'id': r['id'],
            'garden_id': r['garden_id'],
            'variety_id': r['variety_id'],
            'tree_code': r['tree_code'],
            'tree_name': r['tree_name'],
            'planted_at': r['planted_at'],
            'status': r['status'] ?? 'active',
            'qr_token': r['qr_token'],
            'notes': r['notes'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'harvest_seasons', data['harvest_seasons'], (r) => {
            'id': r['id'],
            'garden_id': r['garden_id'],
            'name': r['name'],
            'start_date': r['start_date'],
            'end_date': r['end_date'],
            'status': r['status'],
            'updated_at': r['updated_at'],
          });

      await _replaceRows(txn, 'harvest_sessions', data['harvest_sessions'], (r) => {
            'id': r['id'],
            'season_id': r['season_id'],
            'garden_id': r['garden_id'],
            'harvest_date': r['harvest_date'],
            'session_name': r['session_name'],
            'updated_at': r['updated_at'],
          });
    });
  }

  /// Queues a new field activity offline with a client-generated UUID v4 (SRS 12.4 & 12.5).
  Future<SyncQueueItem> enqueueActivity({
    required String entityType,
    required Map<String, Object?> payload,
    List<String> photoFilePaths = const [],
    String? photoCaption,
  }) async {
    final db = await _db.database;
    final entityId = _uuid.v4();
    final txId = _uuid.v4();
    final nowIso = DateTime.now().toIso8601String();

    await db.transaction((txn) async {
      await txn.insert('sync_queue', {
        'client_transaction_id': txId,
        'entity_type': entityType,
        'entity_id': entityId,
        'payload_json': jsonEncode(payload),
        'sync_status': LocalSyncStatus.pending.name,
        'created_at': nowIso,
      });

      final photoActivityType = entityType == 'harvest_record'
          ? 'harvest'
          : entityType;

      for (final filePath in photoFilePaths) {
        await txn.insert('photo_queue', {
          'id': _uuid.v4(),
          'activity_type': photoActivityType,
          'activity_id': entityId,
          'local_file_path': filePath,
          'caption': photoCaption,
          'sync_status': LocalSyncStatus.pending.name,
          'created_at': nowIso,
        });
      }
    });

    return SyncQueueItem(
      clientTransactionId: txId,
      entityType: entityType,
      entityId: entityId,
      payload: payload,
      status: LocalSyncStatus.pending,
      createdAt: nowIso,
    );
  }

  /// Pushes pending/failed operations to `/api/v1/sync/push` and uploads deferred photos.
  Future<SyncRunResult> syncPending({String deviceIdentifier = 'flutter-mobile'}) async {
    final session = await _storage.getSession();
    if (session == null) {
      throw const ApiException('Sesi login tidak ditemukan');
    }
    final baseUrl = await _storage.getBaseUrl();
    final db = await _db.database;

    final pendingMaps = await db.query(
      'sync_queue',
      where: 'sync_status IN (?, ?)',
      whereArgs: [LocalSyncStatus.pending.name, LocalSyncStatus.failed.name],
      orderBy: 'created_at ASC',
      limit: 100,
    );

    var synced = 0;
    var duplicates = 0;
    var conflicts = 0;
    var failed = 0;

    if (pendingMaps.isNotEmpty) {
      final items = pendingMaps.map(SyncQueueItem.fromMap).toList();

      for (final item in items) {
        await db.update(
          'sync_queue',
          {'sync_status': LocalSyncStatus.syncing.name},
          where: 'client_transaction_id = ?',
          whereArgs: [item.clientTransactionId],
        );
      }

      try {
        final response = await _api.pushSyncBatch(
          baseUrl: baseUrl,
          token: session.token,
          deviceIdentifier: deviceIdentifier,
          idempotencyKey: 'batch-${items.first.clientTransactionId}-${items.length}',
          operations: items
              .map((i) => {
                    'client_transaction_id': i.clientTransactionId,
                    'entity_type': i.entityType,
                    'entity_id': i.entityId,
                    'payload': i.payload,
                  })
              .toList(),
        );

        final data = response['data'] as Map<String, Object?>;
        final results =
            (data['results'] as List<Object?>).cast<Map<String, Object?>>();

        for (final result in results) {
          final txId = result['client_transaction_id'] as String;
          final status = result['status'] as String;
          final message = result['message'] as String?;

          if (status == 'synced') {
            if (result['duplicate'] == true) {
              duplicates++;
            } else {
              synced++;
            }
            await db.update(
              'sync_queue',
              {
                'sync_status': LocalSyncStatus.synced.name,
                'error_message': null,
                'synced_at': DateTime.now().toIso8601String(),
              },
              where: 'client_transaction_id = ?',
              whereArgs: [txId],
            );
          } else if (status == 'conflict') {
            conflicts++;
            await db.update(
              'sync_queue',
              {
                'sync_status': LocalSyncStatus.conflict.name,
                'error_message': message,
              },
              where: 'client_transaction_id = ?',
              whereArgs: [txId],
            );
          } else {
            failed++;
            await db.update(
              'sync_queue',
              {
                'sync_status': LocalSyncStatus.failed.name,
                'error_message': message,
              },
              where: 'client_transaction_id = ?',
              whereArgs: [txId],
            );
          }
        }
      } catch (e) {
        for (final item in items) {
          await db.update(
            'sync_queue',
            {
              'sync_status': LocalSyncStatus.failed.name,
              'error_message': e.toString(),
            },
            where: 'client_transaction_id = ?',
            whereArgs: [item.clientTransactionId],
          );
        }
        rethrow;
      }
    }

    // Upload deferred photos whose parent activities are synced (SRS 12.7)
    var photosUploaded = 0;
    final pendingPhotos = await db.query(
      'photo_queue',
      where: 'sync_status IN (?, ?)',
      whereArgs: [LocalSyncStatus.pending.name, LocalSyncStatus.failed.name],
      orderBy: 'created_at ASC',
    );

    for (final map in pendingPhotos) {
      final photo = PendingPhotoItem.fromMap(map);
      if (!File(photo.localFilePath).existsSync()) {
        await db.update(
          'photo_queue',
          {
            'sync_status': LocalSyncStatus.failed.name,
            'error_message': 'File foto lokal tidak ditemukan',
          },
          where: 'id = ?',
          whereArgs: [photo.id],
        );
        continue;
      }

      try {
        await _api.uploadActivityPhoto(
          baseUrl: baseUrl,
          token: session.token,
          activityType: photo.activityType,
          activityId: photo.activityId,
          filePath: photo.localFilePath,
          caption: photo.caption,
          idempotencyKey: 'photo-${photo.id}',
        );
        photosUploaded++;
        await db.update(
          'photo_queue',
          {'sync_status': LocalSyncStatus.synced.name, 'error_message': null},
          where: 'id = ?',
          whereArgs: [photo.id],
        );
      } catch (e) {
        await db.update(
          'photo_queue',
          {
            'sync_status': LocalSyncStatus.failed.name,
            'error_message': e.toString(),
          },
          where: 'id = ?',
          whereArgs: [photo.id],
        );
      }
    }

    await _storage.saveLastSyncAt(DateTime.now().toIso8601String());

    return SyncRunResult(
      syncedCount: synced,
      duplicateCount: duplicates,
      conflictCount: conflicts,
      failedCount: failed,
      photosUploaded: photosUploaded,
    );
  }

  Future<List<LocalGarden>> getGardens() async {
    final db = await _db.database;
    final rows = await db.rawQuery('''
      SELECT g.id, g.block_id, g.name, g.area_hectares,
             b.name AS block_name, v.name AS village_name
      FROM gardens g
      LEFT JOIN blocks b ON g.block_id = b.id
      LEFT JOIN villages v ON b.village_id = v.id
      ORDER BY g.name ASC
    ''');
    return rows.map(LocalGarden.fromMap).toList();
  }

  Future<List<LocalTree>> getTrees({String? search, String? gardenId}) async {
    final db = await _db.database;
    final whereParts = <String>[];
    final args = <Object?>[];

    if (gardenId != null && gardenId.isNotEmpty) {
      whereParts.add('t.garden_id = ?');
      args.add(gardenId);
    }
    if (search != null && search.trim().isNotEmpty) {
      final q = '%${search.trim()}%';
      whereParts.add('(t.tree_code LIKE ? OR t.tree_name LIKE ? OR v.name LIKE ?)');
      args.addAll([q, q, q]);
    }

    final whereSql = whereParts.isEmpty ? '' : 'WHERE ${whereParts.join(' AND ')}';
    final rows = await db.rawQuery('''
      SELECT t.*, v.name AS variety_name, g.name AS garden_name
      FROM trees t
      LEFT JOIN durian_varieties v ON t.variety_id = v.id
      LEFT JOIN gardens g ON t.garden_id = g.id
      $whereSql
      ORDER BY t.tree_code ASC
    ''', args);
    return rows.map(LocalTree.fromMap).toList();
  }

  /// Looks up a tree offline by scanned QR URL, token, or tree_code (SRS 11.6).
  Future<LocalTree?> findTreeByQrOrCode(String rawValue) async {
    final db = await _db.database;
    final cleaned = rawValue.trim();
    final uri = Uri.tryParse(cleaned);
    final extractedToken = (uri != null && uri.pathSegments.isNotEmpty)
        ? uri.pathSegments.last
        : cleaned;

    final rows = await db.rawQuery('''
      SELECT t.*, v.name AS variety_name, g.name AS garden_name
      FROM trees t
      LEFT JOIN durian_varieties v ON t.variety_id = v.id
      LEFT JOIN gardens g ON t.garden_id = g.id
      WHERE t.qr_token = ? OR t.tree_code = ? OR t.id = ?
      LIMIT 1
    ''', [extractedToken, cleaned, cleaned]);

    if (rows.isEmpty) return null;
    return LocalTree.fromMap(rows.first);
  }

  Future<List<LocalProduct>> getProducts() async {
    final db = await _db.database;
    final rows = await db.rawQuery('''
      SELECT p.*, c.name AS category_name
      FROM agricultural_products p
      LEFT JOIN product_categories c ON p.category_id = c.id
      ORDER BY p.name ASC
    ''');
    return rows.map(LocalProduct.fromMap).toList();
  }

  Future<List<LocalHarvestSession>> getHarvestSessionsForGarden(String gardenId) async {
    final db = await _db.database;
    final rows = await db.query(
      'harvest_sessions',
      where: 'garden_id = ?',
      whereArgs: [gardenId],
      orderBy: 'harvest_date DESC',
    );
    return rows.map(LocalHarvestSession.fromMap).toList();
  }

  Future<List<SyncQueueItem>> getSyncQueue() async {
    final db = await _db.database;
    final rows = await db.query('sync_queue', orderBy: 'created_at DESC');
    return rows.map(SyncQueueItem.fromMap).toList();
  }

  Future<List<PendingPhotoItem>> getPhotoQueue() async {
    final db = await _db.database;
    final rows = await db.query('photo_queue', orderBy: 'created_at DESC');
    return rows.map(PendingPhotoItem.fromMap).toList();
  }

  Future<String?> getLastSyncAt() => _storage.getLastSyncAt();

  Future<void> _replaceRows(
    Transaction txn,
    String table,
    Object? rawList,
    Map<String, Object?> Function(Map<String, Object?> row) mapper,
  ) async {
    await txn.delete(table);
    final rows = (rawList as List<Object?>? ?? []).cast<Map<String, Object?>>();
    for (final row in rows) {
      await txn.insert(
        table,
        mapper(row),
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
  }
}
