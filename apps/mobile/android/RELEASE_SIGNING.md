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

Ordinary debug development/testing does not require release signing inputs. When a release artifact task such as `assembleRelease` or `bundleRelease` is requested, Gradle verifies that all four external values are present and that the configured keystore/key entry can be loaded without exposing credentials.

The release gate verifies the actual selected signing identity, not only the keystore path. It computes in-memory SHA-256 digests for the selected certificate and public key, compares them with the Android debug signing identity when that identity is available locally, and independently rejects the canonical Android debug certificate subject (`CN=Android Debug, O=Android, C=US`). A copied debug keystore at another path therefore remains blocked. Fingerprints, key material, aliases, and passwords are never printed.

`tool/verify_android_release_signing_gate.sh` is an executable CI check. It proves an ordinary debug APK can still be assembled without release credentials, creates only an ephemeral standard Android debug identity in a temporary directory, copies that identity to a different path, and confirms `assembleRelease` fails with the non-secret debug-identity diagnostic. The temporary keystores are deleted when the check exits and are never committed.

The placeholder application ID `org.modrik.placeholder.modrik_mobile` remains intentional until the owner supplies the final production identifier. Production store submission and iOS signing are outside this gate.
