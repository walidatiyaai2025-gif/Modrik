import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/issue14_sync_client.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';

const _testOperationId = 'mobile-test-operation-0001';

void main() {
  test('attempt parsing and cache preserve backend question and option order exactly', () async {
    final attempt = Attempt.fromJson(_attemptJson());
    expect(
      attempt.questions.map((question) => question.attemptQuestionId),
      ['q-server-second', 'q-server-first'],
    );
    expect(
      attempt.questions.first.responseContract.options.map((option) => option.id),
      ['option-b', 'option-a'],
    );

    final cache = MemoryAttemptSnapshotCache();
    await cache.write(attempt, DateTime.utc(2026, 8, 20));
    final restored = (await cache.readLatest())!.attempt;
    expect(
      restored.questions.map((question) => question.attemptQuestionId),
      ['q-server-second', 'q-server-first'],
    );
    expect(
      restored.questions.first.responseContract.options.map((option) => option.id),
      ['option-b', 'option-a'],
    );
  });

  test('logical command keys satisfy canonical idempotency length and visible ASCII', () {
    final key = newLogicalCommandKey();
    expect(key.length, inInclusiveRange(16, 128));
    expect(key, matches(RegExp(r'^[\x21-\x7E]+$')));
  });

  test('offline initialization resumes only the cached authoritative attempt snapshot', () async {
    final cache = MemoryAttemptSnapshotCache();
    await cache.write(Attempt.fromJson(_attemptJson()), DateTime.utc(2026, 8, 20));
    final controller = MobileLearningController(
      gateway: const _OfflineGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      attemptSnapshotCache: cache,
      clock: () => DateTime.utc(2026, 8, 20, 12),
    );
    await controller.initialize();

    expect(controller.status, MobileViewStatus.offline);
    expect(
      controller.attempt!.questions.map((question) => question.attemptQuestionId),
      ['q-server-second', 'q-server-first'],
    );
  });

  test('pending answer keeps one operation ID while edited before transport', () async {
    final pending = MemoryPendingOperationStore();
    final cache = MemoryAttemptSnapshotCache();
    await cache.write(Attempt.fromJson(_attemptJson()), DateTime.utc(2026, 8, 20));
    final controller = MobileLearningController(
      gateway: const _OfflineGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      attemptSnapshotCache: cache,
      pendingOperationStore: pending,
      clock: () => DateTime.utc(2026, 8, 20, 12),
    );
    await controller.initialize();

    controller.setAnswer('q-server-second', 'option-b');
    await Future<void>.delayed(Duration.zero);
    final first = (await pending.list()).single;
    controller.setAnswer('q-server-second', 'option-a');
    await Future<void>.delayed(Duration.zero);
    final second = (await pending.list()).single;

    expect(second.logicalCommandKey, first.logicalCommandKey);
    expect(second.value, 'option-a');
    expect(second.expectedRevision, 0);
    expect(second.transportAttempted, isFalse);
  });

  test('pending payload becomes immutable after transport starts', () async {
    final pending = MemoryPendingOperationStore();
    final cache = MemoryAttemptSnapshotCache();
    await cache.write(Attempt.fromJson(_attemptJson()), DateTime.utc(2026, 8, 20));
    await pending.put(
      PendingLearningOperation(
        localId: 'answer:attempt-1:q-server-second',
        type: PendingLearningOperationType.answer,
        logicalCommandKey: _testOperationId,
        createdAt: DateTime.utc(2026, 8, 20, 11),
        attemptId: 'attempt-1',
        attemptQuestionId: 'q-server-second',
        expectedRevision: 0,
        value: 'option-b',
        transportAttempted: true,
      ),
    );
    final controller = MobileLearningController(
      gateway: const _OfflineGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      attemptSnapshotCache: cache,
      pendingOperationStore: pending,
      clock: () => DateTime.utc(2026, 8, 20, 12),
    );
    await controller.initialize();

    controller.setAnswer('q-server-second', 'option-a');
    await Future<void>.delayed(Duration.zero);
    final retained = (await pending.list()).single;

    expect(retained.logicalCommandKey, _testOperationId);
    expect(retained.value, 'option-b');
    expect(retained.transportAttempted, isTrue);
    expect(controller.answers['q-server-second'], 'option-a');
  });

  test('Issue #14 HTTP adapter sends only the canonical operation contract', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    final seen = Completer<Map<String, dynamic>>();
    server.listen((request) async {
      final raw = await utf8.decoder.bind(request).join();
      seen.complete(jsonDecode(raw) as Map<String, dynamic>);
      expect(request.method, 'POST');
      expect(request.uri.path, '/api/v1/sync/answers');
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {
          'acknowledgements': [
            {
              'operation_id': _testOperationId,
              'outcome': 'applied',
              'code': 'SYNC_ANSWER_APPLIED',
              'replayed': false,
              'retryable': false,
              'answer_revision': 1,
              'answered_at': '2026-08-20T12:00:00Z',
            }
          ]
        }
      }));
      await request.response.close();
    });

    try {
      final client = HttpIssue14PendingSyncClient(
        baseUrl: Uri.parse('http://${server.address.address}:${server.port}/api/v1/'),
      );
      final outcome = await client.flush([
        PendingLearningOperation(
          localId: 'answer:attempt-1:q-server-second',
          type: PendingLearningOperationType.answer,
          logicalCommandKey: _testOperationId,
          createdAt: DateTime.utc(2026, 8, 20, 11),
          attemptId: '01J00000000000000000000001',
          attemptQuestionId: '01J00000000000000000000002',
          expectedRevision: 0,
          value: 'option-b',
        ),
      ]);
      final payload = await seen.future;
      final operation = (payload['operations'] as List).single as Map<String, dynamic>;

      expect(payload.keys, ['operations']);
      expect(operation.keys.toSet(), {
        'operation_id',
        'attempt_id',
        'attempt_question_id',
        'expected_revision',
        'value',
      });
      expect(operation.containsKey('seed'), isFalse);
      expect(operation.containsKey('score'), isFalse);
      expect(operation.containsKey('question_order'), isFalse);
      expect(outcome.acknowledgements.single.isApplied, isTrue);
      expect(outcome.acknowledgements.single.answerRevision, 1);
    } finally {
      await server.close(force: true);
    }
  });

  test('academic reset is backend-owned and clears stale local learning snapshots', () async {
    final gateway = _ResetGateway();
    final lessons = MemoryDownloadedContentCache();
    final attempts = MemoryAttemptSnapshotCache();
    await lessons.writeLesson(_lesson(), DateTime.utc(2026, 8, 20));
    await attempts.write(Attempt.fromJson(_attemptJson()), DateTime.utc(2026, 8, 20));
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
        academicTrackId: 'track-new',
      ),
      downloadedContentCache: lessons,
      attemptSnapshotCache: attempts,
    )
      ..academicContext = _context('track-old')
      ..attempt = Attempt.fromJson(_attemptJson());

    await controller.resetConfiguredAcademicContext();

    expect(gateway.resetCalls, 1);
    expect(controller.academicContext!.academicTrackId, 'track-new');
    expect(await lessons.listLessons(), isEmpty);
    expect(await attempts.readLatest(), isNull);
    expect(controller.attempt, isNull);
    expect(controller.section, StudentSection.dashboard);
  });

  test('Issue #14 adapter has no alternate fallback transport', () async {
    const client = DeferredIssue14SyncClient();
    await expectLater(client.flush(const []), throwsA(isA<SyncContractUnavailable>()));
  });
}

