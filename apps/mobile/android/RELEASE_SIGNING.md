# Android release signing gate

Android release artifacts are intentionally fail-closed until owner-controlled production signing material is supplied externally. The repository never substitutes the Android debug signing identity for a release build.

## External inputs

Supply all four values through Gradle properties (for example user-level `~/.gradle/gradle.properties` or CI-injected `-P` properties) or environment/CI secrets:

- `MODRIK_ANDROID_SIGNING_STORE_FILE` — path to the external keystore file;
- `MODRIK_ANDROID_SIGNING_STORE_PASSWORD` — keystore password;
- `MODRIK_ANDROID_SIGNING_KEY_ALIAS` — release key alias;
- `MODRIK_ANDROID_SIGNING_KEY_PASSWORD` — release key password.

Do not commit any of these values, keystores, certificates, passwords, aliases, store IDs, or production application IDs. `android/.gitignore` already excludes common keystore files and `key.properties`.

## Behavior

Ordinary debug development/testing does not require release signing inputs. When a release artifact task such as `assembleRelease` or `bundleRelease` is requested, Gradle verifies that all four external values are present, the configured keystore file exists, and the release signing store is not the debug keystore. Missing or unsafe configuration stops the build with a non-secret diagnostic.

The placeholder application ID `org.modrik.placeholder.modrik_mobile` remains intentional until the owner supplies the final production identifier. Production store submission and iOS signing are outside this gate.
