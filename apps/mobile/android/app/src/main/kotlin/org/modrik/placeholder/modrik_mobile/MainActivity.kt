package org.modrik.placeholder.modrik_mobile

import android.os.Build
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.nio.charset.StandardCharsets
import java.security.KeyStore
import java.security.MessageDigest
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class MainActivity : FlutterActivity() {
    private val secureSessionChannelName = "org.modrik.mobile/secure_session"
    private val secureSessionPreferencesName = "modrik_secure_session"
    private val credentialKey = "credential_v1"
    private val secureSessionKeyAlias = "modrik_mobile_session_v1"

    private val learningRecoveryChannelName = "org.modrik.mobile/learning_recovery"
    private val learningRecoveryPreferencesName = "modrik_learning_recovery_v1"
    private val learningRecoveryKeyAlias = "modrik_mobile_learning_recovery_v1"
    private val learningRecoveryBuckets = setOf(
        "pending_operations",
        "attempt_snapshot",
        "downloaded_lessons",
    )

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        configureSecureSessionChannel(flutterEngine)
        configureLearningRecoveryChannel(flutterEngine)
    }

    private fun configureSecureSessionChannel(flutterEngine: FlutterEngine) {
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, secureSessionChannelName)
            .setMethodCallHandler { call, result ->
                try {
                    when (call.method) {
                        "read" -> result.success(readCredential())
                        "write" -> {
                            val value = call.arguments as? String
                                ?: throw IllegalArgumentException("Credential payload is required")
                            writeCredential(value)
                            result.success(null)
                        }
                        "clear" -> {
                            clearCredential()
                            result.success(null)
                        }
                        else -> result.notImplemented()
                    }
                } catch (error: Exception) {
                    result.error(
                        "MOBILE_SECURE_STORAGE_UNAVAILABLE",
                        "Android secure session storage is unavailable.",
                        null,
                    )
                }
            }
    }

    private fun configureLearningRecoveryChannel(flutterEngine: FlutterEngine) {
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, learningRecoveryChannelName)
            .setMethodCallHandler { call, result ->
                try {
                    val arguments = call.arguments as? Map<*, *>
                        ?: throw IllegalArgumentException("Recovery storage arguments are required")
                    val accountId = arguments["account_id"] as? String
                        ?: throw IllegalArgumentException("Account ID is required")

                    when (call.method) {
                        "read" -> {
                            val bucket = requiredRecoveryBucket(arguments)
                            result.success(readRecoveryPayload(accountId, bucket))
                        }
                        "write" -> {
                            val bucket = requiredRecoveryBucket(arguments)
                            val payload = arguments["payload"] as? String
                                ?: throw IllegalArgumentException("Recovery payload is required")
                            writeRecoveryPayload(accountId, bucket, payload)
                            result.success(null)
                        }
                        "remove" -> {
                            val bucket = requiredRecoveryBucket(arguments)
                            removeRecoveryPayload(accountId, bucket)
                            result.success(null)
                        }
                        "clear_account" -> {
                            clearRecoveryAccount(accountId)
                            result.success(null)
                        }
                        else -> result.notImplemented()
                    }
                } catch (error: Exception) {
                    result.error(
                        "MOBILE_RECOVERY_STORAGE_UNAVAILABLE",
                        "Android learning recovery storage is unavailable.",
                        null,
                    )
                }
            }
    }

    private fun requiredRecoveryBucket(arguments: Map<*, *>): String {
        val bucket = arguments["bucket"] as? String
            ?: throw IllegalArgumentException("Recovery bucket is required")
        if (!learningRecoveryBuckets.contains(bucket)) {
            throw IllegalArgumentException("Unsupported recovery bucket")
        }
        return bucket
    }

    private fun readCredential(): String? = readEncryptedPreference(
        preferencesName = secureSessionPreferencesName,
        storageKey = credentialKey,
        alias = secureSessionKeyAlias,
        additionalAuthenticatedData = null,
    )

    private fun writeCredential(value: String) {
        writeEncryptedPreference(
            preferencesName = secureSessionPreferencesName,
            storageKey = credentialKey,
            alias = secureSessionKeyAlias,
            value = value,
            additionalAuthenticatedData = null,
        )
    }

    private fun clearCredential() {
        removePreference(secureSessionPreferencesName, credentialKey)
    }

    private fun readRecoveryPayload(accountId: String, bucket: String): String? {
        val storageKey = recoveryStorageKey(accountId, bucket)
        return readEncryptedPreference(
            preferencesName = learningRecoveryPreferencesName,
            storageKey = storageKey,
            alias = learningRecoveryKeyAlias,
            additionalAuthenticatedData = storageKey.toByteArray(StandardCharsets.UTF_8),
        )
    }

    private fun writeRecoveryPayload(accountId: String, bucket: String, payload: String) {
        val storageKey = recoveryStorageKey(accountId, bucket)
        writeEncryptedPreference(
            preferencesName = learningRecoveryPreferencesName,
            storageKey = storageKey,
            alias = learningRecoveryKeyAlias,
            value = payload,
            additionalAuthenticatedData = storageKey.toByteArray(StandardCharsets.UTF_8),
        )
    }

    private fun removeRecoveryPayload(accountId: String, bucket: String) {
        removePreference(
            learningRecoveryPreferencesName,
            recoveryStorageKey(accountId, bucket),
        )
    }

    private fun clearRecoveryAccount(accountId: String) {
        val prefix = recoveryAccountPrefix(accountId)
        val preferences = getSharedPreferences(learningRecoveryPreferencesName, MODE_PRIVATE)
        val editor = preferences.edit()
        preferences.all.keys
            .filter { key -> key.startsWith(prefix) }
            .forEach { key -> editor.remove(key) }
        if (!editor.commit()) {
            throw IllegalStateException("Recovery account clear was not committed")
        }
    }

    private fun recoveryStorageKey(accountId: String, bucket: String): String {
        if (accountId.isBlank() || !learningRecoveryBuckets.contains(bucket)) {
            throw IllegalArgumentException("Invalid recovery storage key")
        }
        return recoveryAccountPrefix(accountId) + bucket
    }

    private fun recoveryAccountPrefix(accountId: String): String {
        if (accountId.isBlank()) {
            throw IllegalArgumentException("Account ID is required")
        }
        val digest = MessageDigest.getInstance("SHA-256")
            .digest(accountId.toByteArray(StandardCharsets.UTF_8))
            .joinToString(separator = "") { byte -> "%02x".format(byte.toInt() and 0xff) }
        return "account_${digest}_"
    }

    private fun readEncryptedPreference(
        preferencesName: String,
        storageKey: String,
        alias: String,
        additionalAuthenticatedData: ByteArray?,
    ): String? {
        val encoded = getSharedPreferences(preferencesName, MODE_PRIVATE)
            .getString(storageKey, null) ?: return null
        val separator = encoded.indexOf('.')
        if (separator <= 0 || separator == encoded.lastIndex) {
            throw IllegalStateException("Invalid encrypted payload")
        }

        val iv = Base64.decode(encoded.substring(0, separator), Base64.NO_WRAP)
        val ciphertext = Base64.decode(encoded.substring(separator + 1), Base64.NO_WRAP)
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE, getOrCreateKey(alias), GCMParameterSpec(128, iv))
        if (additionalAuthenticatedData != null) {
            cipher.updateAAD(additionalAuthenticatedData)
        }
        return String(cipher.doFinal(ciphertext), StandardCharsets.UTF_8)
    }

    private fun writeEncryptedPreference(
        preferencesName: String,
        storageKey: String,
        alias: String,
        value: String,
        additionalAuthenticatedData: ByteArray?,
    ) {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, getOrCreateKey(alias))
        if (additionalAuthenticatedData != null) {
            cipher.updateAAD(additionalAuthenticatedData)
        }
        val ciphertext = cipher.doFinal(value.toByteArray(StandardCharsets.UTF_8))
        val encoded = Base64.encodeToString(cipher.iv, Base64.NO_WRAP) + "." +
            Base64.encodeToString(ciphertext, Base64.NO_WRAP)

        val stored = getSharedPreferences(preferencesName, MODE_PRIVATE)
            .edit()
            .putString(storageKey, encoded)
            .commit()
        if (!stored) {
            throw IllegalStateException("Encrypted storage write was not committed")
        }
    }

    private fun removePreference(preferencesName: String, storageKey: String) {
        val cleared = getSharedPreferences(preferencesName, MODE_PRIVATE)
            .edit()
            .remove(storageKey)
            .commit()
        if (!cleared) {
            throw IllegalStateException("Encrypted storage clear was not committed")
        }
    }

    private fun getOrCreateKey(alias: String): SecretKey {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
            throw IllegalStateException("Android Keystore AES-GCM requires API 23+")
        }

        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        val existing = keyStore.getEntry(alias, null) as? KeyStore.SecretKeyEntry
        if (existing != null) {
            return existing.secretKey
        }

        val generator = KeyGenerator.getInstance(
            KeyProperties.KEY_ALGORITHM_AES,
            "AndroidKeyStore",
        )
        generator.init(
            KeyGenParameterSpec.Builder(
                alias,
                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
            )
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setRandomizedEncryptionRequired(true)
                .build(),
        )
        return generator.generateKey()
    }
}