Map<String, dynamic> _attemptJson() => {
      'id': 'attempt-1',
      'academic_context_id': 'context-1',
      'quiz_id': 'quiz-1',
      'status': 'in_progress',
      'blueprint_version': 1,
      'ordering_algorithm': 'backend-owned',
      'started_at': '2026-08-20T10:00:00Z',
      'completed_at': null,
      'archived_at': null,
      'questions': [
        {
          'attempt_question_id': 'q-server-second',
          'position': 2,
          'type': 'single_choice',
          'prompt': {'en': 'Server persisted first visible question', 'ar': 'السؤال الأول المحفوظ', 'fr': 'Première question persistée'},
          'response_contract': {
            'kind': 'single_choice',
            'options': [
              {'id': 'option-b', 'label': {'en': 'Option B', 'ar': 'الخيار ب', 'fr': 'Option B'}},
              {'id': 'option-a', 'label': {'en': 'Option A', 'ar': 'الخيار أ', 'fr': 'Option A'}},
            ],
          },
          'current_answer': null,
        },
        {
          'attempt_question_id': 'q-server-first',
          'position': 1,
          'type': 'short_text',
          'prompt': {'en': 'Server persisted second visible question', 'ar': 'السؤال الثاني المحفوظ', 'fr': 'Deuxième question persistée'},
          'response_contract': {'kind': 'short_text', 'max_length': 120},
          'current_answer': null,
        },
      ],
    };

