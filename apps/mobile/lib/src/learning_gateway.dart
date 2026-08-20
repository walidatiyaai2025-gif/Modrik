import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'models.dart';

class MobileBootstrapConfig {
  const MobileBootstrapConfig({
    required this.apiBaseUrl,
    this.bearerToken,
    this.fixtureMode = false,
    this.initialLessonId,
    this.academicTrackId,
  });

  factory MobileBootstrapConfig.fromEnvironment() {
    const rawBase = String.fromEnvironment('MODRIK_API_BASE_URL');
    const fixtureMode = bool.fromEnvironment('MODRIK_FIXTURE_MODE');
    const fixtureToken = String.fromEnvironment('MODRIK_FIXTURE_BEARER_TOKEN');
    const legacyFixtureToken = String.fromEnvironment('MODRIK_API_BEARER_TOKEN');
    const lessonId = String.fromEnvironment('MODRIK_INITIAL_LESSON_ID');
    const trackId = String.fromEnvironment('MODRIK_ACADEMIC_TRACK_ID');
    final selectedFixtureToken = fixtureToken.isNotEmpty
        ? fixtureToken
        : legacyFixtureToken;
    return MobileBootstrapConfig(
      apiBaseUrl: rawBase.isEmpty
          ? null
          : Uri.parse(rawBase.endsWith('/') ? rawBase : '$rawBase/'),
      bearerToken: fixtureMode && selectedFixtureToken.isNotEmpty
          ? selectedFixtureToken
          : null,
      fixtureMode: fixtureMode,
      initialLessonId: lessonId.isEmpty ? null : lessonId,
      academicTrackId: trackId.isEmpty ? null : trackId,
    );
  }

  final Uri? apiBaseUrl;

  /// Synthetic fixture credential only. Production startup never consumes a
  /// compile-time bearer token; it is obtained from the backend Auth lifecycle
  /// and stored through the platform secure-session boundary.
  final String? bearerToken;
  final bool fixtureMode;
  final String? initialLessonId;
  final String? academicTrackId;

  bool get isConfigured => apiBaseUrl != null;
  bool get hasFixtureCredential =>
      fixtureMode && bearerToken != null && bearerToken!.isNotEmpty;
}

class LearningFailure implements Exception {
  const LearningFailure({
    required this.status,
    required this.code,
    required this.message,
    required this.retryable,
  });

  final int status;
  final String code;
  final String message;
  final bool retryable;

  bool get isPermission => status == 401 || status == 403;
  bool get isNetwork => status == 0;

  @override
  String toString() => 'LearningFailure($status, $code, $message)';
}

class SavedAnswer {
  SavedAnswer({
    required this.revision,
    required Object? value,
    required this.answeredAt,
  }) : value = freezeJsonValue(value);

  factory SavedAnswer.fromJson(Map<String, dynamic> json) => SavedAnswer(
        revision: (json['revision'] as num).toInt(),
        value: json['value'],
        answeredAt: json['answered_at'] as String,
      );

  final int revision;
  final Object? value;
  final String answeredAt;
}

abstract interface class LearningGateway {
  Future<Session> session();
  Future<AcademicContext> academicContext();
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  );
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  );
  Future<Lesson> lesson(String lessonId);
  Future<List<ProgressSnapshot>> progress();
  Future<Attempt> startAttempt(String quizId, String idempotencyKey);
  Future<Attempt> resumeAttempt(String attemptId);
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required Object? value,
    required String idempotencyKey,
  });
  Future<AttemptResult> submit(String attemptId, String idempotencyKey);
}

String newLogicalCommandKey() {
  final random = Random.secure();
  final bytes = List<int>.generate(
    20,
    (_) => random.nextInt(256),
    growable: false,
  );
  final encoded = bytes
      .map((value) => value.toRadixString(16).padLeft(2, '0'))
      .join();
  return 'm-$encoded';
}

class HttpLearningGateway implements LearningGateway {
  HttpLearningGateway({
    required this.baseUrl,
    String? bearerToken,
    String? Function()? bearerTokenProvider,
    this.onAuthenticationRejected,
    this.onEmailVerificationRequired,
    HttpClient? client,
  })  : _staticBearerToken = bearerToken,
        _bearerTokenProvider = bearerTokenProvider,
        _client = client ?? HttpClient();

  final Uri baseUrl;
  final String? _staticBearerToken;
  final String? Function()? _bearerTokenProvider;
  final void Function()? onAuthenticationRejected;
  final void Function()? onEmailVerificationRequired;
  final HttpClient _client;

  String? get _bearerToken =>
      _bearerTokenProvider?.call() ?? _staticBearerToken;

  @override
  Future<Session> session() async =>
      Session.fromJson(await _requestMap('session'));

  @override
  Future<AcademicContext> academicContext() async => AcademicContext.fromJson(
        await _requestMap('academic-context'),
      );

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async {
    return AcademicContext.fromJson(
      await _requestMap(
        'academic-context/activate',
        method: 'POST',
        body: {'academic_track_id': academicTrackId},
        idempotencyKey: idempotencyKey,
      ),
    );
  }

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async {
    return AcademicContext.fromJson(
      await _requestMap(
        'academic-context/reset',
        method: 'POST',
        body: {'academic_track_id': academicTrackId},
        idempotencyKey: idempotencyKey,
      ),
    );
  }

