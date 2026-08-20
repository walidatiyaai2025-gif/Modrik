import 'dart:convert';

import 'package:flutter/services.dart';

import 'auth_models.dart';

class AuthStorageFailure implements Exception {
  const AuthStorageFailure(this.code, [this.message]);

  final String code;
  final String? message;

  @override
  String toString() => 'AuthStorageFailure($code)';
}

abstract interface class AuthCredentialStore {
  Future<StoredAuthCredential?> read();
  Future<void> write(StoredAuthCredential credential);
  Future<void> clear();
}

class ExpiryAwareAuthCredentialStore implements AuthCredentialStore {
  ExpiryAwareAuthCredentialStore(
    this.delegate, {
    DateTime Function()? clock,
  }) : _clock = clock ?? DateTime.now;

  final AuthCredentialStore delegate;
  final DateTime Function() _clock;

  @override
  Future<StoredAuthCredential?> read() async {
    final credential = await delegate.read();
    if (credential == null) return null;
    if (!credential.session.expiresAt.isAfter(_clock())) {
      await delegate.clear();
      return null;
    }
    return credential;
  }

  @override
  Future<void> write(StoredAuthCredential credential) =>
      delegate.write(credential);

  @override
  Future<void> clear() => delegate.clear();
}

class PlatformSecureAuthCredentialStore implements AuthCredentialStore {
  const PlatformSecureAuthCredentialStore();

  static const MethodChannel _channel = MethodChannel(
    'org.modrik.mobile/secure_session',
  );

  @override
  Future<StoredAuthCredential?> read() async {
    try {
      final raw = await _channel.invokeMethod<String>('read');
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) {
        throw const AuthStorageFailure('MOBILE_SECURE_STORAGE_INVALID');
      }
      return StoredAuthCredential.fromJson(
        Map<String, dynamic>.from(decoded),
      );
    } on AuthStorageFailure {
      rethrow;
    } on PlatformException catch (error) {
      throw AuthStorageFailure(
        error.code.isEmpty ? 'MOBILE_SECURE_STORAGE_UNAVAILABLE' : error.code,
        error.message,
      );
    } on FormatException {
      throw const AuthStorageFailure('MOBILE_SECURE_STORAGE_INVALID');
    } on TypeError {
      throw const AuthStorageFailure('MOBILE_SECURE_STORAGE_INVALID');
    }
  }

  @override
  Future<void> write(StoredAuthCredential credential) async {
    try {
      await _channel.invokeMethod<void>(
        'write',
        jsonEncode(credential.toJson()),
      );
    } on PlatformException catch (error) {
      throw AuthStorageFailure(
        error.code.isEmpty ? 'MOBILE_SECURE_STORAGE_UNAVAILABLE' : error.code,
        error.message,
      );
    }
  }

  @override
  Future<void> clear() async {
    try {
      await _channel.invokeMethod<void>('clear');
    } on PlatformException catch (error) {
      throw AuthStorageFailure(
        error.code.isEmpty ? 'MOBILE_SECURE_STORAGE_UNAVAILABLE' : error.code,
        error.message,
      );
    }
  }
}

class MemoryAuthCredentialStore implements AuthCredentialStore {
  StoredAuthCredential? _credential;

  @override
  Future<void> clear() async => _credential = null;

  @override
  Future<StoredAuthCredential?> read() async => _credential;

  @override
  Future<void> write(StoredAuthCredential credential) async {
    _credential = credential;
  }
}

class MutableBearerToken {
  String? _value;

  String? call() => _value;

  String? get value => _value;

  void set(String token) {
    _value = token;
  }

  void clear() {
    _value = null;
  }
}
