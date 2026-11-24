import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Local SQLite database supporting offline-first plantation recording (SRS 12.2).
class LocalDatabase {
  LocalDatabase({this.databasePathOverride});

  final String? databasePathOverride;
  Database? _database;

  static const int currentVersion = 2;

  Future<Database> get database async {
    if (_database != null && _database!.isOpen) {
      return _database!;
    }

    final dbPath = databasePathOverride ??
        p.join(await getDatabasesPath(), 'durian_farm_offline.db');

    _database = await openDatabase(
      dbPath,
      version: currentVersion,
      onCreate: _onCreate,
      onUpgrade: _onUpgrade,
    );

    return _database!;
  }

  Future<void> _onCreate(Database db, int version) async {
    await db.execute('''
      CREATE TABLE villages (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        district TEXT,
        regency TEXT,
        province TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE blocks (
        id TEXT PRIMARY KEY,
        village_id TEXT NOT NULL,
        name TEXT NOT NULL,
        area_hectares REAL,
        description TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE gardens (
        id TEXT PRIMARY KEY,
        block_id TEXT NOT NULL,
        name TEXT NOT NULL,
        area_hectares REAL,
        address TEXT,
        latitude REAL,
        longitude REAL,
        description TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE durian_varieties (
        id TEXT PRIMARY KEY,
        code TEXT,
        name TEXT NOT NULL,
        origin TEXT,
        description TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE product_categories (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        description TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE agricultural_products (
        id TEXT PRIMARY KEY,
        category_id TEXT NOT NULL,
        name TEXT NOT NULL,
        brand TEXT,
        unit TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE trees (
        id TEXT PRIMARY KEY,
        garden_id TEXT NOT NULL,
        variety_id TEXT,
        tree_code TEXT NOT NULL,
        tree_name TEXT,
        planted_at TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        qr_token TEXT,
        notes TEXT,
        updated_at TEXT
      )
    ''');
    await db.execute('CREATE INDEX idx_trees_qr_token ON trees (qr_token)');
    await db.execute('CREATE INDEX idx_trees_garden ON trees (garden_id)');

    await db.execute('''
      CREATE TABLE harvest_seasons (
        id TEXT PRIMARY KEY,
        garden_id TEXT NOT NULL,
        name TEXT NOT NULL,
        start_date TEXT,
        end_date TEXT,
        status TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE harvest_sessions (
        id TEXT PRIMARY KEY,
        season_id TEXT NOT NULL,
        garden_id TEXT NOT NULL,
        harvest_date TEXT NOT NULL,
        session_name TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE sync_queue (
        client_transaction_id TEXT PRIMARY KEY,
        owner_user_id TEXT,
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        sync_status TEXT NOT NULL DEFAULT 'pending',
        syncing_started_at TEXT,
        sync_lease_id TEXT,
        error_message TEXT,
        created_at TEXT NOT NULL,
        synced_at TEXT
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_sync_queue_status ON sync_queue (sync_status)',
    );
    await db.execute(
      'CREATE INDEX idx_sync_queue_owner_status ON sync_queue (owner_user_id, sync_status)',
    );

    await db.execute('''
      CREATE TABLE photo_queue (
        id TEXT PRIMARY KEY,
        owner_user_id TEXT,
        activity_type TEXT NOT NULL,
        activity_id TEXT NOT NULL,
        local_file_path TEXT NOT NULL,
        caption TEXT,
        sync_status TEXT NOT NULL DEFAULT 'pending',
        syncing_started_at TEXT,
        sync_lease_id TEXT,
        error_message TEXT,
        created_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_photo_queue_owner_status ON photo_queue (owner_user_id, sync_status)',
    );
  }

  /// Non-destructive versioned SQLite migration.
  /// Preserves all existing pending, failed, syncing records, payloads, and photo paths.
  /// Legacy unowned records keep owner_user_id = NULL (quarantined, never auto-assigned).
  Future<void> _onUpgrade(Database db, int oldVersion, int newVersion) async {
    if (oldVersion < 2) {
      await _addColumnIfMissing(db, 'sync_queue', 'owner_user_id', 'TEXT');
      await _addColumnIfMissing(db, 'sync_queue', 'syncing_started_at', 'TEXT');
      await _addColumnIfMissing(db, 'sync_queue', 'sync_lease_id', 'TEXT');
      await db.execute(
        'CREATE INDEX IF NOT EXISTS idx_sync_queue_owner_status ON sync_queue (owner_user_id, sync_status)',
      );

      await _addColumnIfMissing(db, 'photo_queue', 'owner_user_id', 'TEXT');
      await _addColumnIfMissing(db, 'photo_queue', 'syncing_started_at', 'TEXT');
      await _addColumnIfMissing(db, 'photo_queue', 'sync_lease_id', 'TEXT');
      await db.execute(
        'CREATE INDEX IF NOT EXISTS idx_photo_queue_owner_status ON photo_queue (owner_user_id, sync_status)',
      );
    }
  }

  Future<void> _addColumnIfMissing(
    Database db,
    String table,
    String column,
    String definition,
  ) async {
    final columns = await db.rawQuery('PRAGMA table_info($table)');
    final exists = columns.any((row) => row['name'] == column);
    if (!exists) {
      await db.execute('ALTER TABLE $table ADD COLUMN $column $definition');
    }
  }

  Future<int> getPendingSyncCount({String? ownerUserId}) async {
    final db = await database;
    if (ownerUserId != null && ownerUserId.isNotEmpty) {
      final res = await db.rawQuery(
        "SELECT COUNT(*) as count FROM sync_queue WHERE owner_user_id = ? AND sync_status IN ('pending', 'failed')",
        [ownerUserId],
      );
      return Sqflite.firstIntValue(res) ?? 0;
    }
    final res = await db.rawQuery(
      "SELECT COUNT(*) as count FROM sync_queue WHERE sync_status IN ('pending', 'failed')",
    );
    return Sqflite.firstIntValue(res) ?? 0;
  }

  Future<int> getPendingPhotoCount({String? ownerUserId}) async {
    final db = await database;
    if (ownerUserId != null && ownerUserId.isNotEmpty) {
      final res = await db.rawQuery(
        "SELECT COUNT(*) as count FROM photo_queue WHERE owner_user_id = ? AND sync_status IN ('pending', 'failed')",
        [ownerUserId],
      );
      return Sqflite.firstIntValue(res) ?? 0;
    }
    final res = await db.rawQuery(
      "SELECT COUNT(*) as count FROM photo_queue WHERE sync_status IN ('pending', 'failed')",
    );
    return Sqflite.firstIntValue(res) ?? 0;
  }

  Future<int> getQuarantinedSyncCount() async {
    final db = await database;
    final res = await db.rawQuery(
      "SELECT COUNT(*) as count FROM sync_queue WHERE owner_user_id IS NULL AND sync_status IN ('pending', 'failed', 'syncing')",
    );
    return Sqflite.firstIntValue(res) ?? 0;
  }

  Future<int> getQuarantinedPhotoCount() async {
    final db = await database;
    final res = await db.rawQuery(
      "SELECT COUNT(*) as count FROM photo_queue WHERE owner_user_id IS NULL AND sync_status IN ('pending', 'failed', 'syncing')",
    );
    return Sqflite.firstIntValue(res) ?? 0;
  }

  Future<void> clearAllData() async {
    final db = await database;
    const tables = [
      'villages',
      'blocks',
      'gardens',
      'durian_varieties',
      'product_categories',
      'agricultural_products',
      'trees',
      'harvest_seasons',
      'harvest_sessions',
      'sync_queue',
      'photo_queue',
    ];
    await db.transaction((txn) async {
      for (final table in tables) {
        await txn.delete(table);
      }
    });
  }

  Future<void> close() async {
    if (_database != null && _database!.isOpen) {
      await _database!.close();
      _database = null;
    }
  }
}
