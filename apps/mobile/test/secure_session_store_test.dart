import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/auth_models.dart';
import 'package:modrik_mobile/src/secure_session_store.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const channel = MethodChannel('org.modrik.mobile/secure_session');
  final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;

  tearDown(() {
    messenger.setMockMethodCallHandler(channel, null);
  });

  test('platform secure store round-trips read write and clear through the native channel', () async {
    String? stored;
    final methods = <String>[];
    messenger.setMockMethodCallHandler(channel, (call) async {
      methods.add(call.method);
      switch (call.method) {
        case 'read':
          return stored;
        case 'write':
          stored = call.arguments as String;
          return null;
        case 'clear':
          stored = null;
          return null;
      }
      throw PlatformException(code: 'NOT_IMPLEMENTED');
    });

    const store = PlatformSecureAuthCredentialStore();
    final credential = _credential(DateTime.utc(2026, 9, 19));
    await store.write(credential);

    expect(stored, isNotNull);
    final encoded = jsonDecode(stored!) as Map<String, dynamic>;
    expect(encoded['access_token'], 'opaque-backend-session');

    final restored = await store.read();
    expect(restored?.accessToken, credential.accessToken);
    expect(restored?.account.id, credential.account.id);
    expect(restored?.session.id, credential.session.id);

    await store.clear();
    expect(stored, isNull);
    expect(methods, ['write', 'read', 'clear']);
  });

  test('corrupt native secure storage payload fails closed', () async {
    messenger.setMockMethodCallHandler(channel, (call) async {
      if (call.method == 'read') return '{not-json';
      return null;
    });

    const store = PlatformSecureAuthCredentialStore();

    await expectLater(
      store.read(),
      throwsA(
        isA<AuthStorageFailure>().having(
          (failure) => failure.code,
          'code',
          'MOBILE_SECURE_STORAGE_INVALID',
        ),
      ),
    );
  });

  test('native secure storage unavailability is surfaced without plaintext fallback', () async {
    messenger.setMockMethodCallHandler(channel, (call) async {
      throw PlatformException(
        code: 'MOBILE_SECURE_STORAGE_UNAVAILABLE',
        message: 'native store unavailable',
      );
    });

    const store = PlatformSecureAuthCredentialStore();

    await expectLater(
      store.read(),
      throwsA(
        isA<AuthStorageFailure>().having(
          (failure) => failure.code,
          'code',
          'MOBILE_SECURE_STORAGE_UNAVAILABLE',
        ),
      ),
    );
  });

  test('expiry-aware platform boundary clears expired credential before returning it', () async {
    var stored = jsonEncode(
      _credential(DateTime.utc(2026, 8, 19)).toJson(),
    );
    var clearCalls = 0;
    messenger.setMockMethodCallHandler(channel, (call) async {
      switch (call.method) {
        case 'read':
          return stored;
        case 'clear':
          clearCalls++;
          stored = '';
          return null;
      }
      return null;
    });

    final store = ExpiryAwareAuthCredentialStore(
      const PlatformSecureAuthCredentialStore(),
      clock: () => DateTime.utc(2026, 8, 20),
    );

    expect(await store.read(), isNull);
    expect(clearCalls, 1);
    expect(stored, isEmpty);
  });
}

StoredAuthCredential _credential(DateTime expiresAt) => StoredAuthCredential(
      account: const AuthAccount(
        id: 'user-1',
        email: 'student@example.test',
        emailVerified: true,
        passwordEnabled: true,
        status: 'active',
      ),
      accessToken: 'opaque-backend-session',
      session: AuthSessionInfo(
        id: 'session-1',
        name: 'Test phone',
        authenticatedAt: DateTime.utc(2026, 8, 20, 10),
        lastUsedAt: DateTime.utc(2026, 8, 20, 10),
        expiresAt: expiresAt,
        createdAt: DateTime.utc(2026, 8, 20, 10),
        isCurrent: true,
      ),
    );
