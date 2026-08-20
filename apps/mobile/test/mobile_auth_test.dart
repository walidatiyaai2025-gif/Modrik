import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/auth_gateway.dart';
import 'package:modrik_mobile/src/auth_models.dart';
import 'package:modrik_mobile/src/issue14_sync_client.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_auth_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/offline_boundary.dart';
import 'package:modrik_mobile/src/provider_auth_launcher.dart';
import 'package:modrik_mobile/src/secure_session_store.dart';

void main() {
  test('offline startup accepts only an unexpired saved secure session', () async {
    final memory = MemoryAuthCredentialStore();
    await memory.write(_credential(expiresAt: DateTime.utc(2026, 8, 22)));
    final store = ExpiryAwareAuthCredentialStore(
      memory,
      clock: () => DateTime.utc(2026, 8, 20),
    );
    final token = MutableBearerToken();
    var activated = 0;
    final controller = MobileAuthController(
      gateway: _FakeAuthGateway(
        currentSessionFailure: const AuthFailure(
          status: 0,
          code: 'MOBILE_NETWORK_OFFLINE',
          message: 'offline',
          retryable: true,
        ),
      ),
      credentialStore: store,
      tokenProvider: token,
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: true,
      onSessionActivated: (_, __) async => activated++,
    );

    await controller.bootstrap();

    expect(controller.state, MobileAuthState.offlineAuthenticated);
    expect(controller.messageCode, 'offline_saved_session');
    expect(token.value, 'opaque-backend-session');
    expect(activated, 1);
  });

  test('expired saved credential is erased before offline authentication', () async {
    final memory = MemoryAuthCredentialStore();
    await memory.write(_credential(expiresAt: DateTime.utc(2026, 8, 19)));
    final store = ExpiryAwareAuthCredentialStore(
      memory,
      clock: () => DateTime.utc(2026, 8, 20),
    );
    final gateway = _FakeAuthGateway(
      currentSessionFailure: const AuthFailure(
        status: 0,
        code: 'MOBILE_NETWORK_OFFLINE',
        message: 'offline',
        retryable: true,
      ),
    );
    final token = MutableBearerToken();
    final controller = MobileAuthController(
      gateway: gateway,
      credentialStore: store,
      tokenProvider: token,
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: true,
    );

    await controller.bootstrap();

    expect(controller.state, MobileAuthState.signedOut);
    expect(token.value, isNull);
    expect(await memory.read(), isNull);
    expect(gateway.currentSessionCalls, 0);
  });

  test('backend session revocation clears local credential and bearer', () async {
    final store = MemoryAuthCredentialStore();
    await store.write(_credential(expiresAt: DateTime.utc(2026, 8, 22)));
    final token = MutableBearerToken();
    final controller = MobileAuthController(
      gateway: _FakeAuthGateway(
        currentSessionFailure: const AuthFailure(
          status: 401,
          code: 'AUTHENTICATION_REQUIRED',
          message: 'revoked',
          retryable: false,
        ),
      ),
      credentialStore: store,
      tokenProvider: token,
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: true,
    );

    await controller.bootstrap();

    expect(controller.state, MobileAuthState.signedOut);
    expect(controller.messageCode, 'session_expired');
    expect(token.value, isNull);
    expect(await store.read(), isNull);
  });

  test('explicit logout cannot discard pending Issue #14 work', () async {
    final store = MemoryAuthCredentialStore();
    final token = MutableBearerToken();
    final gateway = _FakeAuthGateway(
      sessionResult: const Session(
        userId: 'user-1',
        locale: ModrikLocale.en,
        roles: ['student'],
      ),
    );
    final controller = MobileAuthController(
      gateway: gateway,
      credentialStore: store,
      tokenProvider: token,
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: true,
      canEndSession: () async => false,
    );

    await controller.login(
      email: 'student@example.test',
      password: 'password-1234',
    );
    await controller.logoutCurrentSession();

    expect(gateway.revokeCurrentCalls, 0);
    expect(controller.state, MobileAuthState.authenticated);
    expect(controller.messageCode, 'local_sync_required_before_sign_out');
    expect(token.value, 'opaque-backend-session');
  });

  test('default provider transport fails closed after backend one-time intent', () async {
    final gateway = _FakeAuthGateway();
    final controller = MobileAuthController(
      gateway: gateway,
      credentialStore: MemoryAuthCredentialStore(),
      tokenProvider: MutableBearerToken(),
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: true,
    )..state = MobileAuthState.signedOut;

    await controller.providerLogin(AuthProvider.google);

    expect(gateway.providerIntentCalls, 1);
    expect(gateway.providerCompletionCalls, 0);
    expect(controller.messageCode, 'PROVIDER_CONFIGURATION_PENDING');
    expect(controller.state, MobileAuthState.signedOut);
  });

  test('learning and Sync transports read the current opaque bearer dynamically', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    final seen = <String?>[];
    server.listen((request) async {
      seen.add(request.headers.value(HttpHeaders.authorizationHeader));
      request.response.headers.contentType = ContentType.json;
      if (request.uri.path.endsWith('/session')) {
        request.response.write(jsonEncode({
          'data': {
            'user_id': '01J00000000000000000000001',
            'locale': 'en',
            'roles': ['student'],
          }
        }));
      } else {
        await utf8.decoder.bind(request).join();
        request.response.write(jsonEncode({
          'data': {
            'acknowledgements': [
              {
                'operation_id': 'mobile-auth-sync-operation-0001',
                'outcome': 'applied',
                'code': 'SYNC_ANSWER_APPLIED',
                'replayed': false,
                'retryable': false,
                'answer_revision': 1,
                'answered_at': '2026-08-20T12:00:00Z',
              }
            ]
          }
        }));
      }
      await request.response.close();
    });

    try {
      final baseUrl = Uri.parse(
        'http://${server.address.address}:${server.port}/api/v1/',
      );
      final token = MutableBearerToken()..set('session-one');
      final learning = HttpLearningGateway(
        baseUrl: baseUrl,
        bearerTokenProvider: token.call,
      );
      final sync = HttpIssue14PendingSyncClient(
        baseUrl: baseUrl,
        bearerTokenProvider: token.call,
      );

      await learning.session();
      token.set('session-two');
      await sync.flush([
        PendingLearningOperation(
          localId: 'answer:a:q',
          type: PendingLearningOperationType.answer,
          logicalCommandKey: 'mobile-auth-sync-operation-0001',
          createdAt: DateTime.utc(2026, 8, 20),
          attemptId: '01J00000000000000000000001',
          attemptQuestionId: '01J00000000000000000000002',
          expectedRevision: 0,
          value: const ['option-a'],
        ),
      ]);

      expect(seen, ['Bearer session-one', 'Bearer session-two']);
    } finally {
      await server.close(force: true);
    }
  });
}

