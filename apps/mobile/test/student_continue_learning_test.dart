import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';

void main() {
  testWidgets('Flutter dashboard resumes the exact cached attempt through Continue Learning', (tester) async {
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

    controller
      ..status = MobileViewStatus.offline
      ..section = StudentSection.dashboard
      ..academicContext = AcademicContext.fromJson({
        'state': 'active',
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'year_level': 'Year 7',
        'activated_at': '2026-08-20T10:00:00Z',
      })
      ..attempt = Attempt.fromJson({
        'id': 'attempt-continue-1',
        'academic_context_id': 'context-1',
        'quiz_id': 'quiz-1',
        'status': 'in_progress',
        'blueprint_version': 1,
        'ordering_algorithm': 'backend-order',
        'started_at': '2026-09-19T18:00:00Z',
        'completed_at': null,
        'archived_at': null,
        'questions': [],
      });

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(find.text('Continue Learning'), findsOneWidget);
    expect(find.text('Resume this attempt'), findsOneWidget);
    expect(find.byIcon(Icons.play_circle_outline), findsOneWidget);

    await tester.tap(find.text('Resume this attempt'));
    await tester.pump();

    expect(controller.section, StudentSection.practice);
    expect(controller.messageCode, 'resume_cached_attempt');
  });

  testWidgets('Flutter dashboard does not invent Continue Learning without an attempt', (tester) async {
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

    controller
      ..status = MobileViewStatus.ready
      ..section = StudentSection.dashboard
      ..academicContext = AcademicContext.fromJson({
        'state': 'active',
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'year_level': 'Year 7',
        'activated_at': '2026-08-20T10:00:00Z',
      });

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(find.text('Continue Learning'), findsNothing);
    expect(find.text('Resume this attempt'), findsNothing);
  });

  test('Flutter online initialization restores Continue Learning from Backend authority', () async {
    final cache = MemoryAttemptSnapshotCache();
    await cache.write(
      _attempt('attempt-stale-local'),
      DateTime.utc(2026, 9, 20, 8),
    );
    final gateway = _CurrentAttemptGateway(_attempt('attempt-server-current'));
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      attemptSnapshotCache: cache,
      clock: () => DateTime.utc(2026, 9, 21, 12),
    );

    await controller.initialize();

    expect(gateway.currentAttemptCalls, 1);
    expect(controller.status, MobileViewStatus.ready);
    expect(controller.attempt?.id, 'attempt-server-current');
    expect((await cache.readLatest())?.attempt.id, 'attempt-server-current');
  });

  test('Flutter online null current attempt clears stale local Continue Learning cache', () async {
    final cache = MemoryAttemptSnapshotCache();
    await cache.write(
      _attempt('attempt-stale-local'),
      DateTime.utc(2026, 9, 20, 8),
    );
    final gateway = _CurrentAttemptGateway(null);
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      attemptSnapshotCache: cache,
    );

    await controller.initialize();

    expect(gateway.currentAttemptCalls, 1);
    expect(controller.status, MobileViewStatus.ready);
    expect(controller.attempt, isNull);
    expect(await cache.readLatest(), isNull);
  });

}


Attempt _attempt(String id) => Attempt.fromJson({
      'id': id,
      'academic_context_id': 'context-1',
      'quiz_id': 'quiz-1',
      'status': 'in_progress',
      'blueprint_version': 1,
      'ordering_algorithm': 'backend-order',
      'started_at': '2026-09-21T10:00:00Z',
      'completed_at': null,
      'archived_at': null,
      'questions': const [],
    });

class _CurrentAttemptGateway implements LearningGateway, CurrentAttemptGateway {
  _CurrentAttemptGateway(this.current);

  final Attempt? current;
  int currentAttemptCalls = 0;

  @override
  Future<Attempt?> currentAttempt() async {
    currentAttemptCalls += 1;
    return current;
  }

  @override
  Future<Session> session() async => Session.fromJson({
        'user_id': 'student-1',
        'locale': 'en',
        'roles': const ['student'],
      });

  @override
  Future<AcademicContext> academicContext() async => AcademicContext.fromJson({
        'state': 'active',
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'year_level': 'Year 7',
        'activated_at': '2026-09-21T09:00:00Z',
      });

  @override
  Future<List<ProgressSnapshot>> progress() async => const [];

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) =>
      throw UnimplementedError();

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) =>
      throw UnimplementedError();

  @override
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required Object? value,
    required String idempotencyKey,
  }) =>
      throw UnimplementedError();

  @override
  Future<Lesson> lesson(String lessonId) => throw UnimplementedError();

  @override
  Future<Attempt> resumeAttempt(String attemptId) => throw UnimplementedError();

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) =>
      throw UnimplementedError();

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) =>
      throw UnimplementedError();
}
