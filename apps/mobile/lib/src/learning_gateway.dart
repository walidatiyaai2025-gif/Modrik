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
    bool fixtureMode = false,
    this.initialLessonId,
    String? academicTrackId,
  })  : academicTrackId = academicTrackId,
        fixtureMode = fixtureMode || academicTrackId != null;

  factory MobileBootstrapConfig.fromEnvironment() {
    const rawBase = String.fromEnvironment('MODRIK_API_BASE_URL');

    return MobileBootstrapConfig(
      apiBaseUrl: normalizeProductionApiBase(rawBase),
    );
  }

  /// Converts the owner supplied Backend origin into the canonical Mobile API
  /// root. Mobile gateways resolve paths such as `auth/register` and `session`
  /// relative to this URI, while the Backend contract is rooted at `/v1`.
  ///
  /// Accept both the convenient deployment origin (`https://host`) and an
  /// explicitly versioned value (`https://host/v1/`) without ever producing a
  /// double `/v1/v1/` prefix.
  static Uri? normalizeProductionApiBase(String rawBase) {
    final trimmed = rawBase.trim();
    if (trimmed.isEmpty) return null;

    final parsed = Uri.parse(trimmed);
    final segments = parsed.pathSegments.where((segment) => segment.isNotEmpty).toList();
    if (segments.isEmpty || segments.last != 'v1') {
      segments.add('v1');
    }

    return parsed.replace(
      path: '/${segments.join('/')}/',
      query: null,
      fragment: null,
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
