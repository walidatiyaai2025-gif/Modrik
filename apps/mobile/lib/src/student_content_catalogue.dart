import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'learning_gateway.dart';
import 'models.dart';
import 'runtime_diagnostic_transport.dart';

class CatalogueAssessment {
  const CatalogueAssessment({
    required this.id,
    required this.kind,
    required this.blueprintVersion,
    required this.title,
  });

  factory CatalogueAssessment.fromJson(Map<String, dynamic> json) =>
      CatalogueAssessment(
        id: json['id'] as String,
        kind: json['kind'] as String,
        blueprintVersion: (json['blueprint_version'] as num).toInt(),
        title: localizedTextFromJson(json['title']),
      );

  final String id;
  final String kind;
  final int blueprintVersion;
  final LocalizedText title;
}

class CatalogueLesson {
  const CatalogueLesson({
    required this.id,
    required this.slug,
    required this.contentVersion,
    required this.title,
    required this.publishedAt,
  });

  factory CatalogueLesson.fromJson(Map<String, dynamic> json) => CatalogueLesson(
        id: json['id'] as String,
        slug: json['slug'] as String? ?? '',
        contentVersion: (json['content_version'] as num).toInt(),
        title: localizedTextFromJson(json['title']),
        publishedAt: json['published_at'] as String?,
      );

  final String id;
  final String slug;
  final int contentVersion;
  final LocalizedText title;
  final String? publishedAt;
}

class CatalogueNode {
  const CatalogueNode({
    required this.id,
    required this.reference,
    required this.type,
    required this.title,
    required this.lessons,
    required this.assessments,
    required this.children,
  });

