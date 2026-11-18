import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Local SQLite database supporting offline-first plantation recording (SRS 12.2).
class LocalDatabase {
  LocalDatabase({this.databasePathOverride});

  final String? databasePathOverride;
  Database? _database;

  Future<Database> get database async {
    if (_database != null && _database!.isOpen) {
      return _database!;
    }

    final dbPath = databasePathOverride ??
        p.join(await getDatabasesPath(), 'durian_farm_offline.db');

    _database = await openDatabase(
      dbPath,
      version: 1,
      onCreate: _onCreate,
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
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        sync_status TEXT NOT NULL DEFAULT 'pending',
        error_message TEXT,
        created_at TEXT NOT NULL,
        synced_at TEXT
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_sync_queue_status ON sync_queue (sync_status)',
    );

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
