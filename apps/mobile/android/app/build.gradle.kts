import java.io.File
import java.security.KeyStore
import java.security.MessageDigest
import java.security.cert.X509Certificate
import javax.naming.ldap.LdapName

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

data class SigningIdentity(
    val certificateSha256: String,
    val publicKeySha256: String,
    val commonName: String?,
    val organization: String?,
    val country: String?,
)

fun sha256Hex(bytes: ByteArray): String =
    MessageDigest.getInstance("SHA-256")
        .digest(bytes)
        .joinToString("") { byte -> "%02x".format(byte.toInt() and 0xff) }

fun certificateSubjectAttribute(certificate: X509Certificate, type: String): String? =
    LdapName(certificate.subjectX500Principal.name)
        .rdns
        .firstOrNull { rdn -> rdn.type.equals(type, ignoreCase = true) }
        ?.value
        ?.toString()

fun loadSigningIdentity(
    storeFile: File,
    storePassword: String?,
    keyAlias: String?,
    keyPassword: String?,
    preferredStoreType: String?,
    label: String,
): SigningIdentity {
    if (storePassword.isNullOrBlank() || keyAlias.isNullOrBlank() || keyPassword.isNullOrBlank()) {
        throw GradleException(
            "MODRIK Android $label signing identity could not be verified. " +
                "No release artifact will be produced.",
        )
    }

    val storeTypes =
        listOfNotNull(
            preferredStoreType?.trim()?.takeIf { it.isNotEmpty() },
            KeyStore.getDefaultType(),
            "JKS",
            "PKCS12",
        ).distinct()

    for (storeType in storeTypes) {
        try {
            val keyStore = KeyStore.getInstance(storeType)
            storeFile.inputStream().use { stream ->
                keyStore.load(stream, storePassword.toCharArray())
            }
            if (!keyStore.isKeyEntry(keyAlias)) {
                continue
            }

            // Verify the supplied key password resolves a private-key entry without exposing it.
            if (keyStore.getKey(keyAlias, keyPassword.toCharArray()) == null) {
                continue
            }

            val certificate = keyStore.getCertificate(keyAlias) as? X509Certificate ?: continue
            return SigningIdentity(
                certificateSha256 = sha256Hex(certificate.encoded),
                publicKeySha256 = sha256Hex(certificate.publicKey.encoded),
                commonName = certificateSubjectAttribute(certificate, "CN"),
                organization = certificateSubjectAttribute(certificate, "O"),
                country = certificateSubjectAttribute(certificate, "C"),
            )
        } catch (_: Exception) {
            // Try the next supported keystore type. Diagnostics below never expose secrets.
        }
    }

    throw GradleException(
        "MODRIK Android $label signing identity could not be verified. " +
            "Check the external keystore type, alias, and credentials. " +
            "No release artifact will be produced.",
    )
}

fun SigningIdentity.isCanonicalAndroidDebugIdentity(): Boolean =
    commonName.equals("Android Debug", ignoreCase = true) &&
        organization.equals("Android", ignoreCase = true) &&
        country.equals("US", ignoreCase = true)

fun externalSigningValue(name: String): String? =
    providers.gradleProperty(name)
        .orElse(providers.environmentVariable(name))
        .orNull
        ?.trim()
        ?.takeIf { it.isNotEmpty() }

val releaseSigningKeys = listOf(
    "MODRIK_ANDROID_SIGNING_STORE_FILE",
    "MODRIK_ANDROID_SIGNING_STORE_PASSWORD",
    "MODRIK_ANDROID_SIGNING_KEY_ALIAS",
    "MODRIK_ANDROID_SIGNING_KEY_PASSWORD",
)
val releaseSigningValues = releaseSigningKeys.associateWith(::externalSigningValue)
val missingReleaseSigningKeys = releaseSigningKeys.filter { releaseSigningValues[it] == null }
val releaseSigningConfigured = missingReleaseSigningKeys.isEmpty()

