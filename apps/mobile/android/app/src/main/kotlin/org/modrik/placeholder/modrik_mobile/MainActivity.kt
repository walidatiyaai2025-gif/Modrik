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
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class MainActivity : FlutterActivity() {
    private val channelName = "org.modrik.mobile/secure_session"
    private val preferencesName = "modrik_secure_session"
    private val credentialKey = "credential_v1"
    private val keyAlias = "modrik_mobile_session_v1"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, channelName)
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

    private fun readCredential(): String? {
        val encoded = getSharedPreferences(preferencesName, MODE_PRIVATE)
            .getString(credentialKey, null) ?: return null
        val separator = encoded.indexOf('.')
        if (separator <= 0 || separator == encoded.lastIndex) {
            throw IllegalStateException("Invalid encrypted credential payload")
        }

        val iv = Base64.decode(encoded.substring(0, separator), Base64.NO_WRAP)
        val ciphertext = Base64.decode(encoded.substring(separator + 1), Base64.NO_WRAP)
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE, getOrCreateKey(), GCMParameterSpec(128, iv))
        return String(cipher.doFinal(ciphertext), StandardCharsets.UTF_8)
    }

    private fun writeCredential(value: String) {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, getOrCreateKey())
        val ciphertext = cipher.doFinal(value.toByteArray(StandardCharsets.UTF_8))
        val encoded = Base64.encodeToString(cipher.iv, Base64.NO_WRAP) + "." +
            Base64.encodeToString(ciphertext, Base64.NO_WRAP)

        val stored = getSharedPreferences(preferencesName, MODE_PRIVATE)
            .edit()
            .putString(credentialKey, encoded)
            .commit()
        if (!stored) {
            throw IllegalStateException("Secure credential write was not committed")
        }
    }

    private fun clearCredential() {
        val cleared = getSharedPreferences(preferencesName, MODE_PRIVATE)
            .edit()
            .remove(credentialKey)
            .commit()
        if (!cleared) {
            throw IllegalStateException("Secure credential clear was not committed")
        }
    }

    private fun getOrCreateKey(): SecretKey {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
            throw IllegalStateException("Android Keystore AES-GCM requires API 23+")
        }

        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        val existing = keyStore.getEntry(keyAlias, null) as? KeyStore.SecretKeyEntry
        if (existing != null) {
            return existing.secretKey
        }

        val generator = KeyGenerator.getInstance(
            KeyProperties.KEY_ALGORITHM_AES,
            "AndroidKeyStore",
        )
        generator.init(
            KeyGenParameterSpec.Builder(
                keyAlias,
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
