import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? httpClient}) : _http = httpClient ?? http.Client();

  final http.Client _http;

  Future<Map<String, Object?>> login({
    required String baseUrl,
    required String email,
    required String password,
    String deviceName = 'flutter-mobile',
  }) async {
    final response = await _http.post(
      Uri.parse('$baseUrl/api/v1/auth/login'),
      headers: const {
        HttpHeaders.contentTypeHeader: 'application/json',
        HttpHeaders.acceptHeader: 'application/json',
      },
      body: jsonEncode({
        'email': email,
        'password': password,
        'device_name': deviceName,
      }),
    );

    return _decodeOrThrow(response);
  }

  Future<void> logout({required String baseUrl, required String token}) async {
    await _http.post(
      Uri.parse('$baseUrl/api/v1/auth/logout'),
      headers: _authHeaders(token),
    );
  }

  Future<Map<String, Object?>> fetchBootstrap({
    required String baseUrl,
    required String token,
  }) async {
    final response = await _http.get(
      Uri.parse('$baseUrl/api/v1/sync/bootstrap'),
      headers: _authHeaders(token),
    );
    return _decodeOrThrow(response);
  }

  Future<Map<String, Object?>> pushSyncBatch({
    required String baseUrl,
    required String token,
    required String deviceIdentifier,
    required List<Map<String, Object?>> operations,
    String? idempotencyKey,
  }) async {
    final headers = _authHeaders(token);
    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      headers['X-Idempotency-Key'] = idempotencyKey;
    }

    final response = await _http.post(
      Uri.parse('$baseUrl/api/v1/sync/push'),
      headers: headers,
      body: jsonEncode({
        'device_identifier': deviceIdentifier,
        'operations': operations,
      }),
    );
    return _decodeOrThrow(response);
  }

  Future<Map<String, Object?>> pullSyncChanges({
    required String baseUrl,
    required String token,
    String? since,
  }) async {
    final response = await _http.post(
      Uri.parse('$baseUrl/api/v1/sync/pull'),
      headers: _authHeaders(token),
      body: jsonEncode({
        'since': ?since,
      }),
    );
    return _decodeOrThrow(response);
  }

  Future<Map<String, Object?>> uploadActivityPhoto({
    required String baseUrl,
    required String token,
    required String activityType,
    required String activityId,
    required String filePath,
    String? caption,
    String? idempotencyKey,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/api/v1/activity-photos'),
    )
      ..headers[HttpHeaders.authorizationHeader] = 'Bearer $token'
      ..headers[HttpHeaders.acceptHeader] = 'application/json'
      ..fields['activity_type'] = activityType
      ..fields['activity_id'] = activityId;

    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      request.headers['X-Idempotency-Key'] = idempotencyKey;
    }

    if (caption != null && caption.isNotEmpty) {
      request.fields['caption'] = caption;
    }

    request.files.add(await http.MultipartFile.fromPath('photo', filePath));

    final streamed = await _http.send(request);
    final response = await http.Response.fromStream(streamed);
    return _decodeOrThrow(response);
  }

  Future<Map<String, Object?>> fetchPublicTree({
    required String baseUrl,
    required String token,
  }) async {
    final response = await _http.get(
      Uri.parse('$baseUrl/api/v1/public/trees/$token'),
      headers: const {HttpHeaders.acceptHeader: 'application/json'},
    );
    return _decodeOrThrow(response);
  }

  Map<String, String> _authHeaders(String token) => {
        HttpHeaders.authorizationHeader: 'Bearer $token',
        HttpHeaders.contentTypeHeader: 'application/json',
        HttpHeaders.acceptHeader: 'application/json',
      };

  Map<String, Object?> _decodeOrThrow(http.Response response) {
    Map<String, Object?>? decoded;
    try {
      final parsed = jsonDecode(response.body);
      if (parsed is Map<String, Object?>) {
        decoded = parsed;
      }
    } catch (_) {
      // Body may not be valid JSON (e.g. plain text or HTML 500 error from web server)
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (decoded != null) {
        return decoded;
      }
      throw ApiException(
        'Format respons server tidak valid (${response.statusCode})',
        statusCode: response.statusCode,
      );
    }

    final message = (decoded?['message'] as String?) ??
        (response.body.isNotEmpty && response.body.length < 200
            ? response.body
            : 'Permintaan gagal (${response.statusCode})');
    throw ApiException(message, statusCode: response.statusCode);
  }
}
