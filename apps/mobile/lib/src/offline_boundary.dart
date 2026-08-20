import 'dart:collection';

import 'models.dart';

class CachedLesson {
  const CachedLesson({required this.lesson, required this.savedAt});

  final Lesson lesson;
  final DateTime savedAt;

  bool isStaleAt(DateTime now, {Duration maxAge = const Duration(hours: 24)}) {
    return now.difference(savedAt) > maxAge;
  }
}

abstract interface class DownloadedContentCache {
  Future<CachedLesson?> readLesson(String lessonId);
  Future<List<CachedLesson>> listLessons();
  Future<void> writeLesson(Lesson lesson, DateTime savedAt);
  Future<void> removeLesson(String lessonId);
}

class MemoryDownloadedContentCache implements DownloadedContentCache {
  final Map<String, CachedLesson> _lessons = {};

  @override
  Future<List<CachedLesson>> listLessons() async =>
      List<CachedLesson>.unmodifiable(_lessons.values);

  @override
  Future<CachedLesson?> readLesson(String lessonId) async => _lessons[lessonId];

  @override
  Future<void> removeLesson(String lessonId) async {
    _lessons.remove(lessonId);
  }

  @override
  Future<void> writeLesson(Lesson lesson, DateTime savedAt) async {
    _lessons[lesson.id] = CachedLesson(lesson: lesson, savedAt: savedAt);
  }
}

class CachedAttempt {
  const CachedAttempt({required this.attempt, required this.savedAt});

  final Attempt attempt;
  final DateTime savedAt;
}

abstract interface class AttemptSnapshotCache {
  Future<CachedAttempt?> readLatest();
  Future<void> write(Attempt attempt, DateTime savedAt);
  Future<void> clear();
}

class MemoryAttemptSnapshotCache implements AttemptSnapshotCache {
  CachedAttempt? _latest;

  @override
  Future<void> clear() async => _latest = null;

  @override
  Future<CachedAttempt?> readLatest() async => _latest;

  @override
  Future<void> write(Attempt attempt, DateTime savedAt) async {
    // Store the exact server snapshot. Never reconstruct or reorder questions.
    _latest = CachedAttempt(attempt: attempt, savedAt: savedAt);
  }
}

enum PendingLearningOperationType { answer }

class PendingLearningOperation {
  const PendingLearningOperation({
    required this.localId,
    required this.type,
    required this.logicalCommandKey,
    required this.createdAt,
    required this.attemptId,
    required this.attemptQuestionId,
    required this.expectedRevision,
    required this.value,
    this.transportAttempted = false,
  });

  final String localId;
  final PendingLearningOperationType type;

  /// Stable random identifier mapped to Issue #14 `operation_id`.
  /// Once transport is attempted, the operation payload becomes immutable and
  /// the same identifier/payload pair is retained until acknowledgement.
  final String logicalCommandKey;
  final DateTime createdAt;
  final String attemptId;
  final String attemptQuestionId;
  final int expectedRevision;
  final String value;
  final bool transportAttempted;

  PendingLearningOperation markTransportAttempted() => PendingLearningOperation(
        localId: localId,
        type: type,
        logicalCommandKey: logicalCommandKey,
        createdAt: createdAt,
        attemptId: attemptId,
        attemptQuestionId: attemptQuestionId,
        expectedRevision: expectedRevision,
        value: value,
        transportAttempted: true,
      );
}

abstract interface class PendingOperationStore {
  Future<List<PendingLearningOperation>> list();
  Future<void> put(PendingLearningOperation operation);
  Future<void> remove(String localId);
  Future<void> clear();
}

class MemoryPendingOperationStore implements PendingOperationStore {
  final Map<String, PendingLearningOperation> _operations = {};

  @override
  Future<void> clear() async => _operations.clear();

  @override
  Future<List<PendingLearningOperation>> list() async =>
      UnmodifiableListView(_operations.values.toList(growable: false));

  @override
  Future<void> put(PendingLearningOperation operation) async {
    _operations[operation.localId] = operation;
  }

  @override
  Future<void> remove(String localId) async {
    _operations.remove(localId);
  }
}

class PendingSyncAcknowledgement {
  const PendingSyncAcknowledgement({
    required this.operationId,
    required this.outcome,
    required this.code,
    required this.replayed,
    required this.retryable,
    required this.answerRevision,
    required this.answeredAt,
  });

  factory PendingSyncAcknowledgement.fromJson(Map<String, dynamic> json) {
    return PendingSyncAcknowledgement(
      operationId: json['operation_id'] as String,
      outcome: json['outcome'] as String,
      code: json['code'] as String,
      replayed: json['replayed'] as bool? ?? false,
      retryable: json['retryable'] as bool? ?? false,
      answerRevision: (json['answer_revision'] as num?)?.toInt(),
      answeredAt: json['answered_at'] as String?,
    );
  }

  final String operationId;
  final String outcome;
  final String code;
  final bool replayed;
  final bool retryable;
  final int? answerRevision;
  final String? answeredAt;

  bool get isApplied => outcome == 'applied';
  bool get isConflict => outcome == 'conflict';
  bool get isRejected => outcome == 'rejected';
}

class PendingSyncOutcome {
  const PendingSyncOutcome({required this.acknowledgements});

  final List<PendingSyncAcknowledgement> acknowledgements;
}

class SyncContractUnavailable implements Exception {
  const SyncContractUnavailable();
}

abstract interface class PendingSyncClient {
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations);
}

/// Used only when no canonical sync transport can be configured. It has no
/// endpoint or alternate wire protocol; Issue #14 owns those details.
class DeferredIssue14SyncClient implements PendingSyncClient {
  const DeferredIssue14SyncClient();

  @override
  Future<PendingSyncOutcome> flush(List<PendingLearningOperation> operations) {
    return Future.error(const SyncContractUnavailable());
  }
}
