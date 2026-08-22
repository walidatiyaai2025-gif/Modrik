import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'learning_gateway.dart';
import 'models.dart';
import 'runtime_diagnostic_transport.dart';
import 'runtime_diagnostics.dart';

class StudentNotification {
  const StudentNotification({
    required this.id,
    required this.kind,
    required this.title,
    required this.body,
    required this.action,
    required this.occurredAt,
    required this.readAt,
    required this.isRead,
  });

  factory StudentNotification.fromJson(Map<String, dynamic> json) {
    return StudentNotification(
      id: json['id'] as String,
      kind: json['kind'] as String,
      title: localizedTextFromJson(json['title']),
      body: localizedTextFromJson(json['body']),
      action: json['action'] as String?,
      occurredAt: json['occurred_at'] as String,
      readAt: json['read_at'] as String?,
      isRead: json['is_read'] as bool? ?? false,
    );
  }

  final String id;
  final String kind;
  final LocalizedText title;
  final LocalizedText body;
  final String? action;
  final String occurredAt;
  final String? readAt;
  final bool isRead;
}

class StudentNotificationInbox {
  const StudentNotificationInbox({
    required this.items,
    required this.unreadCount,
  });

  factory StudentNotificationInbox.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    return StudentNotificationInbox(
      items: List<StudentNotification>.unmodifiable(
        rawItems is List
            ? rawItems.whereType<Map>().map(
                  (item) => StudentNotification.fromJson(
                    Map<String, dynamic>.from(item),
                  ),
                )
            : const <StudentNotification>[],
      ),
      unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
    );
  }

  final List<StudentNotification> items;
  final int unreadCount;
}

class StudentNotificationReadAllResult {
  const StudentNotificationReadAllResult({
    required this.updatedCount,
    required this.unreadCount,
  });

  factory StudentNotificationReadAllResult.fromJson(
    Map<String, dynamic> json,
  ) {
    return StudentNotificationReadAllResult(
      updatedCount: (json['updated_count'] as num?)?.toInt() ?? 0,
      unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
    );
  }

  final int updatedCount;
  final int unreadCount;
}

abstract interface class StudentNotificationGateway {
  bool get isConfigured;
  Future<StudentNotificationInbox> inbox();
  Future<StudentNotification> markRead(String notificationId);
  Future<StudentNotificationReadAllResult> markAllRead();
}

class HttpStudentNotificationGateway implements StudentNotificationGateway {
  HttpStudentNotificationGateway({
    required this.baseUrl,
    String? bearerToken,
    this.bearerTokenProvider,
    this.onAuthenticationRejected,
    this.diagnostics,
    HttpClient? client,
  })  : _staticBearerToken = bearerToken,
        _client = client ?? HttpClient();

  final Uri baseUrl;
  final String? _staticBearerToken;
  final String? Function()? bearerTokenProvider;
  final void Function()? onAuthenticationRejected;
  final RuntimeDiagnostics? diagnostics;
  final HttpClient _client;

  String? get _bearerToken =>
      bearerTokenProvider?.call() ?? _staticBearerToken;

  @override
  bool get isConfigured => true;

  @override
  Future<StudentNotificationInbox> inbox() async =>
      StudentNotificationInbox.fromJson(await _requestMap('notifications'));

  @override
  Future<StudentNotification> markRead(String notificationId) async =>
      StudentNotification.fromJson(
        await _requestMap(
          'notifications/$notificationId/read',
          method: 'PUT',
        ),
      );

  @override
  Future<StudentNotificationReadAllResult> markAllRead() async =>
      StudentNotificationReadAllResult.fromJson(
        await _requestMap('notifications/read-all', method: 'PUT'),
      );

