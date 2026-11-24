import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
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
import 'package:path/path.dart' as p;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

class _FailingSecureVault implements SecureVaultBackend {
  @override
  Future<void> delete(String key) async {
    throw Exception('Keystore locked');
  }

  @override
  Future<String?> read(String key) async {
    throw Exception('Keystore locked');
  }

  @override
  Future<void> write(String key, String value) async {
    throw Exception('Keystore unavailable');
  }
}

String _encodeLegacyV1(String key, String plain, String deviceSalt) {
  final combined = utf8.encode('durian_secure_vault_salt_v1:$key:$deviceSalt');
  final keyBytes = Uint8List(32);
  for (var i = 0; i < 32; i++) {
    var acc = (i * 31 + 17) & 0xFF;
    for (var j = 0; j < combined.length; j++) {
      acc = (acc ^ (combined[j] * (j + 1) + i)) & 0xFF;
    }
    keyBytes[i] = acc;
  }
  final plainBytes = utf8.encode(plain);
  final cipherBytes = Uint8List(plainBytes.length);
  for (var i = 0; i < plainBytes.length; i++) {
    cipherBytes[i] = plainBytes[i] ^ keyBytes[i % keyBytes.length];
  }
  return 'enc:v1:${base64.encode(cipherBytes)}';
}

