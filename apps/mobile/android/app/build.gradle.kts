plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

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
            "MODRIK Android release signing resolved to the debug keystore. " +
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
