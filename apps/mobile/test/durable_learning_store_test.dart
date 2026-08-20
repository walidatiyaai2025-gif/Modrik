import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/durable_learning_store.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';

void main() {
  group('durable Mobile learning recovery', () {
    test('pending operation survives reconstruction with same frozen Issue #14 identity', () async {
      final storage = MemoryLearningRecoveryStorage();
      final firstScope = LearningRecoveryScope()..bind('account-a');
      final firstStore = DurablePendingOperationStore(
        storage: storage,
        scope: firstScope,
      );
      final original = _pendingOperation(
        transportAttempted: true,
        value: const {
          'selected': ['option-b', 'option-a'],
        },
      );

      await firstStore.put(original);

      // New scope + store instances model a process/controller reconstruction.
      final reopenedScope = LearningRecoveryScope()..bind('account-a');
      final reopenedStore = DurablePendingOperationStore(
        storage: storage,
        scope: reopenedScope,
      );
      final reopened = (await reopenedStore.list()).single;

      expect(reopened.localId, original.localId);
      expect(reopened.logicalCommandKey, original.logicalCommandKey);
      expect(reopened.attemptId, original.attemptId);
      expect(reopened.attemptQuestionId, original.attemptQuestionId);
      expect(reopened.expectedRevision, original.expectedRevision);
      expect(reopened.value, original.value);
      expect(reopened.transportAttempted, isTrue);
      expect(reopened.createdAt, original.createdAt);
    });

    test('ACK removes durable operation and another reopen cannot resend it', () async {
      final storage = MemoryLearningRecoveryStorage();
      final firstScope = LearningRecoveryScope()..bind('account-a');
      final firstStore = DurablePendingOperationStore(
        storage: storage,
        scope: firstScope,
      );
      await firstStore.put(_pendingOperation(transportAttempted: true));

      final reopenedScope = LearningRecoveryScope()..bind('account-a');
      final reopenedStore = DurablePendingOperationStore(
        storage: storage,
        scope: reopenedScope,
      );
      final sync = _AppliedSyncClient();
      final controller = MobileLearningController(
        gateway: const UnconfiguredLearningGateway(),
        config: MobileBootstrapConfig(
          apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
        ),
        pendingOperationStore: reopenedStore,
        pendingSyncClient: sync,
      );

      await controller.requestPendingSync();

      expect(sync.calls, 1);
      expect(sync.seen.single.logicalCommandKey, 'operation-stable-1');
      expect(sync.seen.single.value, 'option-b');
      expect(await reopenedStore.list(), isEmpty);

      final thirdScope = LearningRecoveryScope()..bind('account-a');
      final thirdStore = DurablePendingOperationStore(
        storage: storage,
        scope: thirdScope,
      );
      expect(await thirdStore.list(), isEmpty);

      final afterRestart = MobileLearningController(
        gateway: const UnconfiguredLearningGateway(),
        config: MobileBootstrapConfig(
          apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
        ),
        pendingOperationStore: thirdStore,
        pendingSyncClient: sync,
      );
      await afterRestart.requestPendingSync();
      expect(sync.calls, 1);
      expect(afterRestart.messageCode, 'sync_complete');
    });

    test('attempt and lesson survive reconstruction without client reordering', () async {
      final storage = MemoryLearningRecoveryStorage();
      final firstScope = LearningRecoveryScope()..bind('account-a');
      final firstAttempts = DurableAttemptSnapshotCache(
        storage: storage,
        scope: firstScope,
      );
      final firstLessons = DurableDownloadedContentCache(
        storage: storage,
        scope: firstScope,
      );
      final attempt = _attempt();
      final lesson = _lesson('lesson-a');
      final savedAt = DateTime.utc(2026, 8, 20, 18, 30);

      await firstAttempts.write(attempt, savedAt);
      await firstLessons.writeLesson(lesson, savedAt);

      final reopenedScope = LearningRecoveryScope()..bind('account-a');
      final reopenedAttempts = DurableAttemptSnapshotCache(
        storage: storage,
        scope: reopenedScope,
      );
      final reopenedLessons = DurableDownloadedContentCache(
        storage: storage,
        scope: reopenedScope,
      );

      final cachedAttempt = await reopenedAttempts.readLatest();
      final cachedLesson = await reopenedLessons.readLesson('lesson-a');

      expect(cachedAttempt, isNotNull);
      expect(cachedAttempt!.savedAt, savedAt);
      expect(
        cachedAttempt.attempt.questions
            .map((question) => question.attemptQuestionId),
        ['server-question-2', 'server-question-1'],
      );
      expect(
        cachedAttempt.attempt.questions.first.responseContract.options
            .map((option) => option.id),
        ['option-b', 'option-a'],
      );
      expect(cachedLesson, isNotNull);
      expect(cachedLesson!.lesson.id, 'lesson-a');
      expect(cachedLesson.savedAt, savedAt);
    });

    test('account scopes cannot read each other and clear only the intended account', () async {
      final storage = MemoryLearningRecoveryStorage();
      final scopeA = LearningRecoveryScope()..bind('account-a');
      final scopeB = LearningRecoveryScope()..bind('account-b');
      final pendingA = DurablePendingOperationStore(storage: storage, scope: scopeA);
      final pendingB = DurablePendingOperationStore(storage: storage, scope: scopeB);
      final attemptsA = DurableAttemptSnapshotCache(storage: storage, scope: scopeA);
      final attemptsB = DurableAttemptSnapshotCache(storage: storage, scope: scopeB);
      final lessonsA = DurableDownloadedContentCache(storage: storage, scope: scopeA);
      final lessonsB = DurableDownloadedContentCache(storage: storage, scope: scopeB);
      final savedAt = DateTime.utc(2026, 8, 20, 19);

      await pendingA.put(_pendingOperation());
      await attemptsA.write(_attempt(), savedAt);
      await lessonsA.writeLesson(_lesson('lesson-a'), savedAt);
      await pendingB.put(
        _pendingOperation(
          localId: 'answer:attempt-b:question-b',
          logicalCommandKey: 'operation-b',
          attemptId: 'attempt-b',
          attemptQuestionId: 'question-b',
        ),
      );
      await attemptsB.write(_attempt(id: 'attempt-b'), savedAt);
      await lessonsB.writeLesson(_lesson('lesson-b'), savedAt);

      expect((await pendingA.list()).single.logicalCommandKey, 'operation-stable-1');
      expect((await pendingB.list()).single.logicalCommandKey, 'operation-b');
      expect((await lessonsA.listLessons()).single.lesson.id, 'lesson-a');
      expect((await lessonsB.listLessons()).single.lesson.id, 'lesson-b');

      await storage.clearAccount('account-a');

      expect(await pendingA.list(), isEmpty);
      expect(await attemptsA.readLatest(), isNull);
      expect(await lessonsA.listLessons(), isEmpty);
      expect((await pendingB.list()).single.logicalCommandKey, 'operation-b');
      expect((await attemptsB.readLatest())!.attempt.id, 'attempt-b');
      expect((await lessonsB.listLessons()).single.lesson.id, 'lesson-b');
    });

    test('academic-reset cache operations remain bounded to snapshots, not pending sync', () async {
      final storage = MemoryLearningRecoveryStorage();
      final scope = LearningRecoveryScope()..bind('account-a');
      final pending = DurablePendingOperationStore(storage: storage, scope: scope);
      final attempts = DurableAttemptSnapshotCache(storage: storage, scope: scope);
      final lessons = DurableDownloadedContentCache(storage: storage, scope: scope);
      final savedAt = DateTime.utc(2026, 8, 20, 20);

      await pending.put(_pendingOperation());
      await attempts.write(_attempt(), savedAt);
      await lessons.writeLesson(_lesson('lesson-a'), savedAt);

      // These are exactly the cache methods used by the existing reset flow
      // after its pending-sync guard has passed. Pending Sync authority is a
      // separate store and must never be erased as a side effect.
      await attempts.clear();
      for (final cached in await lessons.listLessons()) {
        await lessons.removeLesson(cached.lesson.id);
      }

      expect(await attempts.readLatest(), isNull);
      expect(await lessons.listLessons(), isEmpty);
      expect((await pending.list()).single.logicalCommandKey, 'operation-stable-1');
    });

    test('corrupt durable payload fails closed instead of looking like empty state', () async {
      final storage = MemoryLearningRecoveryStorage();
      await storage.write(
        accountId: 'account-a',
        bucket: 'attempt_snapshot',
        payload: '{"schema_version":1,"attempt":"not-an-attempt"}',
      );
      final scope = LearningRecoveryScope()..bind('account-a');
      final attempts = DurableAttemptSnapshotCache(storage: storage, scope: scope);

      await expectLater(
        attempts.readLatest(),
        throwsA(
          isA<LearningRecoveryStorageFailure>().having(
            (failure) => failure.code,
            'code',
            'MOBILE_RECOVERY_STORAGE_INVALID',
          ),
        ),
      );
    });

    test('durable stores refuse access until Auth binds an account scope', () async {
      final pending = DurablePendingOperationStore(
        storage: MemoryLearningRecoveryStorage(),
        scope: LearningRecoveryScope(),
      );

      expect(
        pending.list,
        throwsA(
          isA<LearningRecoveryStorageFailure>().having(
            (failure) => failure.code,
            'code',
            'MOBILE_RECOVERY_SCOPE_REQUIRED',
          ),
        ),
      );
    });
  });
}

