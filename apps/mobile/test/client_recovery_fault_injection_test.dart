import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/issue14_sync_client.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';

const _operationId = 'mobile-recovery-operation-0001';
const _attemptId = 'attempt-recovery-1';
const _questionId = 'q-server-second';

void main() {
  test('offline before initial load does not fabricate a learning workspace', () async {
    final controller = MobileLearningController(
      gateway: const _OfflineGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

    await controller.initialize();

    expect(controller.status, MobileViewStatus.offline);
    expect(controller.messageCode, 'offline_no_downloads');
    expect(controller.lesson, isNull);
    expect(controller.attempt, isNull);
    expect(controller.pendingOperationCount, 0);
  });

  test('offline after cache preserves the exact authoritative attempt order', () async {
    final attemptCache = MemoryAttemptSnapshotCache();
    final authoritative = _attempt();
    await attemptCache.write(authoritative, DateTime.utc(2026, 8, 20, 10));
    final controller = MobileLearningController(
      gateway: const _OfflineGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      attemptSnapshotCache: attemptCache,
      clock: () => DateTime.utc(2026, 8, 20, 12),
    );

    await controller.initialize();

    expect(controller.status, MobileViewStatus.offline);
    expect(controller.messageCode, 'offline_cached');
    expect(
      controller.attempt!.questions.map((question) => question.attemptQuestionId),
      ['q-server-second', 'q-server-first'],
    );
    expect(
      controller.attempt!.questions.first.responseContract.options
          .map((option) => option.id),
      ['option-b', 'option-a'],
    );
  });

  test('timeout before ACK retains the attempted operation for same-ID reconnect replay', () async {
    final store = MemoryPendingOperationStore();
    await store.put(_pendingOperation(value: 'option-b'));
    final timeoutClient = _TimeoutSyncClient();
    final controller = MobileLearningController(
      gateway: const _NeverCalledGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      pendingOperationStore: store,
      pendingSyncClient: timeoutClient,
    )..attempt = _attempt();

    await controller.requestPendingSync();

    expect(timeoutClient.calls, 1);
    final retainedAfterTimeout = (await store.list()).single;
    expect(retainedAfterTimeout.logicalCommandKey, _operationId);
    expect(retainedAfterTimeout.value, 'option-b');
    expect(retainedAfterTimeout.transportAttempted, isTrue);

    // A later local edit must not rewrite a payload which may have reached the
    // server before the timeout. It stays a draft until authority is refreshed.
    controller.setAnswer(_questionId, 'option-a');
    await Future<void>.delayed(Duration.zero);
    final retainedAfterEdit = (await store.list()).single;
    expect(retainedAfterEdit.logicalCommandKey, _operationId);
    expect(retainedAfterEdit.value, 'option-b');
    expect(retainedAfterEdit.transportAttempted, isTrue);

    // Reconstructing the controller around a retained store models the client
    // side of a durable-store reopen. Issue #90 owns making that store durable
    // across a real OS process restart; #81 verifies no controller re-keying.
    final appliedClient = _AppliedSyncClient();
    final reconnected = MobileLearningController(
      gateway: const _NeverCalledGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      pendingOperationStore: store,
      pendingSyncClient: appliedClient,
    );

    await reconnected.requestPendingSync();

    expect(appliedClient.calls, 1);
    expect(appliedClient.seen.single.logicalCommandKey, _operationId);
    expect(appliedClient.seen.single.value, 'option-b');
    expect(await store.list(), isEmpty);
    expect(reconnected.messageCode, 'sync_complete');

    // Once ACKed, another reconnect/sync pass has nothing to resend.
    await reconnected.requestPendingSync();
    expect(appliedClient.calls, 1);
    expect(await store.list(), isEmpty);
  });

  test('multi-device stale revision refreshes authority and requeues the local draft', () async {
    final store = MemoryPendingOperationStore();
    await store.put(
      _pendingOperation(
        value: 'option-a',
        transportAttempted: true,
      ),
    );
    final authoritative = _attempt(
      firstRevision: 2,
      firstValue: 'option-a',
    );
    final gateway = _RecordingGateway(authoritativeAttempt: authoritative);
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      pendingOperationStore: store,
      pendingSyncClient: const _ConflictSyncClient(),
    )..attempt = _attempt();

    // Simulate the local device having a newer draft while another device has
    // already advanced the authoritative server revision to 2.
    controller.setAnswer(_questionId, 'option-b');
    await controller.requestPendingSync();

    expect(gateway.resumedAttemptIds, [_attemptId]);
    expect(controller.messageCode, 'sync_conflict');
    expect(
      controller.attempt!.questions.map((question) => question.attemptQuestionId),
      ['q-server-second', 'q-server-first'],
    );
    final replacement = (await store.list()).single;
    expect(replacement.logicalCommandKey, isNot(_operationId));
    expect(replacement.expectedRevision, 2);
    expect(replacement.value, 'option-b');
    expect(replacement.transportAttempted, isFalse);
  });

  test('client clock skew cannot change the Issue #14 answer payload', () {
    final farPast = _pendingOperation(
      createdAt: DateTime.utc(2001, 1, 1),
      value: const ['option-b', 'option-a'],
    );
    final farFuture = _pendingOperation(
      createdAt: DateTime.utc(2099, 12, 31),
      value: const ['option-b', 'option-a'],
    );

    final pastPayload = issue14OperationPayload(farPast);
    final futurePayload = issue14OperationPayload(farFuture);

    expect(futurePayload, pastPayload);
    expect(futurePayload.keys.toSet(), {
      'operation_id',
      'attempt_id',
      'attempt_question_id',
      'expected_revision',
      'value',
    });
    expect(futurePayload.containsKey('created_at'), isFalse);
    expect(futurePayload.containsKey('client_timestamp'), isFalse);
    expect(futurePayload.containsKey('seed'), isFalse);
    expect(futurePayload.containsKey('score'), isFalse);
    expect(futurePayload.containsKey('question_order'), isFalse);
  });

  test('academic reset is blocked by pending sync then clears intended caches after ACK', () async {
    final pending = MemoryPendingOperationStore();
    final lessons = MemoryDownloadedContentCache();
    final attempts = MemoryAttemptSnapshotCache();
    final authoritative = _attempt();
    await pending.put(_pendingOperation(value: 'option-b'));
    await lessons.writeLesson(_lesson(), DateTime.utc(2026, 8, 20, 10));
    await attempts.write(authoritative, DateTime.utc(2026, 8, 20, 10));
    final gateway = _RecordingGateway(authoritativeAttempt: authoritative);
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      downloadedContentCache: lessons,
      attemptSnapshotCache: attempts,
      pendingOperationStore: pending,
    )
      ..academicContext = _context('track-old')
      ..attempt = authoritative;

    await controller.resetAcademicContext(
      'track-new',
      idempotencyKey: 'reset-recovery-0001',
    );

    expect(gateway.resetCalls, 0);
    expect(controller.messageCode, 'context_change_requires_sync');
    expect((await pending.list()).single.logicalCommandKey, _operationId);
    expect((await lessons.listLessons()).single.lesson.id, 'lesson-1');
    expect((await attempts.readLatest())!.attempt.id, _attemptId);
    expect(controller.attempt!.id, _attemptId);

    // Once the pending operation has been authoritatively resolved, the same
    // reset flow may proceed and only then invalidates local learning caches.
    await pending.clear();
    await controller.resetAcademicContext(
      'track-new',
      idempotencyKey: 'reset-recovery-0002',
    );

    expect(gateway.resetCalls, 1);
    expect(controller.academicContext!.academicTrackId, 'track-new');
    expect(await pending.list(), isEmpty);
    expect(await lessons.listLessons(), isEmpty);
    expect(await attempts.readLatest(), isNull);
    expect(controller.attempt, isNull);
    expect(controller.section, StudentSection.dashboard);
  });
}

