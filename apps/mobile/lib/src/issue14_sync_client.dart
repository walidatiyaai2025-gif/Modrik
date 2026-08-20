import 'dart:convert';
import 'dart:io';

import 'learning_gateway.dart';
import 'offline_boundary.dart';

Map<String, dynamic> issue14OperationPayload(PendingLearningOperation operation) => {
      'operation_id': operation.logicalCommandKey,
      'attempt_id': operation.attemptId,
      'attempt_question_id': operation.attemptQuestionId,
      'expected_revision': operation.expectedRevision,
      'value': operation.value,
    };

class HttpIssue14PendingSyncClient implements PendingSyncClient {
  HttpIssue14PendingSyncClient({
    required this.baseUrl,
    String? bearerToken,
    String? Function()? bearerTokenProvider,
    this.onAuthenticationRejected,
    HttpClient? client,
  })  : _staticBearerToken = bearerToken,
        _bearerTokenProvider = bearerTokenProvider,
        _client = client ?? HttpClient();

  final Uri baseUrl;
  final String? _staticBearerToken;
  final String? Function()? _bearerTokenProvider;
  final void Function()? onAuthenticationRejected;
  final HttpClient _client;

  String? get _bearerToken =>
      _bearerTokenProvider?.call() ?? _staticBearerToken;

  @override
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations) async {
    if (operations.isEmpty) {
      return const PendingSyncOutcome(acknowledgements: []);
    }

    final acknowledgements = <PendingSyncAcknowledgement>[];
    for (var offset = 0; offset < operations.length; offset += 100) {
      final end = (offset + 100).clamp(0, operations.length);
      acknowledgements.addAll(await _flushBatch(operations.sublist(offset, end)));
    }
    return PendingSyncOutcome(
      acknowledgements: List<PendingSyncAcknowledgement>.unmodifiable(acknowledgements),
    );
  }

  Future<List<PendingSyncAcknowledgement>> _flushBatch(
    List<PendingLearningOperation> operations,
  ) async {
    try {
      final request = await _client.postUrl(baseUrl.resolve('sync/answers'));
      request.headers.set(
        HttpHeaders.acceptHeader,
        'application/json, application/problem+json',
      );
      request.headers.contentType = ContentType.json;
      final token = _bearerToken;
      if (token != null && token.isNotEmpty) {
        request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
      }
      request.write(
        jsonEncode({
          'operations': operations.map(issue14OperationPayload).toList(growable: false),
        }),
      );

      final response = await request.close();
      final text = await response.transform(utf8.decoder).join();
      final payload = text.isEmpty ? <String, dynamic>{} : jsonDecode(text);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final problem = payload is Map
            ? Map<String, dynamic>.from(payload)
            : <String, dynamic>{};
        final failure = LearningFailure(
          status: response.statusCode,
          code: problem['code'] as String? ?? 'SYNC_REQUEST_FAILED',
          message: problem['detail'] as String? ?? 'The answer sync request failed.',
          retryable: problem['retryable'] as bool? ?? response.statusCode >= 500,
        );
        if (failure.status == 401 && failure.code == 'AUTHENTICATION_REQUIRED') {
          onAuthenticationRejected?.call();
        }
        throw failure;
      }
      if (payload is! Map) {
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_INVALID_SYNC_RESPONSE',
          message: 'The answer sync service returned an invalid response.',
          retryable: false,
        );
      }
      final envelope = Map<String, dynamic>.from(payload);
      final data = envelope['data'];
      if (data is! Map) {
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_INVALID_SYNC_RESPONSE',
          message: 'The answer sync service returned an invalid data envelope.',
          retryable: false,
        );
      }
      final acknowledgements = Map<String, dynamic>.from(data)['acknowledgements'];
      if (acknowledgements is! List) {
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_INVALID_SYNC_RESPONSE',
          message: 'The answer sync service returned invalid acknowledgements.',
          retryable: false,
        );
      }
      return List<PendingSyncAcknowledgement>.unmodifiable(
        acknowledgements.whereType<Map>().map(
              (item) => PendingSyncAcknowledgement.fromJson(
                Map<String, dynamic>.from(item),
              ),
            ),
      );
    } on LearningFailure {
      rethrow;
    } on SocketException catch (error) {
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_OFFLINE',
        message: error.message,
        retryable: true,
      );
    } on HttpException catch (error) {
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_ERROR',
        message: error.message,
        retryable: true,
      );
    } on FormatException {
      throw const LearningFailure(
        status: 0,
        code: 'MOBILE_INVALID_SYNC_RESPONSE',
        message: 'The answer sync service returned malformed JSON.',
        retryable: false,
      );
    }
  }
}
