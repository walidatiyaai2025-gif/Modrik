import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/runtime_correlation.dart';
import 'package:modrik_mobile/src/runtime_diagnostics.dart';

const _config = RuntimeDiagnosticsConfig(
  enabled: true,
  environment: 'fixture-acceptance',
  build: 'slot7',
  version: '1.0.0',
  commit: 'integrated-main',
);

void main() {
  final liveAcceptanceEnabled =
      Platform.environment['MODRIK_OBSERVABILITY_LIVE_ACCEPTANCE'] == 'true';

  test(
    'C: Mobile Learning reaches live Backend with one safe support reference',
    () async {
      final backend = Platform.environment['MODRIK_API_BASE_URL'] ?? 'http://127.0.0.1:8000';
      final evidencePath = Platform.environment['OBS_MOBILE_EVIDENCE'] ?? '/tmp/modrik-observability-mobile.json';
      final fixtureBearer = Platform.environment['MODRIK_FIXTURE_BEARER_TOKEN'] ??
          'SENTINEL_BEARER_101_FIXTURE_ONLY';
      final sentinels = <String>[
        fixtureBearer,
        'SENTINEL_COOKIE_101_FIXTURE_ONLY',
        'SENTINEL_PASSWORD_101_FIXTURE_ONLY',
        'SENTINEL_PROVIDER_SECRET_101_FIXTURE_ONLY',
        'SENTINEL_LEARNER_ANSWER_101_FIXTURE_ONLY',
        'SENTINEL_QUESTION_TEXT_101_FIXTURE_ONLY',
        'sentinel.person.101@example.test',
        'SENTINEL_NAME_101_FIXTURE_ONLY',
      ];

      final persistence = MemoryRuntimeDiagnosticsPersistence();
      final diagnostics = RuntimeDiagnostics(
        config: _config,
        persistence: persistence,
      );
      await diagnostics.initialize();

      diagnostics.recordUnexpected(
        StateError(sentinels.join(' | ')),
        StackTrace.current,
        operation: 'flutter.unhandled',
      );
      diagnostics.record(
        severity: DiagnosticSeverity.warn,
        category: 'privacy',
        correlationId: 'local',
        operation: 'privacy.sentinel',
        result: 'sanitized',
        metadata: {
          'authorization': sentinels[0],
          'cookie': sentinels[1],
          'password': sentinels[2],
          'provider_secret': sentinels[3],
          'answer': sentinels[4],
          'question_text': sentinels[5],
          'email': sentinels[6],
          'name': sentinels[7],
        },
      );

      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse('${backend.replaceAll(RegExp(r'/+$'), '')}/v1/'),
        bearerToken: fixtureBearer,
        diagnostics: diagnostics,
      );
      const missingLessonId = '01J99999999999999999999999';
      LearningFailure? liveFailure;
      try {
        await gateway.lesson(missingLessonId);
        fail('Expected representative live Backend learning failure.');
      } on LearningFailure catch (failure) {
        liveFailure = failure;
      }

      expect(liveFailure, isNotNull);
      expect(liveFailure.status, 404);
      expect(liveFailure.code, isNotEmpty);
      final liveEvent = diagnostics.events.lastWhere(
        (event) =>
            event.operation == 'learning.get.lessons' &&
            event.result == 'backend_failure',
      );
      expect(validDiagnosticCorrelationId(liveEvent.correlationId), liveEvent.correlationId);
      expect(liveEvent.stableCode, liveFailure.code);
      expect(liveEvent.metadata['http_status'], 404);

      const syncOperationId = 'op-stable-issue14-acceptance-001';
      expect(liveEvent.correlationId, isNot(syncOperationId));

      final invalidCorrelationServer = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
      String? invalidFallbackRequestCorrelation;
      invalidCorrelationServer.listen((request) async {
        invalidFallbackRequestCorrelation = request.headers.value(diagnosticCorrelationHeader);
        request.response.statusCode = HttpStatus.serviceUnavailable;
        request.response.headers.set(
          diagnosticCorrelationHeader,
          'Bearer SENTINEL_PROVIDER_SECRET_101_FIXTURE_ONLY',
        );
        request.response.headers.contentType = ContentType.json;
        request.response.write(jsonEncode({
          'code': 'LEARNING_TEMPORARY_FAILURE',
          'detail': 'Synthetic fixture failure.',
          'retryable': true,
        }));
        await request.response.close();
      });

      try {
        final fallbackGateway = HttpLearningGateway(
          baseUrl: Uri.parse(
            'http://${invalidCorrelationServer.address.address}:${invalidCorrelationServer.port}/v1/',
          ),
          diagnostics: diagnostics,
        );
        await expectLater(
          fallbackGateway.academicTracks(),
          throwsA(
            isA<LearningFailure>().having(
              (failure) => failure.code,
              'code',
              'LEARNING_TEMPORARY_FAILURE',
            ),
          ),
        );
        final fallbackEvent = diagnostics.events.lastWhere(
          (event) =>
              event.operation == 'learning.get.academic-tracks' &&
              event.result == 'backend_failure',
        );
        expect(fallbackEvent.correlationId, invalidFallbackRequestCorrelation);
        expect(validDiagnosticCorrelationId(fallbackEvent.correlationId), fallbackEvent.correlationId);
        expect(
          fallbackEvent.correlationId,
          isNot('Bearer SENTINEL_PROVIDER_SECRET_101_FIXTURE_ONLY'),
        );

        await Future<void>.delayed(const Duration(milliseconds: 20));
        final exported = diagnostics.exportSanitizedJson(
          locale: 'en',
          direction: 'ltr',
          connectivity: DiagnosticConnectivity.online,
          currentFlow: 'learning.acceptance',
        );
        for (final sentinel in sentinels) {
          expect(exported, isNot(contains(sentinel)));
          expect(persistence.encoded ?? '', isNot(contains(sentinel)));
        }

        final evidence = <String, Object?>{
          'main_sha': Platform.environment['ACCEPTANCE_MAIN_SHA'] ?? 'unknown',
          'surface': 'mobile',
          'cases': {
            'C_mobile_learning_backend_failure': {
              'correlation_id': liveEvent.correlationId,
              'status': liveFailure.status,
              'code': liveFailure.code,
              'operation': liveEvent.operation,
              'result': liveEvent.result,
              'sync_operation_id_distinct': liveEvent.correlationId != syncOperationId,
            },
            'C_invalid_backend_correlation_fallback': {
              'request_correlation_id': invalidFallbackRequestCorrelation,
              'final_correlation_id': fallbackEvent.correlationId,
              'fallback_preserved': fallbackEvent.correlationId == invalidFallbackRequestCorrelation,
            },
          },
          'privacy_sentinel_count': sentinels.length,
          'diagnostic_export_bytes': utf8.encode(exported).length,
        };
        final encodedEvidence = const JsonEncoder.withIndent('  ').convert(evidence);
        for (final sentinel in sentinels) {
          expect(encodedEvidence, isNot(contains(sentinel)));
        }
        await File(evidencePath).writeAsString(encodedEvidence, flush: true);
      } finally {
        await invalidCorrelationServer.close(force: true);
      }
    },
    skip: liveAcceptanceEnabled
        ? false
        : 'Requires the dedicated live observability acceptance workflow.',
  );
}
