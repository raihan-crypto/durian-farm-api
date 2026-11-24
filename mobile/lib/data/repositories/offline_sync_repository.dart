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

  /// Active in-process sync lease IDs so recovery never resets an in-flight batch.
  final Set<String> _activeLeases = <String>{};

  static const Duration defaultStaleLeaseTimeout = Duration(minutes: 5);

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

      await _replaceRows(txn, 'trees', data['trees'], (r) {
        String? qrToken;
        if (r['qr_code'] is Map && (r['qr_code'] as Map)['is_active'] == false) {
          qrToken = null;
        } else if (r['qr_token'] is String && (r['qr_token'] as String).isNotEmpty) {
          qrToken = r['qr_token'] as String;
        } else if (r['qr_code'] is Map && (r['qr_code'] as Map)['is_active'] != false) {
          qrToken = (r['qr_code'] as Map)['public_token'] as String?;
        }

        return {
          'id': r['id'],
          'garden_id': r['garden_id'],
          'variety_id': r['variety_id'],
          'tree_code': r['tree_code'],
          'tree_name': r['tree_name'],
          'planted_at': r['planted_at'],
          'status': r['status'] ?? 'active',
          'qr_token': qrToken,
          'notes': r['notes'],
          'updated_at': r['updated_at'],
        };
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

  /// Queues a new field activity offline with a client-generated UUID v4 (SRS 12.4 & 12.5)
  /// bound immutably to the currently authenticated owner user.
  Future<SyncQueueItem> enqueueActivity({
    required String entityType,
    required Map<String, Object?> payload,
    List<String> photoFilePaths = const [],
    String? photoCaption,
    String? ownerUserIdOverride,
  }) async {
    final session = await _storage.getSession();
    final ownerUserId = ownerUserIdOverride ?? session?.userId;
    if (ownerUserId == null || ownerUserId.isEmpty) {
      throw const ApiException('Sesi login diperlukan untuk mencatat aktivitas offline');
    }

    final db = await _db.database;
    final entityId = _uuid.v4();
    final txId = _uuid.v4();
    final nowIso = DateTime.now().toUtc().toIso8601String();

    await db.transaction((txn) async {
      await txn.insert('sync_queue', {
        'client_transaction_id': txId,
        'owner_user_id': ownerUserId,
        'entity_type': entityType,
        'entity_id': entityId,
        'payload_json': jsonEncode(payload),
        'sync_status': LocalSyncStatus.pending.name,
        'syncing_started_at': null,
        'sync_lease_id': null,
        'created_at': nowIso,
      });

      final photoActivityType = entityType == 'harvest_record'
          ? 'harvest'
          : entityType;

      for (final filePath in photoFilePaths) {
        await txn.insert('photo_queue', {
          'id': _uuid.v4(),
          'owner_user_id': ownerUserId,
          'activity_type': photoActivityType,
          'activity_id': entityId,
          'local_file_path': filePath,
          'caption': photoCaption,
          'sync_status': LocalSyncStatus.pending.name,
          'syncing_started_at': null,
          'sync_lease_id': null,
          'created_at': nowIso,
        });
      }
    });

    return SyncQueueItem(
      clientTransactionId: txId,
      ownerUserId: ownerUserId,
      entityType: entityType,
      entityId: entityId,
      payload: payload,
      status: LocalSyncStatus.pending,
      createdAt: nowIso,
    );
  }

  /// Recovers stale `syncing` rows in both `sync_queue` and `photo_queue` whose lease
  /// has expired (e.g., after an OS kill or crash). Actively leased items in this process
  /// or within [staleAfter] are never reset.
  Future<int> recoverStaleSyncingItems({
    String? ownerUserId,
    Duration staleAfter = defaultStaleLeaseTimeout,
    DateTime? now,
    bool recoverAnyDeadProcessLease = false,
  }) async {
    final db = await _db.database;
    final currentUtc = (now ?? DateTime.now()).toUtc();
    final cutoffIso = currentUtc.subtract(staleAfter).toIso8601String();
    var recovered = 0;

    for (final table in const ['sync_queue', 'photo_queue']) {
      final whereParts = <String>['sync_status = ?'];
      final args = <Object?>[LocalSyncStatus.syncing.name];

      if (ownerUserId != null && ownerUserId.isNotEmpty) {
        whereParts.add('owner_user_id = ?');
        args.add(ownerUserId);
      }

      if (!recoverAnyDeadProcessLease) {
        whereParts.add('(syncing_started_at IS NULL OR syncing_started_at <= ?)');
        args.add(cutoffIso);
      }

      if (_activeLeases.isNotEmpty) {
        final placeholders = List.filled(_activeLeases.length, '?').join(', ');
        whereParts.add('(sync_lease_id IS NULL OR sync_lease_id NOT IN ($placeholders))');
        args.addAll(_activeLeases);
      }

      recovered += await db.update(
        table,
        {
          'sync_status': LocalSyncStatus.pending.name,
          'syncing_started_at': null,
          'sync_lease_id': null,
        },
        where: whereParts.join(' AND '),
        whereArgs: args,
      );
    }

    return recovered;
  }

  /// Called once on application cold start to recover rows left in `syncing`
  /// by a previously terminated process while preserving all active leases.
  Future<int> recoverInterruptedOnStartup() {
    return recoverStaleSyncingItems(recoverAnyDeadProcessLease: true);
  }

  /// Pushes pending/failed operations owned by the currently authenticated user
  /// to `/api/v1/sync/push` and uploads deferred photos.
  /// Never submits another user's records or quarantined unowned legacy records.
  Future<SyncRunResult> syncPending({
    String deviceIdentifier = 'flutter-mobile',
    Duration staleLeaseTimeout = defaultStaleLeaseTimeout,
    DateTime? now,
  }) async {
    final session = await _storage.getSession();
    if (session == null) {
      throw const ApiException('Sesi login tidak ditemukan');
    }
    final ownerUserId = session.userId;
    final baseUrl = await _storage.getBaseUrl();
    final db = await _db.database;

    // Recover expired leases for this owner before selecting pending work
    await recoverStaleSyncingItems(
      ownerUserId: ownerUserId,
      staleAfter: staleLeaseTimeout,
      now: now,
    );

    final pendingMaps = await db.query(
      'sync_queue',
      where: 'owner_user_id = ? AND sync_status IN (?, ?)',
      whereArgs: [
        ownerUserId,
        LocalSyncStatus.pending.name,
        LocalSyncStatus.failed.name,
      ],
      orderBy: 'created_at ASC',
      limit: 100,
    );

    var synced = 0;
    var duplicates = 0;
    var conflicts = 0;
    var failed = 0;

    if (pendingMaps.isNotEmpty) {
      final candidateItems = pendingMaps.map(SyncQueueItem.fromMap).toList();
      final leaseId = _uuid.v4();
      final leaseStartedAt = (now ?? DateTime.now()).toUtc().toIso8601String();
      _activeLeases.add(leaseId);

      final leasedItems = <SyncQueueItem>[];
      try {
        for (final item in candidateItems) {
          final updated = await db.update(
            'sync_queue',
            {
              'sync_status': LocalSyncStatus.syncing.name,
              'syncing_started_at': leaseStartedAt,
              'sync_lease_id': leaseId,
            },
            where:
                'client_transaction_id = ? AND owner_user_id = ? AND sync_status IN (?, ?)',
            whereArgs: [
              item.clientTransactionId,
              ownerUserId,
              LocalSyncStatus.pending.name,
              LocalSyncStatus.failed.name,
            ],
          );
          if (updated == 1) {
            leasedItems.add(item);
          }
        }

        if (leasedItems.isNotEmpty) {
          try {
            final response = await _api.pushSyncBatch(
              baseUrl: baseUrl,
              token: session.token,
              deviceIdentifier: deviceIdentifier,
              idempotencyKey:
                  'batch-$ownerUserId-${leasedItems.first.clientTransactionId}-${leasedItems.length}',
              operations: leasedItems
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
                    'syncing_started_at': null,
                    'sync_lease_id': null,
                    'error_message': null,
                    'synced_at': DateTime.now().toUtc().toIso8601String(),
                  },
                  where:
                      'client_transaction_id = ? AND owner_user_id = ? AND sync_lease_id = ?',
                  whereArgs: [txId, ownerUserId, leaseId],
                );
              } else if (status == 'conflict') {
                conflicts++;
                await db.update(
                  'sync_queue',
                  {
                    'sync_status': LocalSyncStatus.conflict.name,
                    'syncing_started_at': null,
                    'sync_lease_id': null,
                    'error_message': message,
                  },
                  where:
                      'client_transaction_id = ? AND owner_user_id = ? AND sync_lease_id = ?',
                  whereArgs: [txId, ownerUserId, leaseId],
                );
              } else {
                failed++;
                await db.update(
                  'sync_queue',
                  {
                    'sync_status': LocalSyncStatus.failed.name,
                    'syncing_started_at': null,
                    'sync_lease_id': null,
                    'error_message': message,
                  },
                  where:
                      'client_transaction_id = ? AND owner_user_id = ? AND sync_lease_id = ?',
                  whereArgs: [txId, ownerUserId, leaseId],
                );
              }
            }
          } catch (e) {
            for (final item in leasedItems) {
              await db.update(
                'sync_queue',
                {
                  'sync_status': LocalSyncStatus.failed.name,
                  'syncing_started_at': null,
                  'sync_lease_id': null,
                  'error_message': e.toString(),
                },
                where:
                    'client_transaction_id = ? AND owner_user_id = ? AND sync_lease_id = ?',
                whereArgs: [item.clientTransactionId, ownerUserId, leaseId],
              );
            }
            failed += leasedItems.length;
          }
        }
      } finally {
        _activeLeases.remove(leaseId);
      }
    }

    // Upload deferred photos owned by the authenticated user (SRS 12.7)
    var photosUploaded = 0;
    final pendingPhotos = await db.query(
      'photo_queue',
      where: 'owner_user_id = ? AND sync_status IN (?, ?)',
      whereArgs: [
        ownerUserId,
        LocalSyncStatus.pending.name,
        LocalSyncStatus.failed.name,
      ],
      orderBy: 'created_at ASC',
    );

    for (final map in pendingPhotos) {
      final photo = PendingPhotoItem.fromMap(map);
      if (!File(photo.localFilePath).existsSync()) {
        await db.update(
          'photo_queue',
          {
            'sync_status': LocalSyncStatus.failed.name,
            'syncing_started_at': null,
            'sync_lease_id': null,
            'error_message': 'File foto lokal tidak ditemukan',
          },
          where: 'id = ? AND owner_user_id = ?',
          whereArgs: [photo.id, ownerUserId],
        );
        continue;
      }

      final photoLeaseId = _uuid.v4();
      final photoLeaseAt = (now ?? DateTime.now()).toUtc().toIso8601String();
      _activeLeases.add(photoLeaseId);
      try {
        final claimed = await db.update(
          'photo_queue',
          {
            'sync_status': LocalSyncStatus.syncing.name,
            'syncing_started_at': photoLeaseAt,
            'sync_lease_id': photoLeaseId,
          },
          where: 'id = ? AND owner_user_id = ? AND sync_status IN (?, ?)',
          whereArgs: [
            photo.id,
            ownerUserId,
            LocalSyncStatus.pending.name,
            LocalSyncStatus.failed.name,
          ],
        );
        if (claimed != 1) {
          continue;
        }

        await _api.uploadActivityPhoto(
          baseUrl: baseUrl,
          token: session.token,
          activityType: photo.activityType,
          activityId: photo.activityId,
          filePath: photo.localFilePath,
          caption: photo.caption,
          idempotencyKey: 'photo-$ownerUserId-${photo.id}',
        );
        photosUploaded++;
        await db.update(
          'photo_queue',
          {
            'sync_status': LocalSyncStatus.synced.name,
            'syncing_started_at': null,
            'sync_lease_id': null,
            'error_message': null,
          },
          where: 'id = ? AND owner_user_id = ? AND sync_lease_id = ?',
          whereArgs: [photo.id, ownerUserId, photoLeaseId],
        );
      } catch (e) {
        await db.update(
          'photo_queue',
          {
            'sync_status': LocalSyncStatus.failed.name,
            'syncing_started_at': null,
            'sync_lease_id': null,
            'error_message': e.toString(),
          },
          where: 'id = ? AND owner_user_id = ? AND sync_lease_id = ?',
          whereArgs: [photo.id, ownerUserId, photoLeaseId],
        );
      } finally {
        _activeLeases.remove(photoLeaseId);
      }
    }

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

  Future<List<SyncQueueItem>> getSyncQueue({
    String? ownerUserId,
    bool includeAllUsers = false,
  }) async {
    final db = await _db.database;
    final effectiveOwner =
        includeAllUsers ? null : (ownerUserId ?? (await _storage.getSession())?.userId);

    final rows = effectiveOwner != null
        ? await db.query(
            'sync_queue',
            where: 'owner_user_id = ?',
            whereArgs: [effectiveOwner],
            orderBy: 'created_at DESC',
          )
        : await db.query('sync_queue', orderBy: 'created_at DESC');
    return rows.map(SyncQueueItem.fromMap).toList();
  }

  Future<List<PendingPhotoItem>> getPhotoQueue({
    String? ownerUserId,
    bool includeAllUsers = false,
  }) async {
    final db = await _db.database;
    final effectiveOwner =
        includeAllUsers ? null : (ownerUserId ?? (await _storage.getSession())?.userId);

    final rows = effectiveOwner != null
        ? await db.query(
            'photo_queue',
            where: 'owner_user_id = ?',
            whereArgs: [effectiveOwner],
            orderBy: 'created_at DESC',
          )
        : await db.query('photo_queue', orderBy: 'created_at DESC');
    return rows.map(PendingPhotoItem.fromMap).toList();
  }

  /// Returns quarantined legacy sync_queue records (owner_user_id IS NULL).
  Future<List<SyncQueueItem>> getQuarantinedSyncQueue() async {
    final db = await _db.database;
    final rows = await db.query(
      'sync_queue',
      where: 'owner_user_id IS NULL',
      orderBy: 'created_at DESC',
    );
    return rows.map(SyncQueueItem.fromMap).toList();
  }

  /// Returns quarantined legacy photo_queue records (owner_user_id IS NULL).
  Future<List<PendingPhotoItem>> getQuarantinedPhotoQueue() async {
    final db = await _db.database;
    final rows = await db.query(
      'photo_queue',
      where: 'owner_user_id IS NULL',
      orderBy: 'created_at DESC',
    );
    return rows.map(PendingPhotoItem.fromMap).toList();
  }

  /// Explicit user-authorized recovery for legacy unowned queue records created before v2 migration.
  /// Never overwrites records that already have an owner_user_id.
  Future<int> claimQuarantinedRecordForCurrentUser(String clientTransactionId) async {
    final session = await _storage.getSession();
    if (session == null) {
      throw const ApiException('Sesi login diperlukan untuk mengklaim data legacy');
    }
    final db = await _db.database;
    return db.transaction((txn) async {
      final rows = await txn.query(
        'sync_queue',
        columns: ['entity_id'],
        where: 'client_transaction_id = ? AND owner_user_id IS NULL',
        whereArgs: [clientTransactionId],
      );
      if (rows.isEmpty) return 0;

      final entityId = rows.first['entity_id'] as String;
      final updated = await txn.update(
        'sync_queue',
        {
          'owner_user_id': session.userId,
          'sync_status': LocalSyncStatus.pending.name,
          'syncing_started_at': null,
          'sync_lease_id': null,
        },
        where: 'client_transaction_id = ? AND owner_user_id IS NULL',
        whereArgs: [clientTransactionId],
      );

      await txn.update(
        'photo_queue',
        {
          'owner_user_id': session.userId,
          'sync_status': LocalSyncStatus.pending.name,
          'syncing_started_at': null,
          'sync_lease_id': null,
        },
        where: 'activity_id = ? AND owner_user_id IS NULL',
        whereArgs: [entityId],
      );

      return updated;
    });
  }

  /// Returns the count of unsynchronized offline activities (pending or failed)
  /// scoped to the active session owner when logged in.
  Future<int> getPendingActivityCount({String? ownerUserId}) async {
    final effectiveOwner = ownerUserId ?? (await _storage.getSession())?.userId;
    return _db.getPendingSyncCount(ownerUserId: effectiveOwner);
  }

  /// Returns the count of unsynchronized deferred photos (pending or failed)
  /// scoped to the active session owner when logged in.
  Future<int> getPendingPhotoCount({String? ownerUserId}) async {
    final effectiveOwner = ownerUserId ?? (await _storage.getSession())?.userId;
    return _db.getPendingPhotoCount(ownerUserId: effectiveOwner);
  }

  Future<int> getQuarantinedActivityCount() => _db.getQuarantinedSyncCount();

  Future<int> getQuarantinedPhotoCount() => _db.getQuarantinedPhotoCount();

  Future<String?> getLastSyncAt() => _storage.getLastSyncAt();

  /// Pulls all incremental delta pages from server since lastSyncAt (SRS 12.3 & 16.3),
  /// applying tombstones and advancing lastSyncAt ONLY after all pages succeed.
  /// If lastSyncAt is null, falls back to full bootstrapFromServer().
  Future<int> pullDeltaFromServer({int limit = 200}) async {
    final lastSyncAt = await _storage.getLastSyncAt();
    if (lastSyncAt == null) {
      await bootstrapFromServer();
      return -1;
    }

    final session = await _storage.getSession();
    if (session == null) return 0;
    final baseUrl = await _storage.getBaseUrl();

    var totalChanged = 0;
    String? cursor;
    String? pinnedSnapshotTime;
    var hasMore = true;
    var pagesFetched = 0;
    const maxPages = 1000;

    while (hasMore && pagesFetched < maxPages) {
      final response = await _api.pullSyncChanges(
        baseUrl: baseUrl,
        token: session.token,
        since: lastSyncAt,
        cursor: cursor,
        limit: limit,
      );
      final responseData = response['data'] as Map<String, Object?>?;
      if (responseData == null) break;

      pinnedSnapshotTime ??=
          (responseData['snapshot_time'] ?? responseData['server_time']) as String?;

      final changes =
          (responseData['changes'] as Map<String, Object?>?) ?? responseData;
      final deletedIds = _extractDeletedIds(responseData);

      totalChanged += await persistDeltaChanges(changes, deletedIds: deletedIds);

      final nextCursor = responseData['next_cursor'] as String?;
      hasMore = responseData['has_more'] == true &&
          nextCursor != null &&
          nextCursor.isNotEmpty;
      cursor = nextCursor;
      pagesFetched++;
    }

    if (!hasMore && pinnedSnapshotTime != null) {
      await _storage.saveLastSyncAt(pinnedSnapshotTime);
    }

    return totalChanged;
  }

  Map<String, List<String>> _extractDeletedIds(Map<String, Object?> responseData) {
    final result = <String, List<String>>{};

    final rawDeletedIds = responseData['deleted_ids'];
    if (rawDeletedIds is Map<String, Object?>) {
      for (final entry in rawDeletedIds.entries) {
        final list = (entry.value as List<Object?>? ?? [])
            .whereType<String>()
            .toList();
        if (list.isNotEmpty) {
          result[entry.key] = list;
        }
      }
      return result;
    }

    final rawTombstones = responseData['tombstones'];
    if (rawTombstones is Map<String, Object?>) {
      for (final entry in rawTombstones.entries) {
        final items = (entry.value as List<Object?>? ?? [])
            .whereType<Map<String, Object?>>()
            .map((e) => e['id'])
            .whereType<String>()
            .toList();
        if (items.isNotEmpty) {
          result[entry.key] = items;
        }
      }
    }

    return result;
  }

  /// Persists incremental delta changes and applies soft-delete tombstones into local SQLite
  /// inside a single atomic transaction without wiping existing master records or offline queues.
  Future<int> persistDeltaChanges(
    Map<String, Object?> data, {
    Map<String, List<String>> deletedIds = const {},
  }) async {
    final db = await _db.database;
    var totalChanged = 0;

    await db.transaction((txn) async {
      totalChanged += await _upsertRows(txn, 'villages', data['villages'], (r) => {
            'id': r['id'],
            'name': r['name'],
            'district': r['district'],
            'regency': r['regency'],
            'province': r['province'],
            'updated_at': r['updated_at'],
          });

      totalChanged += await _upsertRows(txn, 'blocks', data['blocks'], (r) => {
            'id': r['id'],
            'village_id': r['village_id'],
            'name': r['name'],
            'area_hectares': (r['area_hectares'] as num?)?.toDouble(),
            'description': r['description'],
            'updated_at': r['updated_at'],
          });

      totalChanged += await _upsertRows(txn, 'gardens', data['gardens'], (r) => {
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

      totalChanged += await _upsertRows(txn, 'durian_varieties', data['durian_varieties'], (r) => {
            'id': r['id'],
            'code': r['code'],
            'name': r['name'],
            'origin': r['origin'],
            'description': r['description'],
            'updated_at': r['updated_at'],
          });

      totalChanged += await _upsertRows(txn, 'product_categories', data['product_categories'], (r) => {
            'id': r['id'],
            'name': r['name'],
            'description': r['description'],
            'updated_at': r['updated_at'],
          });

      totalChanged += await _upsertRows(txn, 'agricultural_products', data['agricultural_products'], (r) => {
            'id': r['id'],
            'category_id': r['category_id'],
            'name': r['name'],
            'brand': r['brand'],
            'unit': r['unit'],
            'updated_at': r['updated_at'],
          });

      totalChanged += await _upsertTrees(txn, data['trees']);

      totalChanged += await _upsertRows(txn, 'harvest_seasons', data['harvest_seasons'], (r) => {
            'id': r['id'],
            'garden_id': r['garden_id'],
            'name': r['name'],
            'start_date': r['start_date'],
            'end_date': r['end_date'],
            'status': r['status'],
            'updated_at': r['updated_at'],
          });

      totalChanged += await _upsertRows(txn, 'harvest_sessions', data['harvest_sessions'], (r) => {
            'id': r['id'],
            'season_id': r['season_id'],
            'garden_id': r['garden_id'],
            'harvest_date': r['harvest_date'],
            'session_name': r['session_name'],
            'updated_at': r['updated_at'],
          });

      // Apply soft-delete tombstones to local master tables (never touches sync_queue/photo_queue)
      const localMasterTables = [
        'villages',
        'blocks',
        'gardens',
        'durian_varieties',
        'product_categories',
        'agricultural_products',
        'trees',
        'harvest_seasons',
        'harvest_sessions',
      ];
      for (final table in localMasterTables) {
        final idsToDelete = deletedIds[table];
        if (idsToDelete != null && idsToDelete.isNotEmpty) {
          for (final id in idsToDelete) {
            totalChanged += await txn.delete(
              table,
              where: 'id = ?',
              whereArgs: [id],
            );
          }
        }
      }
    });

    return totalChanged;
  }

  Future<int> _upsertTrees(Transaction txn, Object? rawList) async {
    final rows = (rawList as List<Object?>? ?? []).cast<Map<String, Object?>>();
    for (final r in rows) {
      final id = r['id'] as String?;
      if (id == null) continue;

      final hasQrTokenKey = r.containsKey('qr_token');
      final hasQrCodeKey = r.containsKey('qr_code');

      String? targetQrToken;

      if (!hasQrTokenKey && !hasQrCodeKey) {
        // 1. QR field tidak tersedia dalam payload:
        //    Pertahankan qr_token yang sudah ada di SQLite lokal.
        final existing = await txn.query(
          'trees',
          columns: ['qr_token'],
          where: 'id = ?',
          whereArgs: [id],
          limit: 1,
        );
        if (existing.isNotEmpty) {
          targetQrToken = existing.first['qr_token'] as String?;
        }
      } else {
        // 2 & 3. QR field tersedia dalam payload:
        // Cek objek relasi qr_code terlebih dahulu jika disertakan
        if (hasQrCodeKey && r['qr_code'] is Map) {
          final qrMap = r['qr_code'] as Map<String, Object?>;
          if (qrMap['is_active'] == false) {
            // 3. QR tersedia dan secara eksplisit tidak aktif:
            //    Set qr_token lokal menjadi null, jangan fallback ke token lama.
            targetQrToken = null;
          } else {
            // 2. QR tersedia dan aktif:
            final token = qrMap['public_token'] as String?;
            targetQrToken = (token != null && token.isNotEmpty) ? token : null;
          }
        } else if (hasQrTokenKey) {
          final token = r['qr_token'] as String?;
          if (token != null && token.isNotEmpty) {
            // 2. QR tersedia dan aktif:
            targetQrToken = token;
          } else {
            // 3. qr_token: null secara eksplisit -> set null, jangan fallback ke token lama.
            targetQrToken = null;
          }
        } else {
          // hasQrCodeKey is true tetapi bernilai null -> QR tidak aktif / dihapus
          targetQrToken = null;
        }
      }

      await txn.insert(
        'trees',
        {
          'id': id,
          'garden_id': r['garden_id'],
          'variety_id': r['variety_id'],
          'tree_code': r['tree_code'],
          'tree_name': r['tree_name'],
          'planted_at': r['planted_at'],
          'status': r['status'] ?? 'active',
          'qr_token': targetQrToken,
          'notes': r['notes'],
          'updated_at': r['updated_at'],
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    return rows.length;
  }

  Future<int> _upsertRows(
    Transaction txn,
    String table,
    Object? rawList,
    Map<String, Object?> Function(Map<String, Object?> row) mapper,
  ) async {
    final rows = (rawList as List<Object?>? ?? []).cast<Map<String, Object?>>();
    for (final row in rows) {
      await txn.insert(
        table,
        mapper(row),
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    return rows.length;
  }

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