StoredAuthCredential _credential({required DateTime expiresAt}) =>
    StoredAuthCredential.fromGrant(
      _grant(expiresAt: expiresAt),
    );

AuthSessionGrant _grant({DateTime? expiresAt}) => AuthSessionGrant(
      account: const AuthAccount(
        id: 'user-1',
        email: 'student@example.test',
        emailVerified: true,
        passwordEnabled: true,
        status: 'active',
      ),
      accessToken: 'opaque-backend-session',
      tokenType: 'Bearer',
      session: AuthSessionInfo(
        id: 'session-1',
        name: 'Test phone',
        authenticatedAt: DateTime.utc(2026, 8, 20, 10),
        lastUsedAt: DateTime.utc(2026, 8, 20, 10),
        expiresAt: expiresAt ?? DateTime.utc(2026, 9, 19),
        createdAt: DateTime.utc(2026, 8, 20, 10),
        isCurrent: true,
      ),
    );

class _FakeAuthGateway implements AuthGateway {
  _FakeAuthGateway({
    this.sessionResult,
    this.currentSessionFailure,
  });

  final Session? sessionResult;
  final AuthFailure? currentSessionFailure;
  int currentSessionCalls = 0;
  int revokeCurrentCalls = 0;
  int providerIntentCalls = 0;
  int providerCompletionCalls = 0;

  @override
  Future<Session> currentSession() async {
    currentSessionCalls++;
    if (currentSessionFailure case final failure?) throw failure;
    return sessionResult ??
        const Session(
          userId: 'user-1',
          locale: ModrikLocale.en,
          roles: ['student'],
        );
  }

  @override
  Future<AuthSessionGrant> login({
    required String email,
    required String password,
  }) async =>
      _grant();

  @override
  Future<AuthSessionGrant> register({
    required String name,
    required String email,
    required String password,
  }) async =>
      _grant();

  @override
  Future<void> verifyEmail(String token) async {}

  @override
  Future<void> resendEmailVerification() async {}

  @override
  Future<void> requestPasswordRecovery(String email) async {}

  @override
  Future<void> resetPassword({
    required String token,
    required String password,
  }) async {}

  @override
  Future<void> reauthenticate(String password) async {}

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {}

  @override
  Future<List<AuthSessionInfo>> listSessions() async => [_grant().session];

  @override
  Future<void> revokeCurrentSession() async {
    revokeCurrentCalls++;
  }

  @override
  Future<void> revokeOtherSessions() async {}

  @override
  Future<void> revokeAllSessions() async {}

  @override
  Future<void> deleteAccount(String confirmation) async {}

  @override
  Future<ProviderIntent> createProviderIntent(
    AuthProvider provider,
    ProviderIntentPurpose purpose,
  ) async {
    providerIntentCalls++;
    return ProviderIntent(
      provider: provider,
      purpose: purpose,
      state: 'ssssssssssssssssssssssssssssssss',
      nonce: 'nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn',
      expiresAt: DateTime.utc(2026, 8, 20, 12),
    );
  }

  @override
  Future<ProviderCompletion> completeProviderIntent({
    required AuthProvider provider,
    required String state,
    required String idToken,
  }) async {
    providerCompletionCalls++;
    return ProviderCompletion(
      provider: provider,
      linked: false,
      grant: _grant(),
    );
  }

  @override
  Future<void> unlinkProvider(AuthProvider provider) async {}
}
