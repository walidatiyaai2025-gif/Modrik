import 'dart:async';
import 'dart:collection';

import 'package:flutter/foundation.dart';

import 'learning_gateway.dart';
import 'models.dart';
import 'offline_boundary.dart';

enum MobileViewStatus { loading, ready, empty, error, offline, permission }

enum StudentSection { dashboard, study, practice, progress }

class MobileLearningController extends ChangeNotifier {
  MobileLearningController({
    required this.gateway,
    required this.config,
    DownloadedContentCache? downloadedContentCache,
    AttemptSnapshotCache? attemptSnapshotCache,
    PendingOperationStore? pendingOperationStore,
    PendingSyncClient? pendingSyncClient,
    DateTime Function()? clock,
  })  : downloadedContentCache =
            downloadedContentCache ?? MemoryDownloadedContentCache(),
        attemptSnapshotCache =
            attemptSnapshotCache ?? MemoryAttemptSnapshotCache(),
        pendingOperationStore =
            pendingOperationStore ?? MemoryPendingOperationStore(),
        pendingSyncClient =
            pendingSyncClient ?? const DeferredIssue14SyncClient(),
        _clock = clock ?? DateTime.now;

  final LearningGateway gateway;
  final MobileBootstrapConfig config;
  final DownloadedContentCache downloadedContentCache;
  final AttemptSnapshotCache attemptSnapshotCache;
  final PendingOperationStore pendingOperationStore;
  final PendingSyncClient pendingSyncClient;
  final DateTime Function() _clock;

  MobileViewStatus status = MobileViewStatus.loading;
  StudentSection section = StudentSection.dashboard;
  ModrikLocale locale = ModrikLocale.en;
  Session? session;
  AcademicContext? academicContext;
  Lesson? lesson;
  Attempt? attempt;
  AttemptResult? result;
  List<ProgressSnapshot> progress = const [];
  bool isStale = false;
  bool isBusy = false;
  int pendingOperationCount = 0;
  String? messageCode;

  final Map<String, Object?> _answers = {};
  final Map<String, Object?> _savedAnswers = {};
  final Map<String, int> _revisions = {};

  UnmodifiableMapView<String, Object?> get answers =>
      UnmodifiableMapView(_answers);
  bool get isOffline => status == MobileViewStatus.offline;
  bool get requiresOnboarding =>
      academicContext?.requiresOnboarding ?? false;
  bool get hasLesson => lesson != null;
  bool get hasAttempt => attempt != null;
  bool get hasUnsavedAnswers => _answers.entries.any(
        (entry) => !jsonValueEquals(_savedAnswers[entry.key], entry.value),
      );

  Future<void> initialize() async {
    status = MobileViewStatus.loading;
    messageCode = null;
    notifyListeners();

    if (!config.isConfigured) {
      status = MobileViewStatus.permission;
      messageCode = 'api_not_configured';
      await _refreshPendingCount();
      notifyListeners();
      return;
    }

    try {
      session = await gateway.session();
      locale = session!.locale;
      academicContext = await gateway.academicContext();
      progress = await gateway.progress();
      await _loadConfiguredLessonOnline();
      await _restoreAttemptSnapshot();
      await _refreshPendingCount();
      status = _hasAnyWorkspaceData
          ? MobileViewStatus.ready
          : MobileViewStatus.empty;
      isStale = false;
    } on LearningFailure catch (failure) {
      await _handleFailure(failure);
    } catch (_) {
      status = MobileViewStatus.error;
      messageCode = 'unexpected_error';
    }
    notifyListeners();
  }

  bool get _hasAnyWorkspaceData =>
      academicContext != null || lesson != null || progress.isNotEmpty;

  Future<void> _loadConfiguredLessonOnline() async {
    final lessonId = config.initialLessonId;
    if (lessonId == null) return;
    final fetched = await gateway.lesson(lessonId);
    lesson = fetched;
    await downloadedContentCache.writeLesson(fetched, _clock());
  }

  Future<void> _restoreAttemptSnapshot() async {
    final cached = await attemptSnapshotCache.readLatest();
    if (cached == null) return;
    attempt = cached.attempt;
    _hydrateAttemptAnswers(cached.attempt);
  }

  Future<void> _handleFailure(LearningFailure failure) async {
    if (failure.isPermission) {
      status = MobileViewStatus.permission;
      messageCode = failure.code;
      return;
    }
    if (failure.isNetwork) {
      await _loadOfflineWorkspace();
      return;
    }
    status = MobileViewStatus.error;
    messageCode = failure.code;
  }