PendingLearningOperation _pendingOperation({
  String localId = 'answer:attempt-a:server-question-2',
  String logicalCommandKey = 'operation-stable-1',
  String attemptId = 'attempt-a',
  String attemptQuestionId = 'server-question-2',
  Object? value = 'option-b',
  bool transportAttempted = false,
}) =>
    PendingLearningOperation(
      localId: localId,
      type: PendingLearningOperationType.answer,
      logicalCommandKey: logicalCommandKey,
      createdAt: DateTime.utc(2026, 8, 20, 18),
      attemptId: attemptId,
      attemptQuestionId: attemptQuestionId,
      expectedRevision: 4,
      value: value,
      transportAttempted: transportAttempted,
    );

Attempt _attempt({String id = 'attempt-a'}) => Attempt(
      id: id,
      academicContextId: 'context-a',
      quizId: 'quiz-a',
      status: 'in_progress',
      blueprintVersion: 3,
      orderingAlgorithm: 'server-authoritative-v1',
      startedAt: '2026-08-20T17:00:00Z',
      completedAt: null,
      archivedAt: null,
      questions: const [
        AttemptQuestion(
          attemptQuestionId: 'server-question-2',
          position: 1,
          type: 'single_choice',
          prompt: {ModrikLocale.en: 'Second in source, first in attempt'},
          responseContract: ResponseContract(
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
          currentAnswer: null,
        ),
        AttemptQuestion(
          attemptQuestionId: 'server-question-1',
          position: 2,
          type: 'text',
          prompt: {ModrikLocale.en: 'First in source, second in attempt'},
          responseContract: ResponseContract(kind: 'text', maxLength: 120),
          currentAnswer: null,
        ),
      ],
    );

Lesson _lesson(String id) => Lesson(
      id: id,
      curriculumNodeId: 'node-a',
      contentVersion: 7,
      title: const {ModrikLocale.en: 'Cached lesson'},
      practiceQuizId: 'quiz-a',
      blocks: const [
        LessonBlock(
          id: 'block-a',
          position: 1,
          type: 'text',
          content: {ModrikLocale.en: 'Offline body'},
        ),
      ],
    );

class _AppliedSyncClient implements PendingSyncClient {
  int calls = 0;
  List<PendingLearningOperation> seen = const [];

  @override
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations) async {
    calls++;
    seen = List<PendingLearningOperation>.unmodifiable(operations);
    return PendingSyncOutcome(
      acknowledgements: operations
          .map(
            (operation) => PendingSyncAcknowledgement(
              operationId: operation.logicalCommandKey,
              outcome: 'applied',
              code: 'ANSWER_APPLIED',
              replayed: false,
              retryable: false,
              answerRevision: 5,
              answeredAt: '2026-08-20T18:05:00Z',
            ),
          )
          .toList(growable: false),
    );
  }
}
