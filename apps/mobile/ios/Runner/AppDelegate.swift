import Flutter
import Security
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  private let secureSessionChannel = "org.modrik.mobile/secure_session"
  private let keychainService = "org.modrik.placeholder.modrik_mobile.secure_session"
  private let keychainAccount = "credential_v1"

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)

    guard let registrar = engineBridge.pluginRegistry.registrar(forPlugin: "ModrikSecureSession") else {
      return
    }
    let channel = FlutterMethodChannel(
      name: secureSessionChannel,
      binaryMessenger: registrar.messenger()
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

  private func secureStorageError(_ status: OSStatus) -> FlutterError {
    FlutterError(
      code: "MOBILE_SECURE_STORAGE_UNAVAILABLE",
      message: "iOS Keychain operation failed.",
      details: status
    )
  }
}
