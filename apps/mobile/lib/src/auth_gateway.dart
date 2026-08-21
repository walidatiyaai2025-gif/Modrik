import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'auth_models.dart';
import 'models.dart';
import 'runtime_diagnostic_transport.dart';
import 'runtime_diagnostics.dart';

class AuthFailure implements Exception {
  const AuthFailure({
    required this.status,
    required this.code,
    required this.message,
    required this.retryable,
  });

  final int status;
  final String code;
  final String message;
  final bool retryable;

  bool get isNetwork => status == 0;
  bool get isAuthenticationRequired =>
      status == 401 && code == 'AUTHENTICATION_REQUIRED';
  bool get isRecentAuthenticationRequired =>
      status == 403 && code == 'RECENT_AUTHENTICATION_REQUIRED';
  bool get isEmailVerificationRequired =>
      status == 403 && code == 'EMAIL_VERIFICATION_REQUIRED';
  bool get isProviderUnavailable =>
      status == 503 || code == 'PROVIDER_CONFIGURATION_PENDING';

  @override
  String toString() => 'AuthFailure($status, $code)';
}

abstract interface class AuthGateway {
  Future<AuthSessionGrant> register({
    required String name,
    required String email,
    required String password,
  });
  Future<AuthSessionGrant> login({
    required String email,
    required String password,
  });
  Future<void> verifyEmail(String token);
  Future<void> resendEmailVerification();
  Future<void> requestPasswordRecovery(String email);
  Future<void> resetPassword({required String token, required String password});
  Future<Session> currentSession();
  Future<void> reauthenticate(String password);
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  });
  Future<List<AuthSessionInfo>> listSessions();
  Future<void> revokeCurrentSession();
  Future<void> revokeOtherSessions();
  Future<void> revokeAllSessions();
  Future<void> deleteAccount(String confirmation);
  Future<ProviderIntent> createProviderIntent(
    AuthProvider provider,
    ProviderIntentPurpose purpose,
  );
  Future<ProviderCompletion> completeProviderIntent({
    required AuthProvider provider,
    required String state,
    required String idToken,
  });
  Future<void> unlinkProvider(AuthProvider provider);
}

class HttpAuthGateway implements AuthGateway {
  HttpAuthGateway({
    required this.baseUrl,
    String? bearerToken,
    this.bearerTokenProvider,
    this.diagnostics,
    HttpClient? client,
  })  : _staticBearerToken = bearerToken,
        _client = client ?? HttpClient();

  final Uri baseUrl;
  final String? _staticBearerToken;
  final String? Function()? bearerTokenProvider;
  final RuntimeDiagnostics? diagnostics;
  final HttpClient _client;

  String? get _bearerToken =>
      bearerTokenProvider?.call() ?? _staticBearerToken;

  @override
  Future<AuthSessionGrant> register({
    required String name,
    required String email,
    required String password,
  }) async =>
      AuthSessionGrant.fromJson(
        await _requestMap(
          'auth/register',
          method: 'POST',
          authenticated: false,
          body: {'name': name, 'email': email, 'password': password},
        ),
      );

  @override
  Future<AuthSessionGrant> login({
    required String email,
    required String password,
  }) async =>
      AuthSessionGrant.fromJson(
        await _requestMap(
          'auth/login',
          method: 'POST',
          authenticated: false,
          body: {'email': email, 'password': password},
        ),
      );

  @override
  Future<void> verifyEmail(String token) => _requestVoid(
        'auth/email/verify',
        method: 'POST',
        authenticated: false,
        body: {'token': token},
      );

  @override
  Future<void> resendEmailVerification() => _requestVoid(
        'auth/email/verification',
        method: 'POST',
      );

  @override
  Future<void> requestPasswordRecovery(String email) => _requestVoid(
        'auth/password/recovery',
        method: 'POST',
        authenticated: false,
        body: {'email': email},
      );

  @override
  Future<void> resetPassword({
    required String token,
    required String password,
  }) =>
      _requestVoid(
        'auth/password/reset',
        method: 'POST',
        authenticated: false,
        body: {'token': token, 'password': password},
      );

  @override
  Future<Session> currentSession() async => Session.fromJson(
        await _requestMap('session'),
      );