  factory CatalogueNode.fromJson(Map<String, dynamic> json) => CatalogueNode(
        id: json['id'] as String,
        reference: json['reference'] as String,
        type: json['type'] as String,
        title: localizedTextFromJson(json['title']),
        lessons: List<CatalogueLesson>.unmodifiable(
          (json['lessons'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map(
                (item) => CatalogueLesson.fromJson(
                  Map<String, dynamic>.from(item),
                ),
              ),
        ),
        assessments: List<CatalogueAssessment>.unmodifiable(
          (json['assessments'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map(
                (item) => CatalogueAssessment.fromJson(
                  Map<String, dynamic>.from(item),
                ),
              ),
        ),
        children: List<CatalogueNode>.unmodifiable(
          (json['children'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .map(
                (item) => CatalogueNode.fromJson(
                  Map<String, dynamic>.from(item),
                ),
              ),
        ),
      );

  final String id;
  final String reference;
  final String type;
  final LocalizedText title;
  final List<CatalogueLesson> lessons;
  final List<CatalogueAssessment> assessments;
  final List<CatalogueNode> children;
}

class CatalogueContext {
  const CatalogueContext({
    required this.contextId,
    required this.academicTrackId,
    required this.trackReference,
    required this.yearLevel,
    required this.trackTitle,
  });

  factory CatalogueContext.fromJson(Map<String, dynamic> json) =>
      CatalogueContext(
        contextId: json['context_id'] as String,
        academicTrackId: json['academic_track_id'] as String,
        trackReference: json['track_reference'] as String,
        yearLevel: json['year_level'] as String,
        trackTitle: localizedTextFromJson(json['track_title']),
      );

  final String contextId;
  final String academicTrackId;
  final String trackReference;
  final String yearLevel;
  final LocalizedText trackTitle;
}

class CatalogueCounts {
  const CatalogueCounts({
    required this.subjects,
    required this.lessons,
    required this.assessments,
  });

  factory CatalogueCounts.fromJson(Map<String, dynamic> json) =>
      CatalogueCounts(
        subjects: (json['subjects'] as num? ?? 0).toInt(),
        lessons: (json['lessons'] as num? ?? 0).toInt(),
        assessments: (json['assessments'] as num? ?? 0).toInt(),
      );

  final int subjects;
  final int lessons;
  final int assessments;
}

class ContentCatalogue {
  const ContentCatalogue({
    required this.state,
    required this.subjects,
    required this.counts,
    this.context,
  });

  factory ContentCatalogue.fromJson(Map<String, dynamic> json) {
    final state = json['state'] as String? ?? 'onboarding_required';
    final rawContext = json['context'];
    return ContentCatalogue(
      state: state,
      context: state == 'active' && rawContext is Map
          ? CatalogueContext.fromJson(Map<String, dynamic>.from(rawContext))
          : null,
      subjects: List<CatalogueNode>.unmodifiable(
        (json['subjects'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map(
              (item) => CatalogueNode.fromJson(
                Map<String, dynamic>.from(item),
              ),
            ),
      ),
      counts: CatalogueCounts.fromJson(
        json['counts'] is Map
            ? Map<String, dynamic>.from(json['counts'] as Map)
            : const <String, dynamic>{},
      ),
    );
  }

  final String state;
  final CatalogueContext? context;
  final List<CatalogueNode> subjects;
  final CatalogueCounts counts;

  bool get isActive => state == 'active';
}

/// Converts the published Lesson contract into the existing mobile Lesson
/// model without fabricating a practice assessment. A missing practice quiz is
/// represented as an empty compatibility value so older offline snapshots and
/// controller code remain readable while catalogue-owned assessment IDs remain
/// authoritative for new practice/mock-exam launches.
Lesson publishedLessonFromJson(Map<String, dynamic> json) {
  final normalized = Map<String, dynamic>.from(json);
  normalized['practice_quiz_id'] ??= '';
  return Lesson.fromJson(normalized);
}

extension StudentContentCatalogueHttp on HttpLearningGateway {
  Future<ContentCatalogue> contentCatalogue({String? subjectReference}) async {
    final uri = baseUrl.resolve('content-catalogue').replace(
      queryParameters: subjectReference == null || subjectReference.isEmpty
          ? null
          : {'subject_reference': subjectReference},
    );
    final data = await _studentCatalogueGet(
      uri,
      diagnosticOperationName('learning', 'GET', 'content-catalogue'),
    );
    return ContentCatalogue.fromJson(data);
  }

  Future<Lesson> publishedLesson(String lessonId) async {
    final uri = baseUrl.resolve('lessons/${Uri.encodeComponent(lessonId)}');
    final data = await _studentCatalogueGet(
      uri,
      diagnosticOperationName('learning', 'GET', 'lesson'),
    );
    return publishedLessonFromJson(data);
  }

  Future<Map<String, dynamic>> _studentCatalogueGet(
    Uri uri,
    String diagnosticOperation,
  ) async {
    final diagnosticAttempt = RuntimeDiagnosticTransportAttempt.start(
      diagnostics,
      diagnosticOperation,
    );
    final client = HttpClient();
    try {
      final request = await client.getUrl(uri);
      diagnosticAttempt.attach(request);
      request.headers.set(
        HttpHeaders.acceptHeader,
        'application/json, application/problem+json',
      );
      final token = bearerTokenProvider?.call();
      if (token != null && token.isNotEmpty) {
        request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
      }

      final response = await request.close();
      diagnosticAttempt.acceptResponse(response);
      final text = await response.transform(utf8.decoder).join();
      final payload = text.isEmpty ? <String, dynamic>{} : jsonDecode(text);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final problem = payload is Map
            ? Map<String, dynamic>.from(payload)
            : <String, dynamic>{};
        final failure = LearningFailure(
          status: response.statusCode,
          code: problem['code'] as String? ?? 'LEARNING_REQUEST_FAILED',
          message: problem['detail'] as String? ??
              'The learning request failed.',
          retryable: problem['retryable'] as bool? ??
              response.statusCode >= 500,
        );
        if (failure.status == 401 &&
            failure.code == 'AUTHENTICATION_REQUIRED') {
          onAuthenticationRejected?.call();
        } else if (failure.status == 403 &&
            failure.code == 'EMAIL_VERIFICATION_REQUIRED') {
          onEmailVerificationRequired?.call();
        }
        diagnosticAttempt.backendFailure(
          status: failure.status,
          stableCode: failure.code,
          retryable: failure.retryable,
        );
        throw failure;
      }
      if (payload is! Map) {
        diagnosticAttempt.invalidResponse(
          stableCode: 'MOBILE_INVALID_RESPONSE',
        );
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_INVALID_RESPONSE',
          message: 'The learning service returned an invalid response.',
          retryable: false,
        );
      }
      final envelope = Map<String, dynamic>.from(payload);
      final data = envelope['data'];
      if (data is! Map) {
        diagnosticAttempt.invalidResponse(
          stableCode: 'MOBILE_INVALID_RESPONSE',
        );
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_INVALID_RESPONSE',
          message: 'The learning service returned an invalid response.',
          retryable: false,
        );
      }
      diagnosticAttempt.success(status: response.statusCode);
      return Map<String, dynamic>.from(data);
    } on LearningFailure {
      rethrow;
    } on TimeoutException {
      diagnosticAttempt.transportFailure(
        stableCode: 'MOBILE_NETWORK_TIMEOUT',
      );
      throw const LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_TIMEOUT',
        message: 'The learning request timed out.',
        retryable: true,
      );
    } on SocketException catch (error) {
      diagnosticAttempt.offline();
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_OFFLINE',
        message: error.message,
        retryable: true,
      );
    } on HttpException catch (error) {
      diagnosticAttempt.transportFailure();
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NETWORK_ERROR',
        message: error.message,
        retryable: true,
      );
    } on FormatException {
      diagnosticAttempt.invalidResponse(
        stableCode: 'MOBILE_INVALID_RESPONSE',
      );
      throw const LearningFailure(
        status: 0,
        code: 'MOBILE_INVALID_RESPONSE',
        message: 'The learning service returned malformed JSON.',
        retryable: false,
      );
    } finally {
      client.close(force: true);
    }
  }
}
