import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/academic_track_catalogue.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';

void main() {
  test('mobile catalogue forwards secure bearer and preserves backend track order and labels', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      expect(request.method, 'GET');
      expect(request.uri.path, '/api/v1/academic-tracks');
      expect(
        request.headers.value(HttpHeaders.authorizationHeader),
        'Bearer secure-production-session',
      );
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {
          'tracks': [
            {
              'id': '01J000000000000000000000B2',
              'labels': {
                'ar': 'المسار الثاني',
                'en': 'Second track',
                'fr': 'Deuxième parcours',
              },
            },
            {
              'id': '01J000000000000000000000A1',
              'labels': {
                'ar': 'المسار الأول',
                'en': 'First track',
                'fr': 'Premier parcours',
              },
            },
          ],
        },
        'meta': {'request_id': 'req-mobile-catalogue'},
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        bearerToken: 'secure-production-session',
      );
      final tracks = await gateway.academicTracks();

      expect(
        tracks.map((track) => track.id),
        ['01J000000000000000000000B2', '01J000000000000000000000A1'],
      );
      expect(tracks.first.label(ModrikLocale.ar), 'المسار الثاني');
      expect(tracks.first.label(ModrikLocale.en), 'Second track');
      expect(tracks.first.label(ModrikLocale.fr), 'Deuxième parcours');
    } finally {
      await server.close(force: true);
    }
  });

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
    expect(controller.academicContext?.academicTrackId, '01J000000000000000000000A1');
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
    expect(controller.academicContext?.academicTrackId, '01J000000000000000000000B2');
    expect(controller.attempt, isNull);
    expect(controller.lesson, isNull);
    expect(controller.section, StudentSection.dashboard);
    expect(await lessons.listLessons(), isEmpty);
    expect(await attempts.readLatest(), isNull);
  });
}

AcademicContext _activeContext(String trackId) => AcademicContext.fromJson({
      'state': 'active',
      'context_id': 'context-current',
      'academic_track_id': trackId,
      'year_level': 'fixture-year',
      'activated_at': '2026-08-20T10:00:00Z',
    });

class _AcademicMutationGateway implements LearningGateway {
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
