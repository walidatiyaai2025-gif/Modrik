export 'learning_gateway_runtime.dart' hide MobileBootstrapConfig;

/// Mobile runtime bootstrap configuration.
///
/// Production/Demo startup is intentionally limited to the API endpoint. Test
/// harnesses may still construct this value with deterministic lesson/context
/// hints, but synthetic credentials can never activate an application runtime
/// authentication path.
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

    return MobileBootstrapConfig(
      apiBaseUrl: rawBase.isEmpty
          ? null
          : Uri.parse(rawBase.endsWith('/') ? rawBase : '$rawBase/'),
    );
  }

  final Uri? apiBaseUrl;

  /// Test-harness compatibility only. Application startup never activates a
  /// static bearer credential from this value.
  final String? bearerToken;

  /// Test-harness compatibility only. This flag cannot activate runtime auth.
  final bool fixtureMode;
  final String? initialLessonId;
  final String? academicTrackId;

  bool get isConfigured => apiBaseUrl != null;

  /// Synthetic fixture credentials are never a valid application bootstrap.
  bool get hasFixtureCredential => false;
}
