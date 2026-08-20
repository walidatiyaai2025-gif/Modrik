import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/auth_gateway.dart';
import 'package:modrik_mobile/src/auth_models.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_auth_controller.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/provider_auth_launcher.dart';
import 'package:modrik_mobile/src/secure_session_store.dart';

void main() {
  testWidgets('sign-in UX is semantic, 48px, large-text safe and RTL aware', (tester) async {
    final semantics = tester.ensureSemantics();
    tester.platformDispatcher.textScaleFactorTestValue = 2.0;
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);

    final auth = _authController()..state = MobileAuthState.signedOut;
    final learning = _learningController();
    await tester.pumpWidget(
      ModrikApp(
        controller: learning,
        authController: auth,
        autoInitialize: false,
      ),
    );

    expect(find.text('Sign in to MODRIK'), findsOneWidget);
    expect(find.bySemanticsLabel('Email'), findsWidgets);
    expect(find.bySemanticsLabel('Password'), findsWidgets);
    expect(find.text('Google'), findsOneWidget);
    expect(find.text('Apple'), findsOneWidget);

    final googleButton = find.ancestor(
      of: find.text('Google'),
      matching: find.byType(OutlinedButton),
    );
    expect(googleButton, findsOneWidget);
    expect(tester.getSize(googleButton).height, greaterThanOrEqualTo(48));
    expect(tester.takeException(), isNull);

    auth.setLocale(ModrikLocale.ar);
    await tester.pump();
    final arabicTitle = find.text('تسجيل الدخول إلى مُدرك');
    expect(arabicTitle, findsOneWidget);
    expect(
      Directionality.of(tester.element(arabicTitle)),
      TextDirection.rtl,
    );

    auth.setLocale(ModrikLocale.fr);
    await tester.pump();
    final frenchTitle = find.text('Se connecter à MODRIK');
    expect(frenchTitle, findsOneWidget);
    expect(
      Directionality.of(tester.element(frenchTitle)),
      TextDirection.ltr,
    );
    expect(tester.takeException(), isNull);
    semantics.dispose();
  });

  testWidgets('provider entry fails closed with explicit unavailable UX', (tester) async {
    final auth = _authController()..state = MobileAuthState.signedOut;
    await tester.pumpWidget(
      ModrikApp(
        controller: _learningController(),
        authController: auth,
        autoInitialize: false,
      ),
    );

    await tester.tap(find.text('Google'));
    await tester.pumpAndSettle();

    expect(
      find.textContaining('Google/Apple production configuration'),
      findsOneWidget,
    );
    expect(auth.state, MobileAuthState.signedOut);
    expect(auth.credential, isNull);
  });

  testWidgets('verification and resend controls are explicit and accessible', (tester) async {
    final auth = _authController()
      ..state = MobileAuthState.verificationRequired
      ..entryMode = AuthEntryMode.verify
      ..credential = _credential(emailVerified: false);
    await tester.pumpWidget(
      ModrikApp(
        controller: _learningController(),
        authController: auth,
        autoInitialize: false,
      ),
    );

    expect(find.text('Verify your email'), findsOneWidget);
    expect(find.bySemanticsLabel('Verification token'), findsWidgets);
    expect(find.text('Verify email'), findsOneWidget);
    expect(find.text('Resend verification'), findsOneWidget);
  });

  testWidgets('account security exposes session, recent-auth and deletion actions', (tester) async {
    final auth = _authController()
      ..state = MobileAuthState.authenticated
      ..credential = _credential()
      ..accountPanelOpen = true
      ..sessions = [_credential().session];
    await tester.pumpWidget(
      ModrikApp(
        controller: _learningController(),
        authController: auth,
        autoInitialize: false,
      ),
    );

    expect(find.text('Account security'), findsWidgets);
    expect(find.text('Current session'), findsOneWidget);
    expect(find.text('Confirm identity'), findsOneWidget);
    expect(find.text('Change password'), findsWidgets);
    expect(find.text('Link Google'), findsOneWidget);
    expect(find.text('Link Apple'), findsOneWidget);
    expect(find.text('Delete my account'), findsOneWidget);

    final logoutButton = find.ancestor(
      of: find.text('Log out of this device'),
      matching: find.byType(OutlinedButton),
    );
    expect(logoutButton, findsOneWidget);
    expect(tester.getSize(logoutButton).height, greaterThanOrEqualTo(48));
    expect(tester.takeException(), isNull);
  });
}

MobileLearningController _learningController() => MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    );

MobileAuthController _authController() => MobileAuthController(
      gateway: _WidgetAuthGateway(),
      credentialStore: MemoryAuthCredentialStore(),
      tokenProvider: MutableBearerToken(),
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: true,
    );

StoredAuthCredential _credential({bool emailVerified = true}) =>
    StoredAuthCredential(
      account: AuthAccount(
        id: 'user-1',
        email: 'student@example.test',
        emailVerified: emailVerified,
        passwordEnabled: true,
        status: 'active',
      ),
      accessToken: 'opaque-session',
      session: AuthSessionInfo(
        id: 'session-1',
        name: 'Android phone',
        authenticatedAt: DateTime.utc(2026, 8, 20, 10),
        lastUsedAt: DateTime.utc(2026, 8, 20, 10, 30),
        expiresAt: DateTime.utc(2026, 9, 19),
        createdAt: DateTime.utc(2026, 8, 20, 10),
        isCurrent: true,
      ),
    );

class _WidgetAuthGateway implements AuthGateway {
  @override
  Future<Session> currentSession() async => const Session(
        userId: 'user-1',
        locale: ModrikLocale.en,
        roles: ['student'],
      );

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

  AuthSessionGrant _grant() => AuthSessionGrant(
        account: _credential().account,
        accessToken: 'opaque-session',
        tokenType: 'Bearer',
        session: _credential().session,
      );

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
  Future<List<AuthSessionInfo>> listSessions() async => [_credential().session];

  @override
  Future<void> revokeCurrentSession() async {}

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
  ) async =>
      ProviderIntent(
        provider: provider,
        purpose: purpose,
        state: 'ssssssssssssssssssssssssssssssss',
        nonce: 'nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn',
        expiresAt: DateTime.utc(2026, 8, 20, 12),
      );

  @override
  Future<ProviderCompletion> completeProviderIntent({
    required AuthProvider provider,
    required String state,
    required String idToken,
  }) async =>
      ProviderCompletion(provider: provider, linked: false, grant: _grant());

  @override
  Future<void> unlinkProvider(AuthProvider provider) async {}
}
