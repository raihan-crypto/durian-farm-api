import 'dart:convert';
import 'package:durian_farm_mobile/data/local/local_database.dart';
import 'package:durian_farm_mobile/data/models/models.dart';
import 'package:durian_farm_mobile/data/repositories/offline_sync_repository.dart';
import 'package:durian_farm_mobile/data/services/api_client.dart';
import 'package:durian_farm_mobile/data/services/session_storage.dart';
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
}