android {
    namespace = "org.modrik.placeholder.modrik_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Replace only when the owner supplies the final production application ID.
        applicationId = "org.modrik.placeholder.modrik_mobile"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (releaseSigningConfigured) {
            create("release") {
                storeFile = file(releaseSigningValues.getValue("MODRIK_ANDROID_SIGNING_STORE_FILE")!!)
                storePassword = releaseSigningValues.getValue("MODRIK_ANDROID_SIGNING_STORE_PASSWORD")!!
                keyAlias = releaseSigningValues.getValue("MODRIK_ANDROID_SIGNING_KEY_ALIAS")!!
                keyPassword = releaseSigningValues.getValue("MODRIK_ANDROID_SIGNING_KEY_PASSWORD")!!
            }
        }
    }

    buildTypes {
        release {
            // Release signing is external-only. Never substitute the debug signing config.
            signingConfig = if (releaseSigningConfigured) signingConfigs.getByName("release") else null
        }
    }
}

val appProjectPath = project.path
val releaseArtifactTaskNames = setOf("assembleRelease", "bundleRelease", "packageRelease")

gradle.taskGraph.whenReady {
    val releaseArtifactRequested = allTasks.any { task ->
        task.project.path == appProjectPath &&
            (task.name in releaseArtifactTaskNames || task.name.startsWith("packageRelease"))
    }

    if (!releaseArtifactRequested) {
        return@whenReady
    }

    if (!releaseSigningConfigured) {
        throw GradleException(
            "MODRIK Android release signing is not configured. Missing external values: " +
                missingReleaseSigningKeys.joinToString(", ") +
                ". Supply them through Gradle properties or environment/CI secrets. " +
                "Release builds intentionally do not fall back to debug signing.",
        )
    }

    val configuredStoreFile = file(releaseSigningValues.getValue("MODRIK_ANDROID_SIGNING_STORE_FILE")!!)
    if (!configuredStoreFile.isFile) {
        throw GradleException(
            "MODRIK Android release signing store file does not exist. " +
                "Provide a valid external MODRIK_ANDROID_SIGNING_STORE_FILE before requesting a release artifact.",
        )
    }

    val releaseSigningConfig = android.signingConfigs.getByName("release")
    val debugSigningConfig = android.signingConfigs.getByName("debug")

    if (releaseSigningConfig.storeFile?.canonicalFile == debugSigningConfig.storeFile?.canonicalFile) {
        throw GradleException(
            "MODRIK Android release signing resolved to the Android debug signing identity. " +
                "A production release artifact must use an external non-debug signing identity.",
        )
    }

    val releaseIdentity =
        loadSigningIdentity(
            configuredStoreFile,
            releaseSigningConfig.storePassword,
            releaseSigningConfig.keyAlias,
            releaseSigningConfig.keyPassword,
            releaseSigningConfig.storeType,
            "release",
        )

    val debugStoreFile = debugSigningConfig.storeFile
    if (debugStoreFile?.isFile == true) {
        val debugIdentity =
            loadSigningIdentity(
                debugStoreFile,
                debugSigningConfig.storePassword,
                debugSigningConfig.keyAlias,
                debugSigningConfig.keyPassword,
                debugSigningConfig.storeType,
                "debug",
            )
        if (
            releaseIdentity.certificateSha256 == debugIdentity.certificateSha256 ||
            releaseIdentity.publicKeySha256 == debugIdentity.publicKeySha256
        ) {
            throw GradleException(
                "MODRIK Android release signing resolved to the Android debug signing identity. " +
                    "A production release artifact must use an external non-debug signing identity.",
            )
        }
    }

    // Defense in depth when no local debug keystore exists for a direct fingerprint comparison.
    if (releaseIdentity.isCanonicalAndroidDebugIdentity()) {
        throw GradleException(
            "MODRIK Android release signing resolved to the Android debug signing identity. " +
                "A production release artifact must use an external non-debug signing identity.",
        )
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