  @override
  Future<void> reauthenticate(String password) => _requestVoid(
        'auth/reauthenticate',
        method: 'POST',
        body: {'password': password},
      );

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) =>
      _requestVoid(
        'auth/password',
        method: 'PUT',
        body: {
          'current_password': currentPassword,
          'new_password': newPassword,
        },
      );

  @override
  Future<List<AuthSessionInfo>> listSessions() async {
    final data = await _requestMap('auth/sessions');
    final raw = data['sessions'];
    if (raw is! List) return const [];
    return List<AuthSessionInfo>.unmodifiable(
      raw.whereType<Map>().map(
            (item) => AuthSessionInfo.fromJson(
              Map<String, dynamic>.from(item),
            ),
          ),
    );
  }

  @override
  Future<void> revokeCurrentSession() => _requestVoid(
        'auth/sessions/current',
        method: 'DELETE',
      );

  @override
  Future<void> revokeOtherSessions() => _requestVoid(
        'auth/sessions/others',
        method: 'DELETE',
      );

  @override
  Future<void> revokeAllSessions() => _requestVoid(
        'auth/sessions',
        method: 'DELETE',
      );

  @override
  Future<void> deleteAccount(String confirmation) => _requestVoid(
        'auth/account',
        method: 'DELETE',
        body: {'confirmation': confirmation},
      );

  @override
  Future<ProviderIntent> createProviderIntent(
    AuthProvider provider,
    ProviderIntentPurpose purpose,
  ) async {
    final path = purpose == ProviderIntentPurpose.login
        ? 'auth/providers/${provider.value}/login-intents'
        : 'auth/providers/${provider.value}/link-intents';
    final data = await _requestMap(
      path,
      method: 'POST',
      authenticated: purpose == ProviderIntentPurpose.link,
    );
    return ProviderIntent.fromJson(provider, purpose, data);
  }

  @override
  Future<ProviderCompletion> completeProviderIntent({
    required AuthProvider provider,
    required String state,
    required String idToken,
  }) async =>
      ProviderCompletion.fromJson(
        await _requestMap(
          'auth/providers/${provider.value}/callback',
          method: 'POST',
          authenticated: false,
          body: {'state': state, 'id_token': idToken},
        ),
      );

  @override
  Future<void> unlinkProvider(AuthProvider provider) => _requestVoid(
        'auth/providers/${provider.value}',
        method: 'DELETE',
      );

  Future<Map<String, dynamic>> _requestMap(
    String path, {
    String method = 'GET',
    bool authenticated = true,
    Map<String, dynamic>? body,
  }) async {
    final data = await _requestData(
      path,
      method: method,
      authenticated: authenticated,
      body: body,
    );
    if (data is! Map) {
      throw const AuthFailure(
        status: 0,
        code: 'MOBILE_AUTH_INVALID_RESPONSE',
        message: 'The authentication service returned an invalid response.',
        retryable: false,
      );
    }
    return Map<String, dynamic>.from(data);
  }

  Future<void> _requestVoid(
    String path, {
    String method = 'GET',
    bool authenticated = true,
    Map<String, dynamic>? body,
  }) async {
    await _requestData(
      path,
      method: method,
      authenticated: authenticated,
      body: body,
    );
  }

  Future<dynamic> _requestData(
    String path, {
    String method = 'GET',
    bool authenticated = true,
    Map<String, dynamic>? body,
  }) async {
    final diagnosticAttempt = RuntimeDiagnosticTransportAttempt.start(
      diagnostics,
      diagnosticOperationName('auth', method, path),
    );
    try {
      final request = await _client.openUrl(method, baseUrl.resolve(path));
      diagnosticAttempt.attach(request);
      request.headers.set(
        HttpHeaders.acceptHeader,
        'application/json, application/problem+json',
      );
      if (authenticated) {
        final token = _bearerToken;
        if (token != null && token.isNotEmpty) {
          request.headers.set(
            HttpHeaders.authorizationHeader,
            'Bearer $token',
          );
        }
      }
      if (body != null) {
        request.headers.contentType = ContentType.json;
        request.write(jsonEncode(body));
      }

      final response = await request.close();
      diagnosticAttempt.acceptResponse(response);
      final text = await response.transform(utf8.decoder).join();
      final payload = text.isEmpty ? null : jsonDecode(text);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final problem = payload is Map
            ? Map<String, dynamic>.from(payload)
            : <String, dynamic>{};
        final failure = AuthFailure(
          status: response.statusCode,
          code: problem['code'] as String? ?? 'AUTH_REQUEST_FAILED',
          message: problem['detail'] as String? ?? 'The authentication request failed.',
          retryable: problem['retryable'] as bool? ?? response.statusCode >= 500,
        );
        diagnosticAttempt.backendFailure(
          status: failure.status,
          stableCode: failure.code,
          retryable: failure.retryable,
        );
        throw failure;
      }
      if (payload == null) {
        diagnosticAttempt.success(status: response.statusCode);
        return null;
      }
      if (payload is! Map) {
        diagnosticAttempt.invalidResponse(
          stableCode: 'MOBILE_AUTH_INVALID_RESPONSE',
        );
        throw const AuthFailure(
          status: 0,
          code: 'MOBILE_AUTH_INVALID_RESPONSE',
          message: 'The authentication service returned an invalid response.',
          retryable: false,
        );
      }
      diagnosticAttempt.success(status: response.statusCode);
      return Map<String, dynamic>.from(payload)['data'];
    } on AuthFailure catch (failure) {
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
      diagnosticAttempt.transportFailure(stableCode: 'MOBILE_NETWORK_TIMEOUT');
      throw const AuthFailure(
        status: 0,
        code: 'MOBILE_NETWORK_TIMEOUT',
        message: 'The authentication request timed out.',
        retryable: true,
      );
    } on SocketException catch (error) {
      diagnosticAttempt.offline();
      throw AuthFailure(
        status: 0,
        code: 'MOBILE_NETWORK_OFFLINE',
        message: error.message,
        retryable: true,
      );
    } on HttpException catch (error) {
      diagnosticAttempt.transportFailure();
      throw AuthFailure(
        status: 0,
        code: 'MOBILE_NETWORK_ERROR',
        message: error.message,
        retryable: true,
      );
    } on FormatException {
      diagnosticAttempt.invalidResponse(
        stableCode: 'MOBILE_AUTH_INVALID_RESPONSE',
      );
      throw const AuthFailure(
        status: 0,
        code: 'MOBILE_AUTH_INVALID_RESPONSE',
        message: 'The authentication service returned malformed JSON.',
        retryable: false,
      );
    }
  }
}

