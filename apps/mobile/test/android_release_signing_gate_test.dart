import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Android release signing cannot fall back to debug identity', () {
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();

    expect(
      RegExp(
        r'signingConfig\s*=\s*signingConfigs\.getByName\("debug"\)',
      ).hasMatch(gradle),
      isFalse,
    );
    expect(
      gradle,
      contains(
        'signingConfig = if (releaseSigningConfigured) '
        'signingConfigs.getByName("release") else null',
      ),
    );
    expect(
      gradle,
      contains(
        'releaseSigningConfig.storeFile?.canonicalFile == '
        'debugSigningConfig.storeFile?.canonicalFile',
      ),
    );
  });

  test('Android release signing is external-only and fails closed', () {
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();

    for (final key in <String>[
      'MODRIK_ANDROID_SIGNING_STORE_FILE',
      'MODRIK_ANDROID_SIGNING_STORE_PASSWORD',
      'MODRIK_ANDROID_SIGNING_KEY_ALIAS',
      'MODRIK_ANDROID_SIGNING_KEY_PASSWORD',
    ]) {
      expect(gradle, contains(key));
    }

    expect(gradle, contains('providers.gradleProperty(name)'));
    expect(gradle, contains('providers.environmentVariable(name)'));
    expect(gradle, contains('releaseArtifactRequested'));
    expect(gradle, contains('throw GradleException('));
    expect(
      gradle,
      contains('Release builds intentionally do not fall back to debug signing.'),
    );
  });

  test('Android signing material remains ignored by the repository', () {
    final gitignore = File('android/.gitignore').readAsStringSync();

    expect(gitignore, contains('key.properties'));
    expect(gitignore, contains('**/*.keystore'));
    expect(gitignore, contains('**/*.jks'));
  });
}