  Future<Map<String, dynamic>> _requestMap(
    String path, {
    String method = 'GET',
  }) async {
    final diagnosticAttempt = RuntimeDiagnosticTransportAttempt.start(
      diagnostics,
      diagnosticOperationName('notifications', method, path),
    );
    try {
      final request = await _client.openUrl(method, baseUrl.resolve(path));
      diagnosticAttempt.attach(request);
      request.headers.set(
        HttpHeaders.acceptHeader,
        'application/json, application/problem+json',
      );
      final token = _bearerToken;
      if (token == null || token.isEmpty) {
        throw const LearningFailure(
          status: 401,
          code: 'AUTHENTICATION_REQUIRED',
          message: 'A valid MODRIK session is required.',
          retryable: false,
        );
      }
      request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
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
          code: problem['code'] as String? ?? 'NOTIFICATION_REQUEST_FAILED',
          message: problem['detail'] as String? ?? 'The notification request failed.',
          retryable: problem['retryable'] as bool? ?? response.statusCode >= 500,
        );
        if (failure.status == 401) {
          onAuthenticationRejected?.call();
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
          stableCode: 'MOBILE_NOTIFICATION_INVALID_RESPONSE',
        );
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_NOTIFICATION_INVALID_RESPONSE',
          message: 'The notification service returned an invalid response.',
          retryable: false,
        );
      }
      final envelope = Map<String, dynamic>.from(payload);
      final data = envelope['data'];
      if (data is! Map) {
        diagnosticAttempt.invalidResponse(
          stableCode: 'MOBILE_NOTIFICATION_INVALID_RESPONSE',
        );
        throw const LearningFailure(
          status: 0,
          code: 'MOBILE_NOTIFICATION_INVALID_RESPONSE',
          message: 'The notification service returned an invalid response.',
          retryable: false,
        );
      }
      diagnosticAttempt.success(status: response.statusCode);
      return Map<String, dynamic>.from(data);
    } on LearningFailure catch (failure) {
      if (failure.status > 0) {
        diagnosticAttempt.backendFailure(
          status: failure.status,
          stableCode: failure.code,
          retryable: failure.retryable,
        );
      } else {
        diagnosticAttempt.invalidResponse(stableCode: failure.code);
      }
      rethrow;
    } on TimeoutException {
      diagnosticAttempt.transportFailure(
        stableCode: 'MOBILE_NOTIFICATION_TIMEOUT',
      );
      throw const LearningFailure(
        status: 0,
        code: 'MOBILE_NOTIFICATION_TIMEOUT',
        message: 'The notification request timed out.',
        retryable: true,
      );
    } on SocketException catch (error) {
      diagnosticAttempt.offline(stableCode: 'MOBILE_NOTIFICATION_OFFLINE');
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NOTIFICATION_OFFLINE',
        message: error.message,
        retryable: true,
      );
    } on HttpException catch (error) {
      diagnosticAttempt.transportFailure(
        stableCode: 'MOBILE_NOTIFICATION_NETWORK_ERROR',
      );
      throw LearningFailure(
        status: 0,
        code: 'MOBILE_NOTIFICATION_NETWORK_ERROR',
        message: error.message,
        retryable: true,
      );
    } on FormatException {
      diagnosticAttempt.invalidResponse(
        stableCode: 'MOBILE_NOTIFICATION_INVALID_RESPONSE',
      );
      throw const LearningFailure(
        status: 0,
        code: 'MOBILE_NOTIFICATION_INVALID_RESPONSE',
        message: 'The notification service returned malformed JSON.',
        retryable: false,
      );
    }
  }
}

class UnconfiguredStudentNotificationGateway
    implements StudentNotificationGateway {
  const UnconfiguredStudentNotificationGateway();

  @override
  bool get isConfigured => false;

  LearningFailure get _failure => const LearningFailure(
        status: 403,
        code: 'MOBILE_API_NOT_CONFIGURED',
        message: 'The mobile API endpoint is not configured for this build.',
        retryable: false,
      );

  @override
  Future<StudentNotificationInbox> inbox() => Future.error(_failure);

  @override
  Future<StudentNotification> markRead(String notificationId) =>
      Future.error(_failure);

  @override
  Future<StudentNotificationReadAllResult> markAllRead() =>
      Future.error(_failure);
}