  @override
  Future<Lesson> lesson(String lessonId) async => Lesson.fromJson(
        await _requestMap('lessons/$lessonId'),
      );

  @override
  Future<List<ProgressSnapshot>> progress() async {
    final data = await _requestData('progress');
    if (data is! List) return const [];
    return List<ProgressSnapshot>.unmodifiable(
      data.whereType<Map>().map(
            (item) => ProgressSnapshot.fromJson(
              Map<String, dynamic>.from(item),
            ),
          ),
    );
  }

  @override
  Future<Attempt> startAttempt(
    String quizId,
    String idempotencyKey,
  ) async =>
      Attempt.fromJson(
        await _requestMap(
          'attempts',
          method: 'POST',
          body: {'quiz_id': quizId},
          idempotencyKey: idempotencyKey,
        ),
      );

  @override
  Future<Attempt> resumeAttempt(String attemptId) async => Attempt.fromJson(
        await _requestMap('attempts/$attemptId'),
      );

  @override
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required Object? value,
    required String idempotencyKey,
  }) async {
    return SavedAnswer.fromJson(
      await _requestMap(
        'attempts/$attemptId/answers/$attemptQuestionId',
        method: 'PUT',
        body: {
          'expected_revision': expectedRevision,
          'value': freezeJsonValue(value),
        },
        idempotencyKey: idempotencyKey,
      ),
    );
  }

  @override
  Future<AttemptResult> submit(
    String attemptId,
    String idempotencyKey,
  ) async =>
      AttemptResult.fromJson(
        await _requestMap(
          'attempts/$attemptId/submit',
          method: 'POST',
          idempotencyKey: idempotencyKey,
        ),
      );

  Future<Map<String, dynamic>> _requestMap(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
    String? idempotencyKey,
  }) async {
    final data = await _requestData(
      path,
      method: method,
      body: body,
      idempotencyKey: idempotencyKey,
    );
    if (data is! Map) {
      throw const LearningFailure(
        status: 0,
        code: 'MOBILE_INVALID_RESPONSE',
        message: 'The learning service returned an invalid response.',
        retryable: false,
      );
    }
    return Map<String, dynamic>.from(data);
  }

  Future<dynamic> _requestData(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
    String? idempotencyKey,
  }) async {
    try {
      final request = await _client.openUrl(method, baseUrl.resolve(path));
      request.headers.set(
        HttpHeaders.acceptHeader,
        'application/json, application/problem+json',
      );
      final token = _bearerToken;
      if (token != null && token.isNotEmpty) {
        request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
      }
      if (idempotencyKey != null) {
        request.headers.set('Idempotency-Key', idempotencyKey);
      }
      if (body != null) {
        request.headers.contentType = ContentType.json;
        request.write(jsonEncode(body));
      }
      final response = await request.close();
      final text = await response.transform(utf8.decoder).join();
      final payload = text.isEmpty ? <String, dynamic>{} : jsonDecode(text);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final problem = payload is Map
            ? Map<String, dynamic>.from(payload)
            : <String, dynamic>{};
        final failure = LearningFailure(
          status: response.statusCode,
          code: problem['code'] as String? ?? 'LEARNING_REQUEST_FAILED',
          message: problem['detail'] as String? ?? 'The learning request failed.',
          retryable: problem['retryable'] as bool? ?? response.statusCode >= 500,
        );
        if (failure.status == 401 && failure.code == 'AUTHENTICATION_REQUIRED') {
          onAuthenticationRejected?.call();
        } else if (failure.status == 403 &&
            failure.code == 'EMAIL_VERIFICATION_REQUIRED') {
          onEmailVerificationRequired?.call();
        }
        throw failure;
      }
      if (payload is! Map) {
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_INVALID_RESPONSE',
          message: 'The learning service returned an invalid response.',
          retryable: false,
        );
      }
      final envelope = Map<String, dynamic>.from(payload);
      return envelope['data'];
    } on LearningFailure {
      rethrow;
    } on SocketException catch (error) {
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_OFFLINE',
        message: error.message,
        retryable: true,
      );
    } on HttpException catch (error) {
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_ERROR',
        message: error.message,
        retryable: true,
      );
    } on FormatException {
      throw const LearningFailure(
        status: 0,
        code: 'MOBILE_INVALID_RESPONSE',
        message: 'The learning service returned malformed JSON.',
        retryable: false,
      );
    }
  }
}

class UnconfiguredLearningGateway implements LearningGateway {
  const UnconfiguredLearningGateway();

  LearningFailure get _failure => const LearningFailure(
        status: 403,
        code: 'MOBILE_API_NOT_CONFIGURED',
        message: 'The mobile API endpoint is not configured for this build.',
        retryable: false,
      );

  @override
  Future<AcademicContext> academicContext() => Future.error(_failure);

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) =>
      Future.error(_failure);

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) =>
      Future.error(_failure);

  @override
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required Object? value,
    required String idempotencyKey,
  }) =>
      Future.error(_failure);

  @override
  Future<Lesson> lesson(String lessonId) => Future.error(_failure);

  @override
  Future<List<ProgressSnapshot>> progress() => Future.error(_failure);

  @override
  Future<Attempt> resumeAttempt(String attemptId) => Future.error(_failure);

  @override
  Future<Session> session() => Future.error(_failure);

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) =>
      Future.error(_failure);

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) =>
      Future.error(_failure);
}
