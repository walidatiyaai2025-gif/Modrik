import CryptoKit
import Flutter
import Security
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  private let secureSessionChannel = "org.modrik.mobile/secure_session"
  private let keychainService = "org.modrik.placeholder.modrik_mobile.secure_session"
  private let keychainAccount = "credential_v1"

  private let learningRecoveryChannel = "org.modrik.mobile/learning_recovery"
  private let learningRecoveryDirectory = "ModrikLearningRecoveryV1"
  private let learningRecoveryBuckets: Set<String> = [
    "pending_operations",
    "attempt_snapshot",
    "downloaded_lessons",
  ]

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)

    let registrar = engineBridge.pluginRegistry.registrar(forPlugin: "ModrikSecureSession")
    configureSecureSessionChannel(messenger: registrar.messenger())
    configureLearningRecoveryChannel(messenger: registrar.messenger())
  }

  private func configureSecureSessionChannel(messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(
      name: secureSessionChannel,
      binaryMessenger: messenger
    )
    channel.setMethodCallHandler { [weak self] call, result in
      guard let self else {
        result(
          FlutterError(
            code: "MOBILE_SECURE_STORAGE_UNAVAILABLE",
            message: "iOS secure session storage is unavailable.",
            details: nil
          )
        )
        return
      }

      switch call.method {
      case "read":
        self.readCredential(result: result)
      case "write":
        guard let value = call.arguments as? String else {
          result(
            FlutterError(
              code: "MOBILE_SECURE_STORAGE_UNAVAILABLE",
              message: "Credential payload is required.",
              details: nil
            )
          )
          return
        }
        self.writeCredential(value, result: result)
      case "clear":
        self.clearCredential(result: result)
      default:
        result(FlutterMethodNotImplemented)
      }
    }
  }

  private func configureLearningRecoveryChannel(messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(
      name: learningRecoveryChannel,
      binaryMessenger: messenger
    )
    channel.setMethodCallHandler { [weak self] call, result in
      guard let self else {
        result(self?.recoveryStorageError() ?? FlutterError(
          code: "MOBILE_RECOVERY_STORAGE_UNAVAILABLE",
          message: "iOS learning recovery storage is unavailable.",
          details: nil
        ))
        return
      }

      guard let arguments = call.arguments as? [String: Any],
            let accountId = arguments["account_id"] as? String,
            !accountId.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
        result(self.recoveryStorageError())
        return
      }

      do {
        switch call.method {
        case "read":
          let bucket = try self.requiredRecoveryBucket(arguments)
          result(try self.readRecoveryPayload(accountId: accountId, bucket: bucket))
        case "write":
          let bucket = try self.requiredRecoveryBucket(arguments)
          guard let payload = arguments["payload"] as? String else {
            throw RecoveryStorageError.invalidArguments
          }
          try self.writeRecoveryPayload(accountId: accountId, bucket: bucket, payload: payload)
          result(nil)
        case "remove":
          let bucket = try self.requiredRecoveryBucket(arguments)
          try self.removeRecoveryPayload(accountId: accountId, bucket: bucket)
          result(nil)
        case "clear_account":
          try self.clearRecoveryAccount(accountId: accountId)
          result(nil)
        default:
          result(FlutterMethodNotImplemented)
        }
      } catch {
        result(self.recoveryStorageError())
      }
    }
  }

  private func baseQuery() -> [String: Any] {
    [
      kSecClass as String: kSecClassGenericPassword,
      kSecAttrService as String: keychainService,
      kSecAttrAccount as String: keychainAccount,
    ]
  }

  private func readCredential(result: @escaping FlutterResult) {
    var query = baseQuery()
    query[kSecReturnData as String] = true
    query[kSecMatchLimit as String] = kSecMatchLimitOne

    var item: CFTypeRef?
    let status = SecItemCopyMatching(query as CFDictionary, &item)
    if status == errSecItemNotFound {
      result(nil)
      return
    }
    guard status == errSecSuccess,
          let data = item as? Data,
          let value = String(data: data, encoding: .utf8) else {
      result(secureStorageError(status))
      return
    }
    result(value)
  }

  private func writeCredential(_ value: String, result: @escaping FlutterResult) {
    guard let data = value.data(using: .utf8) else {
      result(secureStorageError(errSecParam))
      return
    }

    let deleteStatus = SecItemDelete(baseQuery() as CFDictionary)
    if deleteStatus != errSecSuccess && deleteStatus != errSecItemNotFound {
      result(secureStorageError(deleteStatus))
      return
    }

    var attributes = baseQuery()
    attributes[kSecValueData as String] = data
    attributes[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
    let status = SecItemAdd(attributes as CFDictionary, nil)
    guard status == errSecSuccess else {
      result(secureStorageError(status))
      return
    }
    result(nil)
  }

  private func clearCredential(result: @escaping FlutterResult) {
    let status = SecItemDelete(baseQuery() as CFDictionary)
    guard status == errSecSuccess || status == errSecItemNotFound else {
      result(secureStorageError(status))
      return
    }
    result(nil)
  }

  private func requiredRecoveryBucket(_ arguments: [String: Any]) throws -> String {
    guard let bucket = arguments["bucket"] as? String,
          learningRecoveryBuckets.contains(bucket) else {
      throw RecoveryStorageError.invalidArguments
    }
    return bucket
  }

  private func recoveryDirectoryURL(create: Bool) throws -> URL {
    guard let applicationSupport = FileManager.default.urls(
      for: .applicationSupportDirectory,
      in: .userDomainMask
    ).first else {
      throw RecoveryStorageError.storageUnavailable
    }
    let directory = applicationSupport.appendingPathComponent(
      learningRecoveryDirectory,
      isDirectory: true
    )
    if create && !FileManager.default.fileExists(atPath: directory.path) {
      try FileManager.default.createDirectory(
        at: directory,
        withIntermediateDirectories: true
      )
      try FileManager.default.setAttributes(
        [.protectionKey: FileProtectionType.completeUntilFirstUserAuthentication],
        ofItemAtPath: directory.path
      )
    }
    return directory
  }

  private func recoveryAccountHash(_ accountId: String) throws -> String {
    let normalized = accountId.trimmingCharacters(in: .whitespacesAndNewlines)
    guard !normalized.isEmpty, let data = normalized.data(using: .utf8) else {
      throw RecoveryStorageError.invalidArguments
    }
    return SHA256.hash(data: data).map { String(format: "%02x", $0) }.joined()
  }

  private func recoveryFileURL(accountId: String, bucket: String, createDirectory: Bool) throws -> URL {
    guard learningRecoveryBuckets.contains(bucket) else {
      throw RecoveryStorageError.invalidArguments
    }
    let accountHash = try recoveryAccountHash(accountId)
    let directory = try recoveryDirectoryURL(create: createDirectory)
    return directory.appendingPathComponent("\(accountHash)_\(bucket)_v1.json")
  }

  private func readRecoveryPayload(accountId: String, bucket: String) throws -> String? {
    let fileURL = try recoveryFileURL(
      accountId: accountId,
      bucket: bucket,
      createDirectory: false
    )
    guard FileManager.default.fileExists(atPath: fileURL.path) else {
      return nil
    }
    let data = try Data(contentsOf: fileURL)
    guard let payload = String(data: data, encoding: .utf8) else {
      throw RecoveryStorageError.invalidPayload
    }
    return payload
  }

  private func writeRecoveryPayload(accountId: String, bucket: String, payload: String) throws {
    guard let data = payload.data(using: .utf8) else {
      throw RecoveryStorageError.invalidPayload
    }
    let fileURL = try recoveryFileURL(
      accountId: accountId,
      bucket: bucket,
      createDirectory: true
    )
    try data.write(to: fileURL, options: .atomic)
    try FileManager.default.setAttributes(
      [.protectionKey: FileProtectionType.completeUntilFirstUserAuthentication],
      ofItemAtPath: fileURL.path
    )
  }

  private func removeRecoveryPayload(accountId: String, bucket: String) throws {
    let fileURL = try recoveryFileURL(
      accountId: accountId,
      bucket: bucket,
      createDirectory: false
    )
    if FileManager.default.fileExists(atPath: fileURL.path) {
      try FileManager.default.removeItem(at: fileURL)
    }
  }

  private func clearRecoveryAccount(accountId: String) throws {
    let directory = try recoveryDirectoryURL(create: false)
    guard FileManager.default.fileExists(atPath: directory.path) else {
      return
    }
    let prefix = try recoveryAccountHash(accountId) + "_"
    for item in try FileManager.default.contentsOfDirectory(
      at: directory,
      includingPropertiesForKeys: nil
    ) where item.lastPathComponent.hasPrefix(prefix) {
      try FileManager.default.removeItem(at: item)
    }
  }

  private func secureStorageError(_ status: OSStatus) -> FlutterError {
    FlutterError(
      code: "MOBILE_SECURE_STORAGE_UNAVAILABLE",
      message: "iOS Keychain operation failed.",
      details: status
    )
  }

  private func recoveryStorageError() -> FlutterError {
    FlutterError(
      code: "MOBILE_RECOVERY_STORAGE_UNAVAILABLE",
      message: "iOS learning recovery storage is unavailable.",
      details: nil
    )
  }
}

private enum RecoveryStorageError: Error {
  case invalidArguments
  case invalidPayload
  case storageUnavailable
}
