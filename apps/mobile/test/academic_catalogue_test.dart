import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/academic_context_reset_boundary.dart';
import 'package:modrik_mobile/src/academic_track_catalogue.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';

void main() {
  test('selected opaque track and stable idempotency key reach activate unchanged', () async {
    final gateway = _AcademicMutationGateway();
    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    )..academicContext = AcademicContext.fromJson({'state': 'onboarding_required'});

    await controller.activateAcademicContext(
      '01J000000000000000000000A1',
      idempotencyKey: 'academic-activate-logical-operation-1',
    );

    expect(gateway.activateTrackIds, ['01J000000000000000000000A1']);
    expect(gateway.activateKeys, ['academic-activate-logical-operation-1']);
    expect(
      controller.academicContext?.academicTrackId,
      '01J000000000000000000000A1',
    );
  });

  test('reset keeps backend authority and clears stale context-bound caches', () async {
    final gateway = _AcademicMutationGateway();
    final lessons = MemoryDownloadedContentCache();
    final attempts = MemoryAttemptSnapshotCache();
    final lesson = Lesson.fromJson({
      'id': 'lesson-old',
      'curriculum_node_id': 'node-old',
      'content_version': 1,
      'title': {'en': 'Old lesson'},
      'practice_quiz_id': 'quiz-old',
      'blocks': const [],
    });
    final attempt = Attempt.fromJson({
      'id': 'attempt-old',
      'academic_context_id': 'context-old',
      'quiz_id': 'quiz-old',
      'status': 'in_progress',
      'blueprint_version': 1,
      'ordering_algorithm': 'backend-authoritative',
      'started_at': '2026-08-20T10:00:00Z',
      'completed_at': null,
      'archived_at': null,
      'questions': const [],
    });
    await lessons.writeLesson(lesson, DateTime.utc(2026, 8, 20));
    await attempts.write(attempt, DateTime.utc(2026, 8, 20));

    final controller = MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
      downloadedContentCache: lessons,
      attemptSnapshotCache: attempts,
    )
      ..academicContext = _activeContext('01J000000000000000000000A1')
      ..lesson = lesson
      ..attempt = attempt;

    await controller.resetAcademicContext(
      '01J000000000000000000000B2',
      idempotencyKey: 'academic-reset-logical-operation-1',
    );

    expect(gateway.resetTrackIds, ['01J000000000000000000000B2']);
    expect(gateway.resetKeys, ['academic-reset-logical-operation-1']);
    expect(
      controller.academicContext?.academicTrackId,
      '01J000000000000000000000B2',
    );
    expect(controller.attempt, isNull);
    expect(controller.lesson, isNull);
    expect(controller.section, StudentSection.dashboard);
    expect(await lessons.listLessons(), isEmpty);
    expect(await attempts.readLatest(), isNull);
  });

  testWidgets(
    'onboarding selector preserves backend order and reacts to AR EN FR locale changes',
    (tester) async {
      final gateway = _CatalogueGateway();
      final controller = MobileLearningController(
        gateway: gateway,
        config: MobileBootstrapConfig(
          apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
        ),
      )
        ..status = MobileViewStatus.ready
        ..locale = ModrikLocale.en
        ..academicContext =
            AcademicContext.fromJson({'state': 'onboarding_required'});

      await tester.pumpWidget(
        MaterialApp(
          home: AcademicContextResetBoundary(
            controller: controller,
            child: const SizedBox.shrink(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Choose your academic context'), findsOneWidget);
      expect(find.text('Second track'), findsOneWidget);
      expect(
        Directionality.of(
          tester.element(find.text('Choose your academic context')),
        ),
        TextDirection.ltr,
      );

      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      final secondTop = tester.getTopLeft(find.text('Second track').last).dy;
      final firstTop = tester.getTopLeft(find.text('First track').last).dy;
      expect(
        secondTop,
        lessThan(firstTop),
        reason: 'client must preserve Backend order',
      );
      await tester.tap(find.text('First track').last);
      await tester.pumpAndSettle();

      controller.setLocale(ModrikLocale.ar);
      await tester.pump();
      expect(find.text('اختر سياقك الأكاديمي'), findsOneWidget);
      expect(find.text('المسار الأول'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.text('اختر سياقك الأكاديمي'))),
        TextDirection.rtl,
      );

      controller.setLocale(ModrikLocale.fr);
      await tester.pump();
      expect(find.text('Choisissez votre contexte académique'), findsOneWidget);
      expect(find.text('Premier parcours'), findsOneWidget);
      expect(
        Directionality.of(
          tester.element(find.text('Choisissez votre contexte académique')),
        ),
        TextDirection.ltr,
      );
    },
  );

  testWidgets(
    'active context shows loading without hiding the learning workspace',
    (tester) async {
      final gateway = _PendingCatalogueGateway();
      final controller = _activeController(gateway);

      await tester.pumpWidget(_activeBoundary(controller));
      await tester.pump();

      expect(find.text('Learning workspace'), findsOneWidget);
      expect(find.text('Loading authorized academic tracks.'), findsOneWidget);
      expect(
        find.byKey(const ValueKey('academic-catalogue-active-state-loading')),
        findsOneWidget,
      );

      gateway.completer.complete(_catalogue());
      await tester.pumpAndSettle();
      expect(find.text('Change academic track'), findsOneWidget);
    },
  );

  testWidgets(
    'active context permission failure is explicit and retryable without hiding learning',
    (tester) async {
      final gateway = _CatalogueGateway(
        catalogueFailure: const LearningFailure(
          status: 401,
          code: 'AUTHENTICATION_REQUIRED',
          message: 'Authentication required.',
          retryable: false,
        ),
        catalogueFailuresBeforeSuccess: 1,
      );
      final controller = _activeController(gateway);

      await tester.pumpWidget(_activeBoundary(controller));
      await tester.pumpAndSettle();

      expect(find.text('Learning workspace'), findsOneWidget);
      expect(
        find.text('The current session cannot read the academic-track catalogue.'),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey('academic-catalogue-active-state-permission')),
        findsOneWidget,
      );

      await tester.tap(find.text('Retry'));
      await tester.pumpAndSettle();
      expect(gateway.catalogueCalls, 2);
      expect(find.text('Change academic track'), findsOneWidget);
    },
  );

  testWidgets(
    'active context exposes empty offline and error catalogue states',
    (tester) async {
      final emptyController = _activeController(_CatalogueGateway(tracks: const []));
      await tester.pumpWidget(
        KeyedSubtree(
          key: const ValueKey('empty-boundary'),
          child: _activeBoundary(emptyController),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Learning workspace'), findsOneWidget);
      expect(find.text('No academic tracks are currently authorized.'), findsOneWidget);
      expect(find.text('Retry'), findsOneWidget);

      final offlineController = _activeController(_CatalogueGateway())
        ..status = MobileViewStatus.offline;
      await tester.pumpWidget(
        KeyedSubtree(
          key: const ValueKey('offline-boundary'),
          child: _activeBoundary(offlineController),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Learning workspace'), findsOneWidget);
      expect(
        find.text(
          'Reconnect to load the authorized catalogue or change academic context.',
        ),
        findsOneWidget,
      );
      expect(find.text('Retry'), findsOneWidget);

      final errorController = _activeController(
        _CatalogueGateway(
          catalogueFailure: const LearningFailure(
            status: 503,
            code: 'LEARNING_REQUEST_FAILED',
            message: 'Unavailable.',
            retryable: true,
          ),
          catalogueFailuresBeforeSuccess: 10,
        ),
      );
      await tester.pumpWidget(
        KeyedSubtree(
          key: const ValueKey('error-boundary'),
          child: _activeBoundary(errorController),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Learning workspace'), findsOneWidget);
      expect(
        find.text('The academic-track catalogue could not be loaded. Retry.'),
        findsOneWidget,
      );
      expect(find.text('Retry'), findsOneWidget);
    },
  );

  testWidgets(
    'reset UX requires confirmation and explains archival consequences',
    (tester) async {
      final gateway = _CatalogueGateway();
      final controller = _activeController(gateway);

      await tester.pumpWidget(_activeBoundary(controller));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Change academic track'));
      await tester.pumpAndSettle();
      expect(find.textContaining('archives the prior context'), findsOneWidget);
      expect(find.textContaining('Pending answers and changes'), findsOneWidget);

      final confirmFinder = find.widgetWithText(FilledButton, 'Confirm reset');
      expect(tester.widget<FilledButton>(confirmFinder).onPressed, isNull);
      await tester.tap(find.text('I understand the archival reset consequences.'));
      await tester.pump();
      expect(tester.widget<FilledButton>(confirmFinder).onPressed, isNotNull);

      await tester.tap(confirmFinder);
      await tester.pumpAndSettle();
      expect(gateway.resetTrackIds, ['01J000000000000000000000B2']);
      expect(
        controller.academicContext?.academicTrackId,
        '01J000000000000000000000B2',
      );
    },
  );

  testWidgets(
    'stale or unauthorized reset rejection keeps old context and retries the same logical operation',
    (tester) async {
      final gateway = _CatalogueGateway(resetFailuresBeforeSuccess: 1);
      final controller = _activeController(gateway);

      await tester.pumpWidget(_activeBoundary(controller));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Change academic track'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('I understand the archival reset consequences.'));
      await tester.pump();

      final confirmFinder = find.widgetWithText(FilledButton, 'Confirm reset');
      await tester.tap(confirmFinder);
      await tester.pumpAndSettle();

      expect(
        controller.academicContext?.academicTrackId,
        '01J000000000000000000000A1',
      );
      expect(find.byKey(const ValueKey('academic-reset-error')), findsOneWidget);
      expect(gateway.resetTrackIds, ['01J000000000000000000000B2']);
      final firstKey = gateway.resetKeys.single;

      await tester.tap(confirmFinder);
      await tester.pumpAndSettle();
      expect(
        controller.academicContext?.academicTrackId,
        '01J000000000000000000000B2',
      );
      expect(gateway.resetTrackIds, [
        '01J000000000000000000000B2',
        '01J000000000000000000000B2',
      ]);
      expect(gateway.resetKeys, [firstKey, firstKey]);
    },
  );
}

Widget _activeBoundary(MobileLearningController controller) => MaterialApp(
      home: AcademicContextResetBoundary(
        controller: controller,
        child: const Scaffold(body: Text('Learning workspace')),
      ),
    );

MobileLearningController _activeController(LearningGateway gateway) =>
    MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    )
      ..status = MobileViewStatus.ready
      ..locale = ModrikLocale.en
      ..academicContext = _activeContext('01J000000000000000000000A1');

AcademicContext _activeContext(String trackId) => AcademicContext.fromJson({
      'state': 'active',
      'context_id': 'context-current',
      'academic_track_id': trackId,
      'year_level': 'fixture-year',
      'activated_at': '2026-08-20T10:00:00Z',
    });

List<AcademicTrack> _catalogue() => [
      AcademicTrack(
        id: '01J000000000000000000000B2',
        labels: {
          ModrikLocale.ar: 'المسار الثاني',
          ModrikLocale.en: 'Second track',
          ModrikLocale.fr: 'Deuxième parcours',
        },
      ),
      AcademicTrack(
        id: '01J000000000000000000000A1',
        labels: {
          ModrikLocale.ar: 'المسار الأول',
          ModrikLocale.en: 'First track',
          ModrikLocale.fr: 'Premier parcours',
        },
      ),
    ];

class _AcademicMutationGateway implements LearningGateway {
  _AcademicMutationGateway({this.resetFailuresBeforeSuccess = 0});

  final int resetFailuresBeforeSuccess;
  final List<String> activateTrackIds = [];
  final List<String> activateKeys = [];
  final List<String> resetTrackIds = [];
  final List<String> resetKeys = [];

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async {
    activateTrackIds.add(academicTrackId);
    activateKeys.add(idempotencyKey);
    return _activeContext(academicTrackId);
  }

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async {
    resetTrackIds.add(academicTrackId);
    resetKeys.add(idempotencyKey);
    if (resetTrackIds.length <= resetFailuresBeforeSuccess) {
      throw const LearningFailure(
        status: 404,
        code: 'RESOURCE_NOT_FOUND',
        message: 'Academic track is no longer authorized.',
        retryable: false,
      );
    }
    return _activeContext(academicTrackId);
  }

  @override
  Future<List<ProgressSnapshot>> progress() async => const [];

  @override
  Future<AcademicContext> academicContext() => throw UnimplementedError();

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
  Future<Session> session() => throw UnimplementedError();

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) =>
      throw UnimplementedError();

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) =>
      throw UnimplementedError();
}

class _CatalogueGateway extends _AcademicMutationGateway
    implements AcademicTrackCatalogueGateway {
  _CatalogueGateway({
    List<AcademicTrack>? tracks,
    this.catalogueFailure,
    this.catalogueFailuresBeforeSuccess = 0,
    int resetFailuresBeforeSuccess = 0,
  })  : _tracks = tracks ?? _catalogue(),
        super(resetFailuresBeforeSuccess: resetFailuresBeforeSuccess);

  final List<AcademicTrack> _tracks;
  final LearningFailure? catalogueFailure;
  final int catalogueFailuresBeforeSuccess;
  int catalogueCalls = 0;

  @override
  Future<List<AcademicTrack>> academicTracks() async {
    catalogueCalls += 1;
    if (catalogueFailure != null &&
        catalogueCalls <= catalogueFailuresBeforeSuccess) {
      throw catalogueFailure!;
    }
    return _tracks;
  }
}

class _PendingCatalogueGateway extends _AcademicMutationGateway
    implements AcademicTrackCatalogueGateway {
  final Completer<List<AcademicTrack>> completer =
      Completer<List<AcademicTrack>>();

  @override
  Future<List<AcademicTrack>> academicTracks() => completer.future;
}
