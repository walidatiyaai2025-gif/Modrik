import 'auth_models.dart';

class ProviderLaunchFailure implements Exception {
  const ProviderLaunchFailure({required this.code, required this.message});

  final String code;
  final String message;

  @override
  String toString() => 'ProviderLaunchFailure($code)';
}

class ProviderAuthorization {
  const ProviderAuthorization({required this.idToken});

  final String idToken;
}

abstract interface class ProviderAuthLauncher {
  Future<ProviderAuthorization> authorize(
    AuthProvider provider,
    ProviderIntent intent,
  );
}

/// Production provider SDK/configuration remains owner-controlled. This default
/// boundary intentionally fails closed instead of inventing client IDs, store
/// identifiers, callback URLs, private keys, or a Firebase Auth substitute.
class UnconfiguredProviderAuthLauncher implements ProviderAuthLauncher {
  const UnconfiguredProviderAuthLauncher();

  @override
  Future<ProviderAuthorization> authorize(
    AuthProvider provider,
    ProviderIntent intent,
  ) =>
      Future.error(
        const ProviderLaunchFailure(
          code: 'PROVIDER_CONFIGURATION_PENDING',
          message: 'The production identity provider is not configured.',
        ),
      );
}