  Future<void> _loadOfflineWorkspace() async {
    final lessonId = config.initialLessonId;
    CachedLesson? cachedLesson;
    if (lessonId != null) {
      cachedLesson = await downloadedContentCache.readLesson(lessonId);
      lesson = cachedLesson?.lesson;
    }
    await _restoreAttemptSnapshot();
    await _refreshPendingCount();
    isStale = cachedLesson?.isStaleAt(_clock()) ?? false;
    status = MobileViewStatus.offline;
    messageCode = lesson == null && attempt == null
        ? 'offline_no_downloads'
        : 'offline_cached';
  }

  void setLocale(ModrikLocale next) {
    if (locale == next) return;
    locale = next;
    notifyListeners();
  }

  void setSection(StudentSection next) {
    if (section == next) return;
    section = next;
    messageCode = null;
    notifyListeners();
  }

  Future<void> activateConfiguredAcademicContext() async {
    final trackId = config.academicTrackId;
    if (isOffline) {
      messageCode = 'onboarding_requires_connection';
      notifyListeners();
      return;
    }
    if (trackId == null) {
      messageCode = 'academic_track_not_configured';
      notifyListeners();
      return;
    }
    await _runBusy(() async {
      try {
        academicContext = await gateway.activateAcademicContext(
          trackId,
          newLogicalCommandKey(),
        );
        messageCode = null;
        status = MobileViewStatus.ready;
      } on LearningFailure catch (failure) {
        await _handleFailure(failure);
      }
    });
  }

  Future<void> resetConfiguredAcademicContext() async {
    final trackId = config.academicTrackId;
    if (isOffline) {
      messageCode = 'onboarding_requires_connection';
      notifyListeners();
      return;
    }
    if (trackId == null) {
      messageCode = 'academic_track_not_configured';
      notifyListeners();
      return;
    }
    await _refreshPendingCount();
    if (pendingOperationCount > 0 || hasUnsavedAnswers) {
      messageCode = 'context_change_requires_sync';
      notifyListeners();
      return;
    }
    if (academicContext?.academicTrackId == trackId) {
      messageCode = 'academic_context_already_active';
      notifyListeners();
      return;
    }

    await _runBusy(() async {
      try {
        academicContext = await gateway.resetAcademicContext(
          trackId,
          newLogicalCommandKey(),
        );
        for (final cached in await downloadedContentCache.listLessons()) {
          await downloadedContentCache.removeLesson(cached.lesson.id);
        }
        await attemptSnapshotCache.clear();
        lesson = null;
        attempt = null;
        result = null;
        _answers.clear();
        _savedAnswers.clear();
        _revisions.clear();
        progress = await gateway.progress();
        await _loadConfiguredLessonOnline();
        section = StudentSection.dashboard;
        status = MobileViewStatus.ready;
        isStale = false;
        messageCode = 'context_reset_complete';
      } on LearningFailure catch (failure) {
        await _handleFailure(failure);
      }
    });
  }

  Future<void> refresh() => initialize();

  Future<void> startPractice() async {
    final currentLesson = lesson;
    if (currentLesson == null) {
      messageCode = 'lesson_required';
      notifyListeners();
      return;
    }
    if (isOffline) {
      messageCode = attempt == null
          ? 'new_attempt_requires_connection'
          : 'resume_cached_attempt';
      notifyListeners();
      return;
    }
    await _runBusy(() async {
      try {
        final started = await gateway.startAttempt(
          currentLesson.practiceQuizId,
          newLogicalCommandKey(),
        );
        _acceptAttemptSnapshot(started);
        result = null;
        section = StudentSection.practice;
        messageCode = null;
        await attemptSnapshotCache.write(started, _clock());
      } on LearningFailure catch (failure) {
        await _handleFailure(failure);
      }
    });
  }

  Future<void> resumeAttempt() async {
    final currentAttempt = attempt;
    if (currentAttempt == null) return;
    if (isOffline) {
      _hydrateAttemptAnswers(currentAttempt);
      section = StudentSection.practice;
      messageCode = 'resume_cached_attempt';
      notifyListeners();
      return;
    }
    await _runBusy(() async {
      try {
        final resumed = await gateway.resumeAttempt(currentAttempt.id);
        _acceptAttemptSnapshot(resumed);
        section = StudentSection.practice;
        messageCode = null;
        await attemptSnapshotCache.write(resumed, _clock());
      } on LearningFailure catch (failure) {
        await _handleFailure(failure);
      }
    });
  }

