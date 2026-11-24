import 'dart:convert';
import 'package:durian_farm_mobile/data/local/local_database.dart';
import 'package:durian_farm_mobile/data/models/models.dart';
import 'package:durian_farm_mobile/data/repositories/auth_repository.dart';
import 'package:durian_farm_mobile/data/repositories/offline_sync_repository.dart';
import 'package:durian_farm_mobile/data/services/api_client.dart';
import 'package:durian_farm_mobile/data/services/session_storage.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

void main() {
  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
  });

  test('persists bootstrap snapshot into SQLite and supports offline QR lookup', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    final repo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: ApiClient(),
      sessionStorage: storage,
    );

    await repo.persistBootstrapSnapshot({
      'villages': [
        {'id': 'v-1', 'name': 'Desa Sukamaju', 'district': 'Cibadak'},
      ],
      'blocks': [
        {'id': 'b-1', 'village_id': 'v-1', 'name': 'Blok A'},
      ],
      'gardens': [
        {'id': 'g-1', 'block_id': 'b-1', 'name': 'Kebun Musang King', 'area_hectares': 3.5},
      ],
      'durian_varieties': [
        {'id': 'var-1', 'code': 'MK', 'name': 'Musang King'},
      ],
      'product_categories': [
        {'id': 'cat-1', 'name': 'Pupuk'},
      ],
      'agricultural_products': [
        {'id': 'prod-1', 'category_id': 'cat-1', 'name': 'NPK 16-16-16', 'unit': 'gram'},
      ],
      'trees': [
        {
          'id': 'tree-1',
          'garden_id': 'g-1',
          'variety_id': 'var-1',
          'tree_code': 'MK-001',
          'tree_name': 'Pohon Induk 1',
          'status': 'active',
          'qr_token': 'abcdef1234567890abcdef1234567890abcdef12',
        },
      ],
      'harvest_seasons': [],
      'harvest_sessions': [],
    });

    final gardens = await repo.getGardens();
    expect(gardens.length, 1);
    expect(gardens.first.villageName, 'Desa Sukamaju');

    final foundByUrl = await repo.findTreeByQrOrCode(
      'https://durian.test/t/abcdef1234567890abcdef1234567890abcdef12',
    );
    expect(foundByUrl, isNotNull);
    expect(foundByUrl!.treeCode, 'MK-001');
    expect(foundByUrl.varietyName, 'Musang King');

    await db.close();
  });

  test('enqueues offline activities with client UUID and marks synced/conflict on push', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'test-token',
      userId: 'user-1',
      userName: 'Petani Test',
      userEmail: 'petani@test.local',
      roles: ['farmer'],
    ));

    final mockHttp = MockClient((request) async {
      final body = jsonDecode(request.body) as Map<String, Object?>;
      final ops = (body['operations'] as List<Object?>).cast<Map<String, Object?>>();

      return http.Response(
        jsonEncode({
          'success': true,
          'message': 'OK',
          'data': {
            'results': [
              {
                'client_transaction_id': ops[0]['client_transaction_id'],
                'entity_type': ops[0]['entity_type'],
                'entity_id': ops[0]['entity_id'],
                'status': 'synced',
                'duplicate': false,
              },
              {
                'client_transaction_id': ops[1]['client_transaction_id'],
                'entity_type': ops[1]['entity_type'],
                'entity_id': ops[1]['entity_id'],
                'status': 'conflict',
                'message': 'Konflik panen pada sesi yang sama',
              },
            ],
          },
        }),
        200,
        headers: {'content-type': 'application/json'},
      );
    });

    final repo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
    );

    final item1 = await repo.enqueueActivity(
      entityType: 'tree_measurement',
      payload: {'tree_id': 'tree-1', 'height_m': 4.2},
    );
    final item2 = await repo.enqueueActivity(
      entityType: 'harvest_record',
      payload: {'session_id': 'sess-1', 'tree_id': 'tree-1', 'fruit_count': 5, 'total_weight_kg': 10},
    );

    expect(item1.entityId, isNotEmpty);
    expect(item2.status, LocalSyncStatus.pending);

    final result = await repo.syncPending();
    expect(result.syncedCount, 1);
    expect(result.conflictCount, 1);

    final queue = await repo.getSyncQueue();
    final statuses = {for (final q in queue) q.clientTransactionId: q.status};
    expect(statuses[item1.clientTransactionId], LocalSyncStatus.synced);
    expect(statuses[item2.clientTransactionId], LocalSyncStatus.conflict);

    await db.close();
  });

  test('RSK-01 Case 1: logout without pending data clears session and leaves database valid', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'valid-token',
      userId: 'user-1',
      userName: 'Petani Clean',
      userEmail: 'petani.clean@test.local',
      roles: ['farmer'],
    ));

    final mockHttp = MockClient((request) async {
      return http.Response(jsonEncode({'success': true, 'message': 'OK'}), 200);
    });

    final authRepo = AuthRepository(
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
      localDatabase: db,
    );

    expect(await storage.getSession(), isNotNull);
    await authRepo.logout();
    expect(await storage.getSession(), isNull);

    // Database remains open and accessible
    final pendingCount = await db.getPendingSyncCount();
    expect(pendingCount, 0);

    await db.close();
  });

  test('RSK-01 Case 2: logout with pending activity records preserves sync_queue', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'valid-token',
      userId: 'user-1',
      userName: 'Petani Pending',
      userEmail: 'petani.pending@test.local',
      roles: ['farmer'],
    ));

    final mockHttp = MockClient((request) async {
      return http.Response(jsonEncode({'success': true, 'message': 'OK'}), 200);
    });

    final syncRepo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
    );
    final authRepo = AuthRepository(
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
      localDatabase: db,
    );

    final queuedItem = await syncRepo.enqueueActivity(
      entityType: 'tree_measurement',
      payload: {'tree_id': 'tree-101', 'diameter_cm': 32.5},
    );
    expect(queuedItem.status, LocalSyncStatus.pending);
    expect(await syncRepo.getPendingActivityCount(), 1);

    // Perform logout
    await authRepo.logout();
    expect(await storage.getSession(), isNull);

    // CRITICAL (RSK-01): sync_queue must NOT be deleted!
    expect(await syncRepo.getPendingActivityCount(), 1);
    final queue = await syncRepo.getSyncQueue();
    expect(queue.length, 1);
    expect(queue.first.clientTransactionId, queuedItem.clientTransactionId);
    expect(queue.first.status, LocalSyncStatus.pending);
    expect(queue.first.payload['diameter_cm'], 32.5);

    await db.close();
  });

  test('RSK-01 Case 3: logout with pending photos preserves photo_queue metadata', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'valid-token',
      userId: 'user-1',
      userName: 'Petani Foto',
      userEmail: 'petani.foto@test.local',
      roles: ['farmer'],
    ));

    final mockHttp = MockClient((request) async {
      return http.Response(jsonEncode({'success': true, 'message': 'OK'}), 200);
    });

    final syncRepo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
    );
    final authRepo = AuthRepository(
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
      localDatabase: db,
    );

    await syncRepo.enqueueActivity(
      entityType: 'fertilization',
      payload: {'tree_id': 'tree-1', 'method': 'kocor'},
      photoFilePaths: ['/data/user/0/durian/cache/sample_durian.jpg'],
      photoCaption: 'Pohon selesai dipupuk',
    );

    expect(await syncRepo.getPendingActivityCount(), 1);
    expect(await syncRepo.getPendingPhotoCount(), 1);

    // Perform logout
    await authRepo.logout();

    // Session is cleared
    expect(await storage.getSession(), isNull);

    // CRITICAL (RSK-01): photo_queue must remain intact
    expect(await syncRepo.getPendingPhotoCount(), 1);
    final photos = await syncRepo.getPhotoQueue();
    expect(photos.length, 1);
    expect(photos.first.activityType, 'fertilization');
    expect(photos.first.localFilePath, '/data/user/0/durian/cache/sample_durian.jpg');
    expect(photos.first.caption, 'Pohon selesai dipupuk');
    expect(photos.first.status, LocalSyncStatus.pending);

    await db.close();
  });

  test('RSK-01 Case 4: logout while offline completes safely and preserves pending data', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'offline-token',
      userId: 'user-offline',
      userName: 'Petani Offline',
      userEmail: 'petani.offline@test.local',
      roles: ['farmer'],
    ));

    // Mock HTTP that throws ClientException (simulating offline / network timeout)
    final mockHttp = MockClient((request) async {
      throw http.ClientException('Network unreachable');
    });

    final syncRepo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
    );
    final authRepo = AuthRepository(
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
      localDatabase: db,
    );

    await syncRepo.enqueueActivity(
      entityType: 'flowering',
      payload: {'tree_id': 'tree-2', 'flowering_status': 'mulai'},
    );

    // Logout should catch the network error gracefully and not throw
    await authRepo.logout();

    // Session is cleared
    expect(await storage.getSession(), isNull);

    // Offline pending activity is safely preserved
    expect(await syncRepo.getPendingActivityCount(), 1);

    await db.close();
  });

  test('RSK-01 Case 6: login again after logout can resume synchronizing previously recorded data', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'session-1',
      userId: 'user-1',
      userName: 'Petani Sesi 1',
      userEmail: 'petani1@test.local',
      roles: ['farmer'],
    ));

    bool pushCalled = false;
    final mockHttp = MockClient((request) async {
      if (request.url.path.contains('/api/v1/auth/logout')) {
        return http.Response(jsonEncode({'success': true}), 200);
      }
      if (request.url.path.contains('/api/v1/sync/push')) {
        pushCalled = true;
        final body = jsonDecode(request.body) as Map<String, Object?>;
        final ops = (body['operations'] as List<Object?>).cast<Map<String, Object?>>();
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'results': [
                {
                  'client_transaction_id': ops[0]['client_transaction_id'],
                  'entity_type': ops[0]['entity_type'],
                  'entity_id': ops[0]['entity_id'],
                  'status': 'synced',
                  'duplicate': false,
                },
              ],
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }
      return http.Response(jsonEncode({'success': true}), 200);
    });

    final apiClient = ApiClient(httpClient: mockHttp);
    final syncRepo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: apiClient,
      sessionStorage: storage,
    );
    final authRepo = AuthRepository(
      apiClient: apiClient,
      sessionStorage: storage,
      localDatabase: db,
    );

    // Record activity during session 1
    final item = await syncRepo.enqueueActivity(
      entityType: 'fruit_development',
      payload: {'tree_id': 'tree-5', 'development_stage': 'pentil'},
    );

    // User logs out with pending activity
    await authRepo.logout();
    expect(await storage.getSession(), isNull);
    expect(await syncRepo.getPendingActivityCount(), 1);

    // User logs in again (Session 2)
    await storage.saveSession(const AuthSession(
      token: 'session-2-new-token',
      userId: 'user-1',
      userName: 'Petani Sesi 2',
      userEmail: 'petani1@test.local',
      roles: ['farmer'],
    ));

    // Resume synchronization
    final syncResult = await syncRepo.syncPending();
    expect(pushCalled, isTrue);
    expect(syncResult.syncedCount, 1);
    expect(await syncRepo.getPendingActivityCount(), 0);

    final queue = await syncRepo.getSyncQueue();
    expect(queue.first.status, LocalSyncStatus.synced);
    expect(queue.first.clientTransactionId, item.clientTransactionId);

    await db.close();
  });

  test('RSK-01 Case 7: failed synchronization preserves records in queue and logout does not delete them', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'valid-token',
      userId: 'user-1',
      userName: 'Petani Fail',
      userEmail: 'petani.fail@test.local',
      roles: ['farmer'],
    ));

    // Mock HTTP returns server error 500 on push
    final mockHttp = MockClient((request) async {
      if (request.url.path.contains('/api/v1/sync/push')) {
        return http.Response('Server Error', 500);
      }
      return http.Response(jsonEncode({'success': true}), 200);
    });

    final apiClient = ApiClient(httpClient: mockHttp);
    final syncRepo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: apiClient,
      sessionStorage: storage,
    );
    final authRepo = AuthRepository(
      apiClient: apiClient,
      sessionStorage: storage,
      localDatabase: db,
    );

    await syncRepo.enqueueActivity(
      entityType: 'harvest_record',
      payload: {'session_id': 'sess-1', 'tree_id': 'tree-1', 'fruit_count': 10},
    );

    // Sync fails
    final syncResult = await syncRepo.syncPending();
    expect(syncResult.failedCount, 1);
    expect(await syncRepo.getPendingActivityCount(), 1);

    final queueBeforeLogout = await syncRepo.getSyncQueue();
    expect(queueBeforeLogout.first.status, LocalSyncStatus.failed);

    // Logout occurs while items are in 'failed' status
    await authRepo.logout();

    // Verify failed items remain preserved for next retry
    expect(await syncRepo.getPendingActivityCount(), 1);
    final queueAfterLogout = await syncRepo.getSyncQueue();
    expect(queueAfterLogout.first.status, LocalSyncStatus.failed);

    await db.close();
  });

  test('RSK-06: pullDeltaFromServer uses since timestamp and updates SQLite without wiping existing records', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    await storage.saveBaseUrl('http://localhost:8000');
    await storage.saveSession(const AuthSession(
      token: 'valid-token',
      userId: 'user-1',
      userName: 'Petani Delta',
      userEmail: 'petani.delta@test.local',
      roles: ['farmer'],
    ));

    // Seed existing garden and trees into SQLite
    final sqliteDb = await db.database;
    await sqliteDb.insert('villages', {
      'id': 'v-1',
      'name': 'Desa A',
    });
    await sqliteDb.insert('blocks', {
      'id': 'b-1',
      'village_id': 'v-1',
      'name': 'Blok A',
    });
    await sqliteDb.insert('gardens', {
      'id': 'g-1',
      'block_id': 'b-1',
      'name': 'Kebun Lama',
    });
    await sqliteDb.insert('trees', {
      'id': 't-1',
      'garden_id': 'g-1',
      'tree_code': 'TR-001',
      'tree_name': 'Pohon Tetap',
      'status': 'active',
    });
    await sqliteDb.insert('trees', {
      'id': 't-2',
      'garden_id': 'g-1',
      'tree_code': 'TR-002',
      'tree_name': 'Pohon Akan Diupdate',
      'status': 'active',
    });

    const previousSyncTime = '2026-11-20T10:00:00.000Z';
    const newServerTime = '2026-11-24T12:00:00.000Z';
    await storage.saveLastSyncAt(previousSyncTime);

    String? sentSince;
    final mockHttp = MockClient((request) async {
      if (request.url.path.contains('/api/v1/sync/pull')) {
        final body = jsonDecode(request.body) as Map<String, Object?>;
        sentSince = body['since'] as String?;

        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'server_time': newServerTime,
              'changes': {
                'trees': [
                  {
                    'id': 't-2',
                    'garden_id': 'g-1',
                    'tree_code': 'TR-002',
                    'tree_name': 'Pohon Telah Diupdate',
                    'status': 'active',
                  },
                  {
                    'id': 't-3',
                    'garden_id': 'g-1',
                    'tree_code': 'TR-003',
                    'tree_name': 'Pohon Baru Delta',
                    'status': 'active',
                  },
                ],
              },
            },
          }),
          200,
        );
      }
      return http.Response('Not Found', 404);
    });

    final repo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: ApiClient(httpClient: mockHttp),
      sessionStorage: storage,
    );

    final appliedCount = await repo.pullDeltaFromServer();

    expect(sentSince, previousSyncTime);
    expect(appliedCount, 2);

    // Verify trees in local database
    final trees = await repo.getTrees();
    expect(trees.length, 3); // t-1 (untouched), t-2 (updated), t-3 (newly inserted)

    final t1 = trees.firstWhere((t) => t.id == 't-1');
    expect(t1.treeName, 'Pohon Tetap');

    final t2 = trees.firstWhere((t) => t.id == 't-2');
    expect(t2.treeName, 'Pohon Telah Diupdate');

    final t3 = trees.firstWhere((t) => t.id == 't-3');
    expect(t3.treeName, 'Pohon Baru Delta');

    // Verify last sync time was updated
    expect(await storage.getLastSyncAt(), newServerTime);

    await db.close();
  });

  test('RSK-08: SessionStorage stores credentials in platform secure vault and migrates legacy plaintext keys', () async {
    final vault = InMemorySecureVault();
    final storage = SessionStorage(vault: vault);

    const testSession = AuthSession(
      token: 'secret-token-12345',
      userId: 'user-sec-1',
      userName: 'Petani Aman',
      userEmail: 'aman@durian.local',
      roles: ['farmer'],
    );

    // Save session
    await storage.saveSession(testSession);

    // Verify stored session can be retrieved and matches original
    final retrieved = await storage.getSession();
    expect(retrieved, isNotNull);
    expect(retrieved!.token, 'secret-token-12345');
    expect(retrieved.userId, 'user-sec-1');
    expect(retrieved.userEmail, 'aman@durian.local');

    // Verify raw SharedPreferences storage does NOT contain any session token
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.containsKey('durian_auth_session'), isFalse);
    expect(prefs.containsKey('durian_auth_session_secure'), isFalse);

    // Verify migration: simulate existing user with legacy plaintext key
    await storage.clearSession();
    expect(await storage.getSession(), isNull);

    // Write raw legacy unencrypted session
    await prefs.setString(
      'durian_auth_session',
      jsonEncode(const AuthSession(
        token: 'legacy-unencrypted-token',
        userId: 'user-legacy-1',
        userName: 'Petani Legacy',
        userEmail: 'legacy@durian.local',
        roles: ['farmer'],
      ).toJson()),
    );

    // getSession() must auto-migrate into secure vault
    final migratedSession = await storage.getSession();
    expect(migratedSession, isNotNull);
    expect(migratedSession!.token, 'legacy-unencrypted-token');

    // Verify legacy unencrypted key was removed and migrated to secure vault
    expect(prefs.containsKey('durian_auth_session'), isFalse);
    final storedInVault = await vault.read('durian_auth_session_secure');
    expect(storedInVault, isNotNull);
    expect(storedInVault!.contains('legacy-unencrypted-token'), isTrue);
  });

  test('QR token semantics: preserves token when omitted, updates when active, clears when explicitly inactive or null', () async {
    final db = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
    final storage = SessionStorage();
    final repo = OfflineSyncRepository(
      localDatabase: db,
      apiClient: ApiClient(),
      sessionStorage: storage,
    );

    // 1. Initial bootstrap with active QR token
    await repo.persistBootstrapSnapshot({
      'villages': [],
      'blocks': [],
      'gardens': [
        {'id': 'g-test-1', 'block_id': 'b-1', 'name': 'Kebun Test'},
      ],
      'durian_varieties': [
        {'id': 'v-test-1', 'code': 'BWR', 'name': 'Bawor'},
      ],
      'product_categories': [],
      'agricultural_products': [],
      'trees': [
        {
          'id': 'tree-qr-test',
          'garden_id': 'g-test-1',
          'variety_id': 'v-test-1',
          'tree_code': 'BWR-001',
          'tree_name': 'Pohon Bawor Awal',
          'status': 'active',
          'qr_token': 'active-initial-token-111',
        },
      ],
      'harvest_seasons': [],
      'harvest_sessions': [],
    });

    // Verify initial active token
    var tree = await repo.findTreeByQrOrCode('active-initial-token-111');
    expect(tree, isNotNull);
    expect(tree!.treeName, 'Pohon Bawor Awal');

    // 2. State 1: Partial delta WITHOUT any QR fields (neither qr_token nor qr_code key)
    // -> Must PRESERVE existing local qr_token
    await repo.persistDeltaChanges({
      'trees': [
        {
          'id': 'tree-qr-test',
          'garden_id': 'g-test-1',
          'variety_id': 'v-test-1',
          'tree_code': 'BWR-001',
          'tree_name': 'Pohon Bawor Nama Baru',
          'status': 'active',
          'notes': 'Update parsial tanpa field QR',
          'updated_at': '2026-11-24T18:00:00Z',
        },
      ],
    });

    tree = await repo.findTreeByQrOrCode('active-initial-token-111');
    expect(tree, isNotNull, reason: 'Old token must be preserved when QR fields are omitted');
    expect(tree!.treeName, 'Pohon Bawor Nama Baru');

    // 3. State 2: Delta with active new QR code in qr_code object
    // -> Must UPDATE to the new token
    await repo.persistDeltaChanges({
      'trees': [
        {
          'id': 'tree-qr-test',
          'garden_id': 'g-test-1',
          'variety_id': 'v-test-1',
          'tree_code': 'BWR-001',
          'tree_name': 'Pohon Bawor Re-QR Active',
          'status': 'active',
          'qr_code': {
            'public_token': 'new-active-token-222',
            'is_active': true,
          },
        },
      ],
    });

    // Old token must no longer match
    expect(await repo.findTreeByQrOrCode('active-initial-token-111'), isNull);
    // New active token must match
    tree = await repo.findTreeByQrOrCode('new-active-token-222');
    expect(tree, isNotNull);
    expect(tree!.treeName, 'Pohon Bawor Re-QR Active');

    // 4. State 3: Delta with EXPLICITLY INACTIVE QR (is_active: false)
    // -> Must CLEAR local qr_token to null and NOT fallback to old token
    await repo.persistDeltaChanges({
      'trees': [
        {
          'id': 'tree-qr-test',
          'garden_id': 'g-test-1',
          'variety_id': 'v-test-1',
          'tree_code': 'BWR-001',
          'tree_name': 'Pohon Bawor QR Deactivated',
          'status': 'active',
          'qr_code': {
            'public_token': 'new-active-token-222',
            'is_active': false,
          },
        },
      ],
    });

    // Neither new nor old token should match now
    expect(await repo.findTreeByQrOrCode('new-active-token-222'), isNull);
    expect(await repo.findTreeByQrOrCode('active-initial-token-111'), isNull);

    // Can still be found by tree code
    tree = await repo.findTreeByQrOrCode('BWR-001');
    expect(tree, isNotNull);
    expect(tree!.treeName, 'Pohon Bawor QR Deactivated');

    // 5. Re-activate with direct qr_token string (State 2)
    await repo.persistDeltaChanges({
      'trees': [
        {
          'id': 'tree-qr-test',
          'garden_id': 'g-test-1',
          'variety_id': 'v-test-1',
          'tree_code': 'BWR-001',
          'tree_name': 'Pohon Bawor Reactivated String',
          'status': 'active',
          'qr_token': 'string-active-token-333',
        },
      ],
    });

    tree = await repo.findTreeByQrOrCode('string-active-token-333');
    expect(tree, isNotNull);

    // 6. Explicit null qr_token (State 3 via API contract)
    // -> Must CLEAR local qr_token to null and NOT fallback
    await repo.persistDeltaChanges({
      'trees': [
        {
          'id': 'tree-qr-test',
          'garden_id': 'g-test-1',
          'variety_id': 'v-test-1',
          'tree_code': 'BWR-001',
          'tree_name': 'Pohon Bawor Explicit Null',
          'status': 'active',
          'qr_token': null,
        },
      ],
    });

    expect(await repo.findTreeByQrOrCode('string-active-token-333'), isNull);

    // 7. Bootstrap with inactive QR sets null token without errors
    await repo.persistBootstrapSnapshot({
      'villages': [],
      'blocks': [],
      'gardens': [],
      'durian_varieties': [],
      'product_categories': [],
      'agricultural_products': [],
      'trees': [
        {
          'id': 'tree-bootstrap-inactive',
          'garden_id': 'g-test-1',
          'variety_id': 'v-test-1',
          'tree_code': 'BWR-BOOT',
          'tree_name': 'Pohon Inactive Bootstrap',
          'status': 'active',
          'qr_code': {
            'public_token': 'inactive-bootstrap-token',
            'is_active': false,
          },
        },
      ],
      'harvest_seasons': [],
      'harvest_sessions': [],
    });

    expect(await repo.findTreeByQrOrCode('inactive-bootstrap-token'), isNull);

    await db.close();
  });
}