void main() {
  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
  });

  group('VULN-01: SQLite v1->v2 migration & multi-user queue isolation', () {
    test('non-destructive v1->v2 migration preserves pending/failed/syncing rows and quarantines unowned records', () async {
      final tempDir = await Directory.systemTemp.createTemp('durian_v1_mig_');
      final dbPath = p.join(tempDir.path, 'legacy_v1.db');

      // 1. Create a genuine v1 database with pre-existing pending, failed, syncing rows
      final legacyDb = await openDatabase(
        dbPath,
        version: 1,
        onCreate: (db, version) async {
          await db.execute('''
            CREATE TABLE sync_queue (
              client_transaction_id TEXT PRIMARY KEY,
              entity_type TEXT NOT NULL,
              entity_id TEXT NOT NULL,
              payload_json TEXT NOT NULL,
              sync_status TEXT NOT NULL DEFAULT 'pending',
              error_message TEXT,
              created_at TEXT NOT NULL,
              synced_at TEXT
            )
          ''');
          await db.execute('''
            CREATE TABLE photo_queue (
              id TEXT PRIMARY KEY,
              activity_type TEXT NOT NULL,
              activity_id TEXT NOT NULL,
              local_file_path TEXT NOT NULL,
              caption TEXT,
              sync_status TEXT NOT NULL DEFAULT 'pending',
              error_message TEXT,
              created_at TEXT NOT NULL
            )
          ''');
        },
      );

      await legacyDb.insert('sync_queue', {
        'client_transaction_id': 'tx-pending-1',
        'entity_type': 'tree_measurement',
        'entity_id': 'ent-1',
        'payload_json': jsonEncode({'tree_id': 'tree-1', 'height_m': 4.5}),
        'sync_status': 'pending',
        'created_at': '2026-11-20T08:00:00Z',
      });
      await legacyDb.insert('sync_queue', {
        'client_transaction_id': 'tx-failed-2',
        'entity_type': 'fertilization',
        'entity_id': 'ent-2',
        'payload_json': jsonEncode({'tree_id': 'tree-2', 'method': 'kocor'}),
        'sync_status': 'failed',
        'error_message': 'Timeout',
        'created_at': '2026-11-20T09:00:00Z',
      });
      await legacyDb.insert('sync_queue', {
        'client_transaction_id': 'tx-syncing-3',
        'entity_type': 'flowering',
        'entity_id': 'ent-3',
        'payload_json': jsonEncode({'tree_id': 'tree-3', 'flowering_status': 'mulai'}),
        'sync_status': 'syncing',
        'created_at': '2026-11-20T10:00:00Z',
      });
      await legacyDb.insert('photo_queue', {
        'id': 'photo-legacy-1',
        'activity_type': 'tree_measurement',
        'activity_id': 'ent-1',
        'local_file_path': '/tmp/legacy_durian.jpg',
        'caption': 'Foto lama',
        'sync_status': 'pending',
        'created_at': '2026-11-20T08:01:00Z',
      });
      await legacyDb.close();

      // 2. Open via LocalDatabase (triggers v1 -> v2 upgrade)
      final localDb = LocalDatabase(databasePathOverride: dbPath);
      final storage = SessionStorage(vault: InMemorySecureVault());
      await storage.saveBaseUrl('http://localhost:8000');
      await storage.saveSession(const AuthSession(
        token: 'token-user-b',
        userId: 'user-b',
        userName: 'Petani B',
        userEmail: 'b@durian.test',
        roles: ['petani'],
      ));

      var pushCalled = false;
      final mockHttp = MockClient((request) async {
        pushCalled = true;
        return http.Response(jsonEncode({'success': true, 'data': {'results': []}}), 200);
      });

      final repo = OfflineSyncRepository(
        localDatabase: localDb,
        apiClient: ApiClient(httpClient: mockHttp),
        sessionStorage: storage,
      );

      // 3. Verify all 3 sync rows and 1 photo row survived intact and are quarantined (owner_user_id IS NULL)
      final quarantined = await repo.getQuarantinedSyncQueue();
      expect(quarantined.length, 3);
      expect(quarantined.every((i) => i.isQuarantined), isTrue);
      expect(
        quarantined.firstWhere((i) => i.clientTransactionId == 'tx-pending-1').payload['height_m'],
        4.5,
      );

      final quarantinedPhotos = await repo.getQuarantinedPhotoQueue();
      expect(quarantinedPhotos.length, 1);
      expect(quarantinedPhotos.first.localFilePath, '/tmp/legacy_durian.jpg');

      // 4. User B running syncPending MUST NOT auto-assign or submit quarantined legacy records!
      final result = await repo.syncPending();
      expect(pushCalled, isFalse);
      expect(result.syncedCount, 0);
      expect(await repo.getQuarantinedActivityCount(), 3);

      // 5. Explicit user-authorized claim assigns ONLY the chosen legacy record & its photo
      final claimed = await repo.claimQuarantinedRecordForCurrentUser('tx-pending-1');
      expect(claimed, 1);
      expect(await repo.getQuarantinedActivityCount(), 2);
      expect(await repo.getPendingActivityCount(), 1);
      expect(await repo.getPendingPhotoCount(), 1);

      await localDb.close();
      await tempDir.delete(recursive: true);
    });

    test('multi-user account switching isolates pending queues and submits only the authenticated owner data', () async {
      final localDb = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
      final storage = SessionStorage(vault: InMemorySecureVault());
      await storage.saveBaseUrl('http://localhost:8000');

      final pushedByToken = <String, List<String>>{};
      final mockHttp = MockClient((request) async {
        if (request.url.path.contains('/api/v1/auth/logout')) {
          return http.Response(jsonEncode({'success': true}), 200);
        }
        if (request.url.path.contains('/api/v1/sync/push')) {
          final auth = request.headers['authorization'] ?? '';
          final body = jsonDecode(request.body) as Map<String, Object?>;
          final ops = (body['operations'] as List<Object?>).cast<Map<String, Object?>>();
          pushedByToken.putIfAbsent(auth, () => []).addAll(
                ops.map((o) => o['client_transaction_id'] as String),
              );
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'results': ops
                    .map((o) => {
                          'client_transaction_id': o['client_transaction_id'],
                          'entity_type': o['entity_type'],
                          'entity_id': o['entity_id'],
                          'status': 'synced',
                          'duplicate': false,
                        })
                    .toList(),
              },
            }),
            200,
          );
        }
        return http.Response(jsonEncode({'success': true}), 200);
      });

      final apiClient = ApiClient(httpClient: mockHttp);
      final syncRepo = OfflineSyncRepository(
        localDatabase: localDb,
        apiClient: apiClient,
        sessionStorage: storage,
      );
      final authRepo = AuthRepository(
        apiClient: apiClient,
        sessionStorage: storage,
        localDatabase: localDb,
      );

      // User A logs in and queues an offline activity
      await storage.saveSession(const AuthSession(
        token: 'token-A',
        userId: 'user-A',
        userName: 'Petani A',
        userEmail: 'a@durian.test',
        roles: ['petani'],
      ));
      final itemA = await syncRepo.enqueueActivity(
        entityType: 'tree_measurement',
        payload: {'tree_id': 'tree-A', 'height_m': 5.0},
      );
      expect(itemA.ownerUserId, 'user-A');

      // User A logs out -> User A's item is preserved
      await authRepo.logout();

      // User B logs in on the SAME device and queues an activity
      await storage.saveSession(const AuthSession(
        token: 'token-B',
        userId: 'user-B',
        userName: 'Petani B',
        userEmail: 'b@durian.test',
        roles: ['petani'],
      ));
      final itemB = await syncRepo.enqueueActivity(
        entityType: 'tree_measurement',
        payload: {'tree_id': 'tree-B', 'height_m': 6.0},
      );

      // User B only sees their own queue item
      final queueB = await syncRepo.getSyncQueue();
      expect(queueB.map((e) => e.clientTransactionId), [itemB.clientTransactionId]);

      // User B syncs -> ONLY itemB is pushed using token-B; itemA is untouched!
      final resultB = await syncRepo.syncPending();
      expect(resultB.syncedCount, 1);
      expect(pushedByToken['Bearer token-B'], [itemB.clientTransactionId]);

      // User B logs out, User A logs back in -> User A's itemA is still pending and syncs with token-A
      await authRepo.logout();
      await storage.saveSession(const AuthSession(
        token: 'token-A-new',
        userId: 'user-A',
        userName: 'Petani A',
        userEmail: 'a@durian.test',
        roles: ['petani'],
      ));
      expect(await syncRepo.getPendingActivityCount(), 1);
      final resultA = await syncRepo.syncPending();
      expect(resultA.syncedCount, 1);
      expect(pushedByToken['Bearer token-A-new'], [itemA.clientTransactionId]);

      await localDb.close();
    });
  });

  group('VULN-03: Mobile delta sync pagination, tombstones, and failure recovery', () {
    test('paginates multiple pages, applies tombstones, and does not advance cursor on partial failure', () async {
      final localDb = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
      final storage = SessionStorage(vault: InMemorySecureVault());
      await storage.saveBaseUrl('http://localhost:8000');
      await storage.saveSession(const AuthSession(
        token: 'token-delta',
        userId: 'user-delta',
        userName: 'Petani Delta',
        userEmail: 'delta@durian.test',
        roles: ['petani'],
      ));

      // Pre-seed local tree that will be soft-deleted on server
      await localDb.database.then((db) async {
        await db.insert('trees', {
          'id': 'tree-deleted',
          'garden_id': 'g-1',
          'tree_code': 'DEL-01',
          'status': 'active',
        });
      });

      const initialSince = '2026-11-20T00:00:00Z';
      const snapshotTime = '2026-11-24T15:00:00Z';
      await storage.saveLastSyncAt(initialSince);

      // 1. Simulate failure on page 2 -> lastSyncAt MUST NOT advance
      var failPage2 = true;
      final mockHttp = MockClient((request) async {
        final body = jsonDecode(request.body) as Map<String, Object?>;
        final cursor = body['cursor'] as String?;

        if (cursor == null) {
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'server_time': snapshotTime,
                'snapshot_time': snapshotTime,
                'has_more': true,
                'next_cursor': 'cursor-page-2',
                'changes': {
                  'trees': [
                    {'id': 'tree-p1', 'garden_id': 'g-1', 'tree_code': 'P1-01', 'status': 'active'},
                  ],
                },
                'deleted_ids': {
                  'trees': ['tree-deleted'],
                },
              },
            }),
            200,
          );
        }

        if (failPage2) {
          return http.Response('Gateway Timeout', 504);
        }

        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'server_time': snapshotTime,
              'snapshot_time': snapshotTime,
              'has_more': false,
              'next_cursor': null,
              'changes': {
                'trees': [
                  {'id': 'tree-p2', 'garden_id': 'g-1', 'tree_code': 'P2-01', 'status': 'active'},
                ],
              },
              'deleted_ids': {},
            },
          }),
          200,
        );
      });

      final repo = OfflineSyncRepository(
        localDatabase: localDb,
        apiClient: ApiClient(httpClient: mockHttp),
        sessionStorage: storage,
      );

      await expectLater(repo.pullDeltaFromServer(), throwsA(isA<ApiException>()));
      // Cursor MUST still be initialSince after page 2 failure!
      expect(await storage.getLastSyncAt(), initialSince);

      // 2. Retry after network recovers -> both pages succeed, no duplicates, tombstone applied, cursor advances
      failPage2 = false;
      await repo.pullDeltaFromServer();

      expect(await storage.getLastSyncAt(), snapshotTime);
      final trees = await repo.getTrees();
      final treeIds = trees.map((t) => t.id).toSet();
      expect(treeIds, {'tree-p1', 'tree-p2'}); // tree-deleted removed, no duplicates!

      await localDb.close();
    });
  });

  group('VULN-05: Stale sync recovery & active lease protection', () {
    test('recovers expired syncing leases in sync_queue & photo_queue without resetting active leases', () async {
      final localDb = LocalDatabase(databasePathOverride: inMemoryDatabasePath);
      final storage = SessionStorage(vault: InMemorySecureVault());
      final repo = OfflineSyncRepository(
        localDatabase: localDb,
        apiClient: ApiClient(),
        sessionStorage: storage,
      );

      final db = await localDb.database;
      final nowUtc = DateTime.utc(2026, 11, 24, 16, 0, 0);
      final expiredTime = nowUtc.subtract(const Duration(minutes: 10)).toIso8601String();
      final freshTime = nowUtc.subtract(const Duration(seconds: 20)).toIso8601String();

      await db.insert('sync_queue', {
        'client_transaction_id': 'tx-stale',
        'owner_user_id': 'user-1',
        'entity_type': 'tree_measurement',
        'entity_id': 'e-stale',
        'payload_json': '{}',
        'sync_status': 'syncing',
        'syncing_started_at': expiredTime,
        'sync_lease_id': 'dead-lease',
        'created_at': expiredTime,
      });
      await db.insert('sync_queue', {
        'client_transaction_id': 'tx-active',
        'owner_user_id': 'user-1',
        'entity_type': 'tree_measurement',
        'entity_id': 'e-active',
        'payload_json': '{}',
        'sync_status': 'syncing',
        'syncing_started_at': freshTime,
        'sync_lease_id': 'other-active-lease',
        'created_at': freshTime,
      });
      await db.insert('photo_queue', {
        'id': 'photo-stale',
        'owner_user_id': 'user-1',
        'activity_type': 'tree_measurement',
        'activity_id': 'e-stale',
        'local_file_path': '/tmp/p.jpg',
        'sync_status': 'syncing',
        'syncing_started_at': expiredTime,
        'sync_lease_id': 'dead-photo-lease',
        'created_at': expiredTime,
      });

      final recovered = await repo.recoverStaleSyncingItems(
        ownerUserId: 'user-1',
        staleAfter: const Duration(minutes: 5),
        now: nowUtc,
      );

      expect(recovered, 2); // tx-stale + photo-stale recovered; tx-active untouched!

      final syncRows = await db.query('sync_queue');
      final byId = {for (final r in syncRows) r['client_transaction_id']: r['sync_status']};
      expect(byId['tx-stale'], 'pending');
      expect(byId['tx-active'], 'syncing');

      final photoRows = await db.query('photo_queue');
      expect(photoRows.first['sync_status'], 'pending');

      await localDb.close();
    });
  });

  group('VULN-07: Platform secure vault migration & no plaintext fallback', () {
    test('migrates legacy enc:v1: obfuscated credentials to platform vault and clears legacy keys', () async {
      final prefs = await SharedPreferences.getInstance();
      const salt = 'test-device-salt-xyz';
      const legacySession = AuthSession(
        token: 'legacy-obf-token-999',
        userId: 'user-obf',
        userName: 'Petani Obf',
        userEmail: 'obf@durian.test',
        roles: ['petani'],
      );
      final rawJson = jsonEncode(legacySession.toJson());
      await prefs.setString('durian_vault_device_salt', salt);
      await prefs.setString(
        'durian_auth_session_secure',
        _encodeLegacyV1('durian_auth_session_secure', rawJson, salt),
      );

      final platformVault = InMemorySecureVault();
      final storage = SessionStorage(vault: platformVault);

      final migrated = await storage.getSession();
      expect(migrated, isNotNull);
      expect(migrated!.token, 'legacy-obf-token-999');

      // Legacy obfuscated key and device salt MUST be erased from SharedPreferences
      expect(prefs.containsKey('durian_auth_session_secure'), isFalse);
      expect(prefs.containsKey('durian_vault_device_salt'), isFalse);
      // Platform vault holds the migrated session
      expect(await platformVault.read('durian_auth_session_secure'), rawJson);
    });

    test('never silently falls back to plaintext when secure storage is unavailable', () async {
      final storage = SessionStorage(
        vault: _FailingSecureVault(),
        allowEphemeralMemoryFallback: false,
      );

      const session = AuthSession(
        token: 'super-secret-token',
        userId: 'user-1',
        userName: 'Petani',
        userEmail: 'p@durian.test',
        roles: ['petani'],
      );

      await expectLater(
        storage.saveSession(session),
        throwsA(isA<SecureStorageUnavailableException>()),
      );

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.containsKey('durian_auth_session'), isFalse);
      expect(prefs.containsKey('durian_auth_session_secure'), isFalse);

      // Ephemeral memory fallback mode keeps session in RAM only, never on disk
      final ephemeralStorage = SessionStorage(
        vault: _FailingSecureVault(),
        allowEphemeralMemoryFallback: true,
      );
      await ephemeralStorage.saveSession(session);
      final restored = await ephemeralStorage.getSession();
      expect(restored?.token, 'super-secret-token');
      expect(prefs.containsKey('durian_auth_session'), isFalse);
      expect(prefs.containsKey('durian_auth_session_secure'), isFalse);
    });
  });
}