  void setAnswer(String attemptQuestionId, Object? value) {
    final frozen = freezeJsonValue(value);
    _answers[attemptQuestionId] = frozen;
    if (isOffline && attempt != null) {
      unawaited(_queuePendingAnswer(attemptQuestionId, frozen));
    }
    notifyListeners();
  }

  Future<void> _queuePendingAnswer(
    String attemptQuestionId,
    Object? value,
  ) async {
    final currentAttempt = attempt;
    if (currentAttempt == null) return;
    final localId = _answerLocalId(currentAttempt.id, attemptQuestionId);
    final existing = (await pendingOperationStore.list())
        .where((operation) => operation.localId == localId)
        .firstOrNull;

    // Never mutate a payload after it may have reached Issue #14. A later
    // local edit remains a draft until this operation is acknowledged and the
    // canonical server revision is refreshed.
    if (existing?.transportAttempted ?? false) return;

    await pendingOperationStore.put(
      PendingLearningOperation(
        localId: localId,
        type: PendingLearningOperationType.answer,
        logicalCommandKey:
            existing?.logicalCommandKey ?? newLogicalCommandKey(),
        createdAt: existing?.createdAt ?? _clock(),
        attemptId: currentAttempt.id,
        attemptQuestionId: attemptQuestionId,
        expectedRevision: _revisions[attemptQuestionId] ?? 0,
        value: value,
      ),
    );
    await _refreshPendingCount();
    notifyListeners();
  }

  Future<void> _queueChangedAnswers() async {
    final currentAttempt = attempt;
    if (currentAttempt == null) return;
    for (final question in currentAttempt.questions) {
      final questionId = question.attemptQuestionId;
      final value = _answers[questionId];
      if (!jsonValueEquals(_savedAnswers[questionId], value)) {
        await _queuePendingAnswer(questionId, value);
      }
    }
  }

  Future<void> submitPractice() async {
    final currentAttempt = attempt;
    if (currentAttempt == null) return;
    if (currentAttempt.questions.any(
      (question) => _isEmptyAnswer(_answers[question.attemptQuestionId]),
    )) {
      messageCode = 'answer_every_question';
      notifyListeners();
      return;
    }

    await _queueChangedAnswers();
    if (isOffline) {
      messageCode = 'submit_requires_sync';
      notifyListeners();
      return;
    }

    await _runBusy(() async {
      // Normally one pass is enough. Extra bounded passes handle a newer local
      // draft created after a timed-out operation while preserving operation
      // immutability and server revision authority.
      for (var pass = 0; pass < 3; pass++) {
        await _refreshPendingCount();
        if (pendingOperationCount == 0) break;
        final canContinue = await _flushPendingOperations();
        if (!canContinue) return;
      }
      await _refreshPendingCount();
      if (pendingOperationCount > 0) {
        messageCode = 'submit_requires_sync';
        return;
      }

      final activeAttempt = attempt;
      if (activeAttempt == null) return;
      try {
        final submitted = await gateway.submit(
          activeAttempt.id,
          newLogicalCommandKey(),
        );
        result = submitted;
        _acceptAttemptSnapshot(submitted.attempt);
        await attemptSnapshotCache.write(submitted.attempt, _clock());
        progress = await gateway.progress();
        messageCode = null;
      } on LearningFailure catch (failure) {
        await _handleFailure(failure);
      }
    });
  }

  Future<void> requestPendingSync() async {
    if (isOffline) {
      messageCode = 'submit_requires_sync';
      notifyListeners();
      return;
    }
    await _runBusy(() async {
      for (var pass = 0; pass < 3; pass++) {
        await _refreshPendingCount();
        if (pendingOperationCount == 0) {
          messageCode = 'sync_complete';
          return;
        }
        final canContinue = await _flushPendingOperations();
        if (!canContinue) return;
      }
      await _refreshPendingCount();
      messageCode = pendingOperationCount == 0
          ? 'sync_complete'
          : 'submit_requires_sync';
    });
  }

