import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';

void main() {
  test('synthetic fixture credentials cannot activate application bootstrap', () {
    final config = MobileBootstrapConfig(
      apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      bearerToken: 'fixture-token-must-not-be-runtime-auth',
      fixtureMode: true,
      academicTrackId: 'fixture-track',
      initialLessonId: 'fixture-lesson',
    );

    expect(config.hasFixtureCredential, isFalse);
    expect(config.fixtureMode, isTrue,
        reason: 'test metadata may remain available to isolated harnesses');
  });

  test('environment bootstrap exposes only the runtime API endpoint contract', () {
    final config = MobileBootstrapConfig.fromEnvironment();

    expect(config.hasFixtureCredential, isFalse);
    expect(config.bearerToken, isNull);
    expect(config.fixtureMode, isFalse);
    expect(config.academicTrackId, isNull);
    expect(config.initialLessonId, isNull);
  });
}
