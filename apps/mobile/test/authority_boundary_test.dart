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

  test('JSON-shaped current answers preserve arrays and cannot be mutated through source data', () {
    final source = <String>['option-b', 'option-a'];
    final payload = _multipleChoiceAttemptJson(source);
    final attempt = Attempt.fromJson(payload);
    source.add('option-c');

    final value = attempt.questions.single.currentAnswer!.value;
    expect(value, ['option-b', 'option-a']);
    expect(() => (value as List<Object?>).add('option-c'), throwsUnsupportedError);
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

  test('online resume requests the exact same attempt and accepts backend order unchanged', () async {
    final gateway = _ResumeGateway(Attempt.fromJson(_attemptJson()));
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    )..attempt = Attempt.fromJson(_attemptJson());

    await controller.resumeAttempt();

    expect(gateway.resumedAttemptIds, ['attempt-1']);
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

  test('pending JSON payload is deeply frozen before transport', () {
    final source = <Object?>[
      'option-b',
      <String, Object?>{'nested': true},
    ];
    final operation = PendingLearningOperation(
      localId: 'answer:attempt-1:q-multiple',
      type: PendingLearningOperationType.answer,
      logicalCommandKey: _testOperationId,
      createdAt: DateTime.utc(2026, 8, 20, 11),
      attemptId: 'attempt-1',
      attemptQuestionId: 'q-multiple',
      expectedRevision: 0,
      value: source,
    );
    source[0] = 'mutated';
    (source[1] as Map<String, Object?>)['nested'] = false;

    expect(operation.value, [
      'option-b',
      {'nested': true},
    ]);
    expect(() => (operation.value as List<Object?>).add('later'), throwsUnsupportedError);
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

  test('Issue #14 HTTP adapter sends canonical array answer unchanged', () async {
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
              'replayed': true,
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
          value: const ['option-b', 'option-a'],
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
      expect(operation['value'], ['option-b', 'option-a']);
      expect(operation.containsKey('seed'), isFalse);
      expect(operation.containsKey('score'), isFalse);
      expect(operation.containsKey('question_order'), isFalse);
      expect(outcome.acknowledgements.single.isApplied, isTrue);
      expect(outcome.acknowledgements.single.replayed, isTrue);
      expect(outcome.acknowledgements.single.answerRevision, 1);
    } finally {
      await server.close(force: true);
    }
  });

  test('production opaque bearer is forwarded to the canonical session endpoint', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      expect(request.method, 'GET');
      expect(request.uri.path, '/api/v1/session');
      expect(request.headers.value(HttpHeaders.authorizationHeader), 'Bearer production-session-token');
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {
          'user_id': '01J00000000000000000000001',
          'locale': 'en',
          'roles': ['student'],
        }
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse('http://${server.address.address}:${server.port}/api/v1/'),
        bearerToken: 'production-session-token',
      );
      final session = await gateway.session();
      expect(session.userId, '01J00000000000000000000001');
      expect(session.roles, ['student']);
    } finally {
      await server.close(force: true);
    }
  });

  test('start-attempt request sends quiz authority only and no seed order or scoring fields', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    final seen = Completer<Map<String, dynamic>>();
    server.listen((request) async {
      expect(request.method, 'POST');
      expect(request.uri.path, '/api/v1/attempts');
      expect(request.headers.value('Idempotency-Key'), _testOperationId);
      final raw = await utf8.decoder.bind(request).join();
      seen.complete(jsonDecode(raw) as Map<String, dynamic>);
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({'data': _attemptJson()}));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse('http://${server.address.address}:${server.port}/api/v1/'),
      );
      final attempt = await gateway.startAttempt('quiz-1', _testOperationId);
      final body = await seen.future;
      expect(body, {'quiz_id': 'quiz-1'});
      expect(body.containsKey('seed'), isFalse);
      expect(body.containsKey('question_order'), isFalse);
      expect(body.containsKey('score'), isFalse);
      expect(attempt.questions.first.attemptQuestionId, 'q-server-second');
    } finally {
      await server.close(force: true);
    }
  });

  test('terminal sync conflict reloads authority and requeues draft at new revision', () async {
    final pending = MemoryPendingOperationStore();
    await pending.put(
      PendingLearningOperation(
        localId: 'answer:attempt-1:q-server-second',
        type: PendingLearningOperationType.answer,
        logicalCommandKey: _testOperationId,
        createdAt: DateTime.utc(2026, 8, 20, 11),
        attemptId: 'attempt-1',
        attemptQuestionId: 'q-server-second',
        expectedRevision: 0,
        value: 'option-a',
        transportAttempted: true,
      ),
    );
    final authoritative = _attemptJson();
    final firstQuestion = (authoritative['questions'] as List).first as Map<String, dynamic>;
    firstQuestion['current_answer'] = {
      'revision': 2,
      'value': 'option-a',
      'answered_at': '2026-08-20T12:00:00Z',
    };
    final gateway = _ResumeGateway(Attempt.fromJson(authoritative));
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      pendingOperationStore: pending,
      pendingSyncClient: const _ConflictSyncClient(),
    )..attempt = Attempt.fromJson(_attemptJson());
    controller.setAnswer('q-server-second', 'option-b');

    await controller.requestPendingSync();

    expect(gateway.resumedAttemptIds, ['attempt-1']);
    expect(controller.messageCode, 'sync_conflict');
    final replacement = (await pending.list()).single;
    expect(replacement.logicalCommandKey, isNot(_testOperationId));
    expect(replacement.expectedRevision, 2);
    expect(replacement.value, 'option-b');
    expect(replacement.transportAttempted, isFalse);
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

Map<String, dynamic> _multipleChoiceAttemptJson(List<String> currentValue) => {
      'id': 'attempt-multiple',
      'academic_context_id': 'context-1',
      'quiz_id': 'quiz-2',
      'status': 'in_progress',
      'blueprint_version': 1,
      'ordering_algorithm': 'backend-owned',
      'started_at': '2026-08-20T10:00:00Z',
      'completed_at': null,
      'archived_at': null,
      'questions': [
        {
          'attempt_question_id': 'q-multiple',
          'position': 1,
          'type': 'multiple_choice',
          'prompt': {'en': 'Pick every matching option'},
          'response_contract': {
            'kind': 'multiple_choice',
            'options': [
              {'id': 'option-b', 'label': {'en': 'Option B'}},
              {'id': 'option-a', 'label': {'en': 'Option A'}},
              {'id': 'option-c', 'label': {'en': 'Option C'}},
            ],
          },
          'current_answer': {
            'revision': 3,
            'value': currentValue,
            'answered_at': '2026-08-20T10:30:00Z',
          },
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
    required Object? value,
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

class _ResumeGateway implements LearningGateway {
  _ResumeGateway(this.resumed);

  final Attempt resumed;
  final List<String> resumedAttemptIds = [];

  @override
  Future<Attempt> resumeAttempt(String attemptId) async {
    resumedAttemptIds.add(attemptId);
    return resumed;
  }

  @override
  Future<List<ProgressSnapshot>> progress() async => const [];

  @override
  Future<AcademicContext> academicContext() => throw UnimplementedError();

  @override
  Future<AcademicContext> activateAcademicContext(String academicTrackId, String idempotencyKey) =>
      throw UnimplementedError();

  @override
  Future<AcademicContext> resetAcademicContext(String academicTrackId, String idempotencyKey) =>
      throw UnimplementedError();

  @override
  Future<SavedAnswer> answer({required String attemptId, required String attemptQuestionId, required int expectedRevision, required Object? value, required String idempotencyKey}) =>
      throw UnimplementedError();

  @override
  Future<Lesson> lesson(String lessonId) => throw UnimplementedError();

  @override
  Future<Session> session() => throw UnimplementedError();

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) => throw UnimplementedError();

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) => throw UnimplementedError();
}

class _ConflictSyncClient implements PendingSyncClient {
  const _ConflictSyncClient();

  @override
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations) async {
    return PendingSyncOutcome(
      acknowledgements: [
        PendingSyncAcknowledgement(
          operationId: operations.single.logicalCommandKey,
          outcome: 'conflict',
          code: 'ANSWER_REVISION_CONFLICT',
          replayed: false,
          retryable: false,
          answerRevision: null,
          answeredAt: null,
        ),
      ],
    );
  }
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
  Future<SavedAnswer> answer({required String attemptId, required String attemptQuestionId, required int expectedRevision, required Object? value, required String idempotencyKey}) =>
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