PendingLearningOperation _pendingOperation({
  Object? value = 'option-b',
  DateTime? createdAt,
  bool transportAttempted = false,
}) {
  return PendingLearningOperation(
    localId: 'answer:$_attemptId:$_questionId',
    type: PendingLearningOperationType.answer,
    logicalCommandKey: _operationId,
    createdAt: createdAt ?? DateTime.utc(2026, 8, 20, 11),
    attemptId: _attemptId,
    attemptQuestionId: _questionId,
    expectedRevision: 0,
    value: value,
    transportAttempted: transportAttempted,
  );
}

Attempt _attempt({int firstRevision = 0, Object? firstValue}) {
  CurrentAnswer? currentAnswer;
  if (firstRevision > 0) {
    currentAnswer = CurrentAnswer(
      revision: firstRevision,
      value: firstValue,
      answeredAt: '2026-08-20T12:00:00Z',
    );
  }
  return Attempt(
    id: _attemptId,
    academicContextId: 'context-1',
    quizId: 'quiz-1',
    status: 'in_progress',
    blueprintVersion: 1,
    orderingAlgorithm: 'modrik-fy-v1',
    startedAt: '2026-08-20T10:00:00Z',
    completedAt: null,
    archivedAt: null,
    questions: [
      AttemptQuestion(
        attemptQuestionId: _questionId,
        position: 1,
        type: 'single_choice',
        prompt: const {ModrikLocale.en: 'Server second?'},
        responseContract: const ResponseContract(
          kind: 'single_choice',
          options: [
            ChoiceOption(
              id: 'option-b',
              label: {ModrikLocale.en: 'B'},
            ),
            ChoiceOption(
              id: 'option-a',
              label: {ModrikLocale.en: 'A'},
            ),
          ],
        ),
        currentAnswer: currentAnswer,
      ),
      const AttemptQuestion(
        attemptQuestionId: 'q-server-first',
        position: 2,
        type: 'short_text',
        prompt: {ModrikLocale.en: 'Server first?'},
        responseContract: ResponseContract(
          kind: 'short_text',
          maxLength: 100,
        ),
        currentAnswer: null,
      ),
    ],
  );
}

