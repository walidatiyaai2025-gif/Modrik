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
    expect(gradle, contains('MessageDigest.getInstance("SHA-256")'));
    expect(gradle, contains('certificateSha256'));
    expect(gradle, contains('publicKeySha256'));
    expect(
      gradle,
      contains(
        'releaseIdentity.certificateSha256 == '
        'debugIdentity.certificateSha256',
      ),
    );
    expect(
      gradle,
      contains(
        'releaseIdentity.publicKeySha256 == '
        'debugIdentity.publicKeySha256',
      ),
    );
    expect(gradle, contains('isCanonicalAndroidDebugIdentity'));
    expect(gradle, contains('commonName.equals("Android Debug"'));
    expect(gradle, contains('organization.equals("Android"'));
    expect(gradle, contains('country.equals("US"'));
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
    expect(gradle, contains('loadSigningIdentity('));
    expect(gradle, contains('throw GradleException('));
    expect(
      gradle,
      contains('Release builds intentionally do not fall back to debug signing.'),
    );
    expect(
      gradle,
      contains(
        'MODRIK Android release signing resolved to the Android debug signing identity.',
      ),
    );
  });

  test('copied Android debug identity is verified executable in CI', () {
    final script = File('tool/verify_android_release_signing_gate.sh');
    expect(script.existsSync(), isTrue);
    final scriptContents = script.readAsStringSync();

    expect(scriptContents, contains(':app:assembleDebug'));
    expect(scriptContents, contains(':app:assembleRelease'));
    expect(scriptContents, contains('copied-debug-release.jks'));
    expect(
      scriptContents,
      contains("-dname 'CN=Android Debug,O=Android,C=US'"),
    );
    expect(
      scriptContents,
      contains(
        'MODRIK Android release signing resolved to the Android debug signing identity.',
      ),
    );

    final workflow = File('../../.github/workflows/ci.yml').readAsStringSync();
    expect(
      workflow,
      contains('bash tool/verify_android_release_signing_gate.sh'),
    );
  });

  test('Android signing material remains ignored by the repository', () {
    final gitignore = File('android/.gitignore').readAsStringSync();

    expect(gitignore, contains('key.properties'));
    expect(gitignore, contains('**/*.keystore'));
    expect(gitignore, contains('**/*.jks'));
  });
}