AcademicContext _context(String trackId) => AcademicContext.fromJson({
      'state': 'active',
      'context_id': 'context-1',
      'academic_track_id': trackId,
      'year_level': 'Year 7',
      'activated_at': '2026-08-20T10:00:00Z',
    });

Lesson _lesson() => Lesson.fromJson({
      'id': 'lesson-1',
      'curriculum_node_id': 'node-1',
      'content_version': 1,
      'title': {'en': 'Lesson', 'ar': 'درس', 'fr': 'Leçon'},
      'practice_quiz_id': 'quiz-1',
      'blocks': const [],
    });

class _OfflineGateway implements LearningGateway {
  const _OfflineGateway();

  LearningFailure get _offline => const LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_OFFLINE',
        message: 'offline',
        retryable: true,
      );

  @override
  Future<AcademicContext> academicContext() => Future.error(_offline);

  @override
  Future<AcademicContext> activateAcademicContext(String academicTrackId, String idempotencyKey) =>
      Future.error(_offline);

  @override
  Future<AcademicContext> resetAcademicContext(String academicTrackId, String idempotencyKey) =>
      Future.error(_offline);

  @override
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required String value,
    required String idempotencyKey,
  }) =>
      Future.error(_offline);

  @override
  Future<Lesson> lesson(String lessonId) => Future.error(_offline);

  @override
  Future<List<ProgressSnapshot>> progress() => Future.error(_offline);

  @override
  Future<Attempt> resumeAttempt(String attemptId) => Future.error(_offline);

  @override
  Future<Session> session() => Future.error(_offline);

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) => Future.error(_offline);

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) => Future.error(_offline);
}

class _ResetGateway implements LearningGateway {
  int resetCalls = 0;

  @override
  Future<AcademicContext> resetAcademicContext(String academicTrackId, String idempotencyKey) async {
    resetCalls += 1;
    return _context(academicTrackId);
  }

  @override
  Future<List<ProgressSnapshot>> progress() async => const [];

  @override
  Future<AcademicContext> academicContext() => throw UnimplementedError();

  @override
  Future<AcademicContext> activateAcademicContext(String academicTrackId, String idempotencyKey) =>
      throw UnimplementedError();

  @override
  Future<SavedAnswer> answer({required String attemptId, required String attemptQuestionId, required int expectedRevision, required String value, required String idempotencyKey}) =>
      throw UnimplementedError();

  @override
  Future<Lesson> lesson(String lessonId) => throw UnimplementedError();

  @override
  Future<Attempt> resumeAttempt(String attemptId) => throw UnimplementedError();

  @override
  Future<Session> session() => throw UnimplementedError();

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) => throw UnimplementedError();

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) => throw UnimplementedError();
}