  Future<bool> _flushPendingOperations() async {
    var operations = await pendingOperationStore.list();
    if (operations.isEmpty) {
      messageCode = 'nothing_to_sync';
      return true;
    }

    final marked = <PendingLearningOperation>[];
    for (final operation in operations) {
      final sent = operation.transportAttempted
          ? operation
          : operation.markTransportAttempted();
      if (!operation.transportAttempted) {
        await pendingOperationStore.put(sent);
      }
      marked.add(sent);
    }
    operations = marked;

    final byOperationId = {
      for (final operation in operations)
        operation.logicalCommandKey: operation,
    };

    try {
      final outcome = await pendingSyncClient.flush(operations);
      var hasRetryable = false;
      var hasConflict = false;
      var hasRejected = false;
      final acknowledgedIds = <String>{};

      for (final acknowledgement in outcome.acknowledgements) {
        final operation = byOperationId[acknowledgement.operationId];
        if (operation == null) {
          messageCode = 'sync_invalid_acknowledgement';
          return false;
        }
        acknowledgedIds.add(acknowledgement.operationId);
        if (acknowledgement.isApplied) {
          await pendingOperationStore.remove(operation.localId);
          _savedAnswers[operation.attemptQuestionId] = operation.value;
          if (acknowledgement.answerRevision case final revision?) {
            _revisions[operation.attemptQuestionId] = revision;
          }
          continue;
        }

        if (acknowledgement.retryable) {
          hasRetryable = true;
          continue;
        }

        // A terminal acknowledgement closes this operation ID. A corrected
        // answer, if any, must be a new operation after a server snapshot refresh.
        await pendingOperationStore.remove(operation.localId);
        hasConflict = hasConflict || acknowledgement.isConflict;
        hasRejected = hasRejected || acknowledgement.isRejected;
      }

      if (acknowledgedIds.length != operations.length) {
        messageCode = 'sync_invalid_acknowledgement';
        await _refreshPendingCount();
        return false;
      }

      await _refreshPendingCount();
      if (!hasRetryable && attempt != null) {
        await _refreshAttemptPreservingDrafts();
      }
      await _refreshPendingCount();

      if (hasRetryable) {
        messageCode = 'sync_retryable';
        return false;
      }
      if (hasConflict) {
        messageCode = 'sync_conflict';
        return false;
      }
      if (hasRejected) {
        messageCode = 'sync_rejected';
        return false;
      }
      messageCode = null;
      return true;
    } on SyncContractUnavailable {
      messageCode = 'sync_contract_pending';
      return false;
    } on LearningFailure catch (failure) {
      await _handleFailure(failure);
      return false;
    }
  }

  Future<void> _refreshAttemptPreservingDrafts() async {
    final currentAttempt = attempt;
    if (currentAttempt == null || isOffline) return;
    final desiredDrafts = Map<String, Object?>.from(_answers);
    final resumed = await gateway.resumeAttempt(currentAttempt.id);
    _acceptAttemptSnapshot(resumed);
    final validIds = resumed.questions
        .map((question) => question.attemptQuestionId)
        .toSet();
    for (final entry in desiredDrafts.entries) {
      if (validIds.contains(entry.key)) {
        _answers[entry.key] = entry.value;
      }
    }
    await attemptSnapshotCache.write(resumed, _clock());
    await _queueChangedAnswers();
  }

  void _acceptAttemptSnapshot(Attempt snapshot) {
    // Server question order is authoritative. No client sort/shuffle occurs.
    attempt = snapshot;
    _hydrateAttemptAnswers(snapshot);
  }

  void _hydrateAttemptAnswers(Attempt snapshot) {
    _answers
      ..clear()
      ..addEntries(snapshot.questions.map(
        (question) => MapEntry(
          question.attemptQuestionId,
          question.currentAnswer?.value ??
              (question.responseContract.kind == 'multiple_choice'
                  ? const <Object?>[]
                  : ''),
        ),
      ));
    _savedAnswers
      ..clear()
      ..addEntries(_answers.entries);
    _revisions
      ..clear()
      ..addEntries(snapshot.questions.map(
        (question) => MapEntry(
          question.attemptQuestionId,
          question.currentAnswer?.revision ?? 0,
        ),
      ));
  }

  bool _isEmptyAnswer(Object? value) {
    if (value == null) return true;
    if (value is String) return value.trim().isEmpty;
    if (value is List) return value.isEmpty;
    if (value is Map) return value.isEmpty;
    return false;
  }

  Future<void> _refreshPendingCount() async {
    pendingOperationCount = (await pendingOperationStore.list()).length;
  }

  Future<void> _runBusy(Future<void> Function() action) async {
    if (isBusy) return;
    isBusy = true;
    notifyListeners();
    try {
      await action();
    } finally {
      isBusy = false;
      notifyListeners();
    }
  }

  String _answerLocalId(String attemptId, String questionId) =>
      'answer:$attemptId:$questionId';
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull {
    final iterator = this.iterator;
    return iterator.moveNext() ? iterator.current : null;
  }
}