class UnconfiguredAuthGateway implements AuthGateway {
  const UnconfiguredAuthGateway();

  AuthFailure get _failure => const AuthFailure(
        status: 503,
        code: 'MOBILE_API_NOT_CONFIGURED',
        message: 'The mobile API endpoint is not configured for this build.',
        retryable: false,
      );

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) => Future.error(_failure);

  @override
  Future<ProviderCompletion> completeProviderIntent({
    required AuthProvider provider,
    required String state,
    required String idToken,
  }) => Future.error(_failure);

  @override
  Future<ProviderIntent> createProviderIntent(
    AuthProvider provider,
    ProviderIntentPurpose purpose,
  ) => Future.error(_failure);

  @override
  Future<Session> currentSession() => Future.error(_failure);

  @override
  Future<void> deleteAccount(String confirmation) => Future.error(_failure);

  @override
  Future<AuthSessionGrant> login({
    required String email,
    required String password,
  }) => Future.error(_failure);

  @override
  Future<List<AuthSessionInfo>> listSessions() => Future.error(_failure);

  @override
  Future<void> reauthenticate(String password) => Future.error(_failure);

  @override
  Future<AuthSessionGrant> register({
    required String name,
    required String email,
    required String password,
  }) => Future.error(_failure);

  @override
  Future<void> requestPasswordRecovery(String email) => Future.error(_failure);

  @override
  Future<void> resendEmailVerification() => Future.error(_failure);

  @override
  Future<void> resetPassword({
    required String token,
    required String password,
  }) => Future.error(_failure);

  @override
  Future<void> revokeAllSessions() => Future.error(_failure);

  @override
  Future<void> revokeCurrentSession() => Future.error(_failure);

  @override
  Future<void> revokeOtherSessions() => Future.error(_failure);

  @override
  Future<void> unlinkProvider(AuthProvider provider) => Future.error(_failure);

  @override
  Future<void> verifyEmail(String token) => Future.error(_failure);
}