Lesson _lesson() => const Lesson(
      id: 'lesson-1',
      curriculumNodeId: 'node-1',
      contentVersion: 1,
      title: {ModrikLocale.en: 'Cached lesson'},
      practiceQuizId: 'quiz-1',
      blocks: [],
    );

AcademicContext _context(String trackId) => AcademicContext.fromJson({
      'state': 'active',
      'context_id': 'context-$trackId',
      'academic_track_id': trackId,
      'year_level': 'synthetic-year',
      'activated_at': '2026-08-20T10:00:00Z',
    });

class _TimeoutSyncClient implements PendingSyncClient {
  int calls = 0;

  @override
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations) {
    calls += 1;
    return Future<PendingSyncOutcome>.error(
      const LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_ERROR',
        message: 'Synthetic timeout before acknowledgement.',
        retryable: true,
      ),
    );
  }
}

class _AppliedSyncClient implements PendingSyncClient {
  int calls = 0;
  final List<PendingLearningOperation> seen = [];

  @override
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations) async {
    calls += 1;
    seen.addAll(operations);
    return PendingSyncOutcome(
      acknowledgements: operations
          .map(
            (operation) => PendingSyncAcknowledgement(
              operationId: operation.logicalCommandKey,
              outcome: 'applied',
              code: 'SYNC_ANSWER_APPLIED',
              replayed: calls > 1,
              retryable: false,
              answerRevision: operation.expectedRevision + 1,
              answeredAt: '2026-08-20T12:00:00Z',
            ),
          )
          .toList(growable: false),
    );
  }
}

class _ConflictSyncClient implements PendingSyncClient {
  const _ConflictSyncClient();

  @override
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations) async {
    return PendingSyncOutcome(
      acknowledgements: operations
          .map(
            (operation) => PendingSyncAcknowledgement(
              operationId: operation.logicalCommandKey,
              outcome: 'conflict',
              code: 'ANSWER_REVISION_CONFLICT',
              replayed: false,
              retryable: false,
              answerRevision: null,
              answeredAt: null,
            ),
          )
          .toList(growable: false),
    );
  }
}

class _RecordingGateway implements LearningGateway {
  _RecordingGateway({required this.authoritativeAttempt});

  final Attempt authoritativeAttempt;
  final List<String> resumedAttemptIds = [];
  int resetCalls = 0;

  Never _unexpected() => throw StateError('Unexpected gateway call in recovery harness.');

  @override
  Future<AcademicContext> academicContext() async => _context('track-old');

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async => _context(academicTrackId);

  @override
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required Object? value,
    required String idempotencyKey,
  }) async => _unexpected();

  @override
  Future<Lesson> lesson(String lessonId) async => _unexpected();

  @override
  Future<List<ProgressSnapshot>> progress() async => const [];

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async {
    resetCalls += 1;
    return _context(academicTrackId);
  }

  @override
  Future<Attempt> resumeAttempt(String attemptId) async {
    resumedAttemptIds.add(attemptId);
    return authoritativeAttempt;
  }

  @override
  Future<Session> session() async => _unexpected();

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) async =>
      _unexpected();

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) async =>
      _unexpected();
}

class _OfflineGateway implements LearningGateway {
  const _OfflineGateway();

  Future<T> _offline<T>() => Future<T>.error(
        const LearningFailure(
          status: 0,
          code: 'MOBILE_NETWORK_OFFLINE',
          message: 'Synthetic offline fault.',
          retryable: true,
        ),
      );

  @override
  Future<AcademicContext> academicContext() => _offline();

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) => _offline();

  @override
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required Object? value,
    required String idempotencyKey,
  }) => _offline();

  @override
  Future<Lesson> lesson(String lessonId) => _offline();

  @override
  Future<List<ProgressSnapshot>> progress() => _offline();

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) => _offline();

  @override
  Future<Attempt> resumeAttempt(String attemptId) => _offline();

  @override
  Future<Session> session() => _offline();

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) => _offline();

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) => _offline();
}

class _NeverCalledGateway extends _OfflineGateway {
  const _NeverCalledGateway();
}
