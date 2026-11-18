import 'dart:convert';

class AuthSession {
  const AuthSession({
    required this.token,
    required this.userId,
    required this.userName,
    required this.userEmail,
    required this.roles,
    this.farmerName,
  });

  final String token;
  final String userId;
  final String userName;
  final String userEmail;
  final List<String> roles;
  final String? farmerName;

  Map<String, Object?> toJson() => {
        'token': token,
        'user_id': userId,
        'user_name': userName,
        'user_email': userEmail,
        'roles': roles,
        'farmer_name': farmerName,
      };

  factory AuthSession.fromJson(Map<String, Object?> json) => AuthSession(
        token: json['token'] as String,
        userId: json['user_id'] as String,
        userName: json['user_name'] as String,
        userEmail: json['user_email'] as String,
        roles: (json['roles'] as List<Object?>? ?? []).cast<String>(),
        farmerName: json['farmer_name'] as String?,
      );
}

class LocalGarden {
  const LocalGarden({
    required this.id,
    required this.blockId,
    required this.name,
    this.blockName,
    this.villageName,
    this.areaHectares,
  });

  final String id;
  final String blockId;
  final String name;
  final String? blockName;
  final String? villageName;
  final double? areaHectares;

  factory LocalGarden.fromMap(Map<String, Object?> map) => LocalGarden(
        id: map['id'] as String,
        blockId: map['block_id'] as String,
        name: map['name'] as String,
        blockName: map['block_name'] as String?,
        villageName: map['village_name'] as String?,
        areaHectares: (map['area_hectares'] as num?)?.toDouble(),
      );
}

class LocalTree {
  const LocalTree({
    required this.id,
    required this.gardenId,
    required this.treeCode,
    required this.status,
    this.treeName,
    this.varietyId,
    this.varietyName,
    this.gardenName,
    this.plantedAt,
    this.qrToken,
    this.notes,
  });

  final String id;
  final String gardenId;
  final String treeCode;
  final String status;
  final String? treeName;
  final String? varietyId;
  final String? varietyName;
  final String? gardenName;
  final String? plantedAt;
  final String? qrToken;
  final String? notes;

  factory LocalTree.fromMap(Map<String, Object?> map) => LocalTree(
        id: map['id'] as String,
        gardenId: map['garden_id'] as String,
        treeCode: map['tree_code'] as String,
        status: (map['status'] as String?) ?? 'active',
        treeName: map['tree_name'] as String?,
        varietyId: map['variety_id'] as String?,
        varietyName: map['variety_name'] as String?,
        gardenName: map['garden_name'] as String?,
        plantedAt: map['planted_at'] as String?,
        qrToken: map['qr_token'] as String?,
        notes: map['notes'] as String?,
      );
}

class LocalProduct {
  const LocalProduct({
    required this.id,
    required this.categoryId,
    required this.name,
    this.brand,
    this.unit,
    this.categoryName,
  });

  final String id;
  final String categoryId;
  final String name;
  final String? brand;
  final String? unit;
  final String? categoryName;

  factory LocalProduct.fromMap(Map<String, Object?> map) => LocalProduct(
        id: map['id'] as String,
        categoryId: map['category_id'] as String,
        name: map['name'] as String,
        brand: map['brand'] as String?,
        unit: map['unit'] as String?,
        categoryName: map['category_name'] as String?,
      );
}

class LocalHarvestSession {
  const LocalHarvestSession({
    required this.id,
    required this.seasonId,
    required this.gardenId,
    required this.harvestDate,
    this.sessionName,
  });

  final String id;
  final String seasonId;
  final String gardenId;
  final String harvestDate;
  final String? sessionName;

  factory LocalHarvestSession.fromMap(Map<String, Object?> map) =>
      LocalHarvestSession(
        id: map['id'] as String,
        seasonId: map['season_id'] as String,
        gardenId: map['garden_id'] as String,
        harvestDate: map['harvest_date'] as String,
        sessionName: map['session_name'] as String?,
      );
}

enum LocalSyncStatus { pending, syncing, synced, failed, conflict }

class SyncQueueItem {
  const SyncQueueItem({
    required this.clientTransactionId,
    required this.entityType,
    required this.entityId,
    required this.payload,
    required this.status,
    required this.createdAt,
    this.errorMessage,
    this.syncedAt,
  });

  final String clientTransactionId;
  final String entityType;
  final String entityId;
  final Map<String, Object?> payload;
  final LocalSyncStatus status;
  final String createdAt;
  final String? errorMessage;
  final String? syncedAt;

  factory SyncQueueItem.fromMap(Map<String, Object?> map) => SyncQueueItem(
        clientTransactionId: map['client_transaction_id'] as String,
        entityType: map['entity_type'] as String,
        entityId: map['entity_id'] as String,
        payload:
            (jsonDecode(map['payload_json'] as String) as Map<String, Object?>),
        status: LocalSyncStatus.values.firstWhere(
          (e) => e.name == (map['sync_status'] as String),
          orElse: () => LocalSyncStatus.pending,
        ),
        createdAt: map['created_at'] as String,
        errorMessage: map['error_message'] as String?,
        syncedAt: map['synced_at'] as String?,
      );
}

class PendingPhotoItem {
  const PendingPhotoItem({
    required this.id,
    required this.activityType,
    required this.activityId,
    required this.localFilePath,
    required this.status,
    required this.createdAt,
    this.caption,
    this.errorMessage,
  });

  final String id;
  final String activityType;
  final String activityId;
  final String localFilePath;
  final LocalSyncStatus status;
  final String createdAt;
  final String? caption;
  final String? errorMessage;

  factory PendingPhotoItem.fromMap(Map<String, Object?> map) =>
      PendingPhotoItem(
        id: map['id'] as String,
        activityType: map['activity_type'] as String,
        activityId: map['activity_id'] as String,
        localFilePath: map['local_file_path'] as String,
        status: LocalSyncStatus.values.firstWhere(
          (e) => e.name == (map['sync_status'] as String),
          orElse: () => LocalSyncStatus.pending,
        ),
        createdAt: map['created_at'] as String,
        caption: map['caption'] as String?,
        errorMessage: map['error_message'] as String?,
      );
}

class SyncRunResult {
  const SyncRunResult({
    required this.syncedCount,
    required this.duplicateCount,
    required this.conflictCount,
    required this.failedCount,
    required this.photosUploaded,
  });

  final int syncedCount;
  final int duplicateCount;
  final int conflictCount;
  final int failedCount;
  final int photosUploaded;
}
