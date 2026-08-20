import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/issue14_sync_client.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';
import 'package:modrik_mobile/src/runtime_correlation.dart';
import 'package:modrik_mobile/src/runtime_diagnostics.dart';

const _config = RuntimeDiagnosticsConfig(
  enabled: true,
  environment: 'pilot',
  build: '21',
  version: '1.0.0',
  commit: 'transport-test',
);

const _catalogueServerCorrelation = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
const _reconnectFailureCorrelation = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
const _reconnectSuccessCorrelation = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
const _syncServerCorrelation = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
const _storageFailureCorrelation = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';

void main() {
  test('learning request records backend-echoed correlation ID', () async {
    final diagnostics = RuntimeDiagnostics(
      config: _config,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );
    await diagnostics.initialize();
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    String? clientCorrelation;
    server.listen((request) async {
      clientCorrelation = request.headers.value(diagnosticCorrelationHeader);
      expect(validDiagnosticCorrelationId(clientCorrelation), clientCorrelation);
      expect(request.uri.path, '/api/v1/academic-tracks');
      request.response.headers.set(
        diagnosticCorrelationHeader,
        _catalogueServerCorrelation,
      );
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {'tracks': []},
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        bearerToken: 'fixture-session-not-diagnostic-output',
        diagnostics: diagnostics,
      );
      expect(await gateway.academicTracks(), isEmpty);

      final transportEvent = diagnostics.events.singleWhere(
        (event) => event.operation == 'learning.get.academic-tracks',
      );
      expect(transportEvent.correlationId, _catalogueServerCorrelation);
      expect(transportEvent.result, 'success');
      expect(transportEvent.connectivity, DiagnosticConnectivity.online);
      expect(transportEvent.correlationId, isNot(clientCorrelation));
    } finally {
      await server.close(force: true);
    }
  });

  test('invalid backend correlation is ignored in favor of valid request ID', () async {
    final diagnostics = RuntimeDiagnostics(
      config: _config,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );
    await diagnostics.initialize();
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    String? requestCorrelation;
    server.listen((request) async {
      requestCorrelation = request.headers.value(diagnosticCorrelationHeader);
      request.response.headers.set(
        diagnosticCorrelationHeader,
        'not-a-canonical-correlation-id',
      );
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {'tracks': []},
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        diagnostics: diagnostics,
      );
      expect(await gateway.academicTracks(), isEmpty);
      final event = diagnostics.events.singleWhere(
        (event) => event.operation == 'learning.get.academic-tracks',
      );
      expect(event.correlationId, requestCorrelation);
      expect(validDiagnosticCorrelationId(event.correlationId), event.correlationId);
    } finally {
      await server.close(force: true);
    }
  });

  test('secret-shaped backend problem code stays out of diagnostics', () async {
    const secretCode = 'SENTINEL-password-value';
    final persistence = MemoryRuntimeDiagnosticsPersistence();
    final diagnostics = RuntimeDiagnostics(
      config: _config,
      persistence: persistence,
    );
    await diagnostics.initialize();
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      request.response.statusCode = HttpStatus.serviceUnavailable;
      request.response.headers.set(
        diagnosticCorrelationHeader,
        _catalogueServerCorrelation,
      );
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'code': secretCode,
        'detail': 'Temporary failure.',
        'retryable': true,
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        diagnostics: diagnostics,
      );

      await expectLater(
        gateway.academicTracks(),
        throwsA(
          isA<LearningFailure>().having(
            (failure) => failure.code,
            'domain code remains unchanged',
            secretCode,
          ),
        ),
      );
      await Future<void>.delayed(Duration.zero);

      final event = diagnostics.events.singleWhere(
        (candidate) => candidate.operation == 'learning.get.academic-tracks',
      );
      expect(event.stableCode, isNull);
      final exported = diagnostics.exportSanitizedJson(
        locale: 'en',
        direction: 'ltr',
        connectivity: DiagnosticConnectivity.online,
        currentFlow: 'learning.dashboard',
      );
      expect(exported, isNot(contains(secretCode)));
      expect(persistence.encoded ?? '', isNot(contains(secretCode)));
    } finally {
      await server.close(force: true);
    }
  });

  test('diagnostic persistence faults cannot alter a successful learning result', () async {
    final diagnostics = RuntimeDiagnostics(
      config: _config,
      persistence: _AlwaysThrowingDiagnosticsPersistence(),
    );
    await expectLater(diagnostics.initialize(), completes);

    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      request.response.headers.set(
        diagnosticCorrelationHeader,
        _storageFailureCorrelation,
      );
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {'tracks': []},
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        diagnostics: diagnostics,
      );

      expect(await gateway.academicTracks(), isEmpty);
      await Future<void>.delayed(Duration.zero);

      final event = diagnostics.events.singleWhere(
        (candidate) => candidate.operation == 'learning.get.academic-tracks',
      );
      expect(event.result, 'success');
      expect(event.correlationId, _storageFailureCorrelation);
    } finally {
      await server.close(force: true);
    }
  });

  test('offline reconnect backend failure and success remain followable', () async {
    final diagnostics = RuntimeDiagnostics(
      config: _config,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );
    await diagnostics.initialize();

    final reservation = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    final port = reservation.port;
    await reservation.close(force: true);
    final baseUrl = Uri.parse('http://127.0.0.1:$port/api/v1/');
    final gateway = HttpLearningGateway(
      baseUrl: baseUrl,
      diagnostics: diagnostics,
    );

    await expectLater(
      gateway.academicTracks(),
      throwsA(
        isA<LearningFailure>().having(
          (failure) => failure.code,
          'code',
          'MOBILE_NETWORK_OFFLINE',
        ),
      ),
    );

    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, port);
    var requestCount = 0;
    server.listen((request) async {
      requestCount += 1;
      request.response.headers.contentType = ContentType.json;
      request.response.headers.set(
        diagnosticCorrelationHeader,
        requestCount == 1
            ? _reconnectFailureCorrelation
            : _reconnectSuccessCorrelation,
      );
      if (requestCount == 1) {
        request.response.statusCode = HttpStatus.serviceUnavailable;
        request.response.write(jsonEncode({
          'code': 'CATALOGUE_TEMPORARILY_UNAVAILABLE',
          'detail': 'Temporary failure.',
          'retryable': true,
        }));
      } else {
        request.response.write(jsonEncode({
          'data': {'tracks': []},
        }));
      }
      await request.response.close();
    });

    try {
      await expectLater(
        gateway.academicTracks(),
        throwsA(
          isA<LearningFailure>().having(
            (failure) => failure.code,
            'code',
            'CATALOGUE_TEMPORARILY_UNAVAILABLE',
          ),
        ),
      );
      expect(await gateway.academicTracks(), isEmpty);

      final transportEvents = diagnostics.events
          .where((event) => event.operation == 'learning.get.academic-tracks')
          .toList(growable: false);
      expect(transportEvents.map((event) => event.result), [
        'offline',
        'backend_failure',
        'success',
      ]);
      expect(
        transportEvents.map((event) => event.correlationId),
        containsAll([
          _reconnectFailureCorrelation,
          _reconnectSuccessCorrelation,
        ]),
      );
    } finally {
      await server.close(force: true);
    }
  });

  test('sync correlation never replaces operation ID and answer stays out of diagnostics', () async {
    const logicalOperationId = 'op-stable-issue14-001';
    const answerSentinel = 'SENTINEL learner answer payload';
    final diagnostics = RuntimeDiagnostics(
      config: _config,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );
    await diagnostics.initialize();
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    String? requestCorrelation;
    server.listen((request) async {
      requestCorrelation = request.headers.value(diagnosticCorrelationHeader);
      expect(validDiagnosticCorrelationId(requestCorrelation), requestCorrelation);
      final raw = await utf8.decodeStream(request);
      final body = jsonDecode(raw) as Map<String, dynamic>;
      final operations = body['operations'] as List<dynamic>;
      final operation = operations.single as Map<String, dynamic>;
      expect(operation['operation_id'], logicalOperationId);
      expect(operation['operation_id'], isNot(requestCorrelation));
      expect(operation['value'], answerSentinel);

      request.response.headers.set(
        diagnosticCorrelationHeader,
        _syncServerCorrelation,
      );
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {
          'acknowledgements': [
            {
              'operation_id': logicalOperationId,
              'outcome': 'applied',
              'code': 'ANSWER_APPLIED',
              'replayed': false,
              'retryable': false,
              'answer_revision': 2,
              'answered_at': '2026-08-20T22:00:00Z',
            },
          ],
        },
      }));
      await request.response.close();
    });

    try {
      final client = HttpIssue14PendingSyncClient(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        bearerToken: 'fixture-session-not-diagnostic-output',
        diagnostics: diagnostics,
      );
      final outcome = await client.flush([
        PendingLearningOperation(
          localId: 'local-1',
          type: PendingLearningOperationType.answer,
          logicalCommandKey: logicalOperationId,
          createdAt: DateTime.utc(2026, 8, 20, 22),
          attemptId: '01J00000000000000000000001',
          attemptQuestionId: '01J00000000000000000000002',
          expectedRevision: 1,
          value: answerSentinel,
        ),
      ]);

      expect(outcome.acknowledgements.single.operationId, logicalOperationId);
      final transportEvent = diagnostics.events.singleWhere(
        (event) => event.operation == 'sync.post.answers',
      );
      expect(transportEvent.correlationId, _syncServerCorrelation);
      final exported = diagnostics.exportSanitizedJson(
        locale: 'en',
        direction: 'ltr',
        connectivity: DiagnosticConnectivity.online,
        currentFlow: 'practice',
        pendingSyncCount: 0,
      );
      expect(exported, isNot(contains(answerSentinel)));
      expect(exported, isNot(contains('fixture-session-not-diagnostic-output')));
      expect(exported, contains(_syncServerCorrelation));
      expect(requestCorrelation, isNot(logicalOperationId));
    } finally {
      await server.close(force: true);
    }
  });
}

class _AlwaysThrowingDiagnosticsPersistence
    implements RuntimeDiagnosticsPersistence {
  @override
  Future<String?> read() async {
    throw StateError('injected diagnostics read failure');
  }

  @override
  Future<void> write(String encoded) async {
    throw StateError('injected diagnostics write failure');
  }

  @override
  Future<void> clear() async {
    throw StateError('injected diagnostics clear failure');
  }
}
