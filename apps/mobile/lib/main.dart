import 'dart:async';
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'src/academic_context_reset_boundary.dart';
import 'src/app_shell.dart';
import 'src/auth_gateway.dart';
import 'src/auth_shell.dart';
import 'src/durable_learning_store.dart';
import 'src/issue14_sync_client.dart';
import 'src/learning_gateway.dart';
import 'src/mobile_auth_controller.dart';
import 'src/mobile_learning_controller.dart';
import 'src/models.dart';
import 'src/notification_center.dart';
import 'src/offline_boundary.dart';
import 'src/provider_auth_launcher.dart';
import 'src/runtime_diagnostics.dart';
import 'src/runtime_inspector.dart';
import 'src/secure_session_store.dart';
import 'src/student_notifications.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const ModrikApp());
}

class ModrikApp extends StatefulWidget {
  const ModrikApp({
    super.key,
    this.controller,
    this.authController,
    this.notificationGateway,
    this.diagnostics,
    this.autoInitialize = true,
  });

  /// Supplying only the learning controller preserves the isolated fixture/test
  /// harness. Production startup creates and owns both controllers instead.
  final MobileLearningController? controller;
  final MobileAuthController? authController;
  final StudentNotificationGateway? notificationGateway;
  final RuntimeDiagnostics? diagnostics;
  final bool autoInitialize;

  @override
  State<ModrikApp> createState() => _ModrikAppState();
}

class _ModrikAppState extends State<ModrikApp> {
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();
  late MobileLearningController _controller;
  MobileAuthController? _authController;
  late StudentNotificationGateway _notificationGateway;
  RuntimeDiagnostics? _diagnostics;
  late bool _ownsController;
  late bool _ownsAuthController;
  bool _ownsDiagnostics = false;
  MobileBootstrapConfig? _config;
  MutableBearerToken? _tokenProvider;
  LearningRecoveryScope? _learningRecoveryScope;
  LearningRecoveryStorage? _learningRecoveryStorage;
  void Function(FlutterErrorDetails)? _previousFlutterErrorHandler;
  void Function(FlutterErrorDetails)? _installedFlutterErrorHandler;
  bool Function(Object, StackTrace)? _previousPlatformErrorHandler;
  bool Function(Object, StackTrace)? _installedPlatformErrorHandler;

  @override
  void initState() {
    super.initState();
    _configureDiagnostics();
    _configureRuntime();

    if (!widget.autoInitialize) return;
    final auth = _authController;
    if (auth != null) {
      unawaited(auth.bootstrap());
    } else {
      unawaited(_controller.initialize());
    }
  }

  void _configureDiagnostics() {
    final supplied = widget.diagnostics;
    if (supplied != null) {
      _diagnostics = supplied;
      _ownsDiagnostics = false;
    } else {
      final config = RuntimeDiagnosticsConfig.fromEnvironment();
      if (config.enabled) {
        _diagnostics = RuntimeDiagnostics(config: config);
        _ownsDiagnostics = true;
      }
    }

    final diagnostics = _diagnostics;
    if (diagnostics == null || !diagnostics.enabled) return;
    unawaited(diagnostics.initialize());
    _installGlobalErrorCapture(diagnostics);
  }

  void _installGlobalErrorCapture(RuntimeDiagnostics diagnostics) {
    _previousFlutterErrorHandler = FlutterError.onError;
    void flutterHandler(FlutterErrorDetails details) {
      diagnostics.recordUnexpected(
        details.exception,
        details.stack ?? StackTrace.current,
        operation: 'flutter.framework',
      );
      _previousFlutterErrorHandler?.call(details);
    }

    _installedFlutterErrorHandler = flutterHandler;
    FlutterError.onError = flutterHandler;

    _previousPlatformErrorHandler = PlatformDispatcher.instance.onError;
    bool platformHandler(Object error, StackTrace stack) {
      diagnostics.recordUnexpected(
        error,
        stack,
        operation: 'flutter.platform',
      );
      return _previousPlatformErrorHandler?.call(error, stack) ?? false;
    }

    _installedPlatformErrorHandler = platformHandler;
    PlatformDispatcher.instance.onError = platformHandler;
  }

  void _configureRuntime() {
    final suppliedLearning = widget.controller;
    final suppliedAuth = widget.authController;
    if (suppliedLearning != null) {
      _controller = suppliedLearning;
      _ownsController = false;
      _authController = suppliedAuth;
      _ownsAuthController = false;
      _notificationGateway = widget.notificationGateway ??
          const UnconfiguredStudentNotificationGateway();
      return;
    }

    final config = MobileBootstrapConfig.fromEnvironment();
    _config = config;

    // The synthetic bootstrap token exists only inside explicit fixture mode.
    // Production builds never consume a compile-time bearer credential.
    if (config.hasFixtureCredential) {
      _controller = _createFixtureLearningController(config);
      _ownsController = true;
      _authController = null;
      _ownsAuthController = false;
      final baseUrl = config.apiBaseUrl;
      _notificationGateway = baseUrl == null
          ? const UnconfiguredStudentNotificationGateway()
          : HttpStudentNotificationGateway(
              baseUrl: baseUrl,
              bearerToken: config.bearerToken,
              diagnostics: _diagnostics,
            );
      return;
    }

    final tokenProvider = MutableBearerToken();
    final recoveryScope = LearningRecoveryScope();
    const recoveryStorage = PlatformLearningRecoveryStorage();
    _tokenProvider = tokenProvider;
    _learningRecoveryScope = recoveryScope;
    _learningRecoveryStorage = recoveryStorage;
    _controller = _createProductionLearningController(
      config,
      tokenProvider,
      recoveryScope,
      recoveryStorage,
    );
    _ownsController = true;
    final baseUrl = config.apiBaseUrl;
    _notificationGateway = baseUrl == null
        ? const UnconfiguredStudentNotificationGateway()
        : HttpStudentNotificationGateway(
            baseUrl: baseUrl,
            bearerTokenProvider: tokenProvider.call,
            onAuthenticationRejected: _notifySessionRejected,
            diagnostics: _diagnostics,
          );
    final AuthGateway authGateway = baseUrl != null
        ? HttpAuthGateway(
            baseUrl: baseUrl,
            bearerTokenProvider: tokenProvider.call,
            diagnostics: _diagnostics,
          )
        : const UnconfiguredAuthGateway();
    _authController = MobileAuthController(
      gateway: authGateway,
      credentialStore: const PlatformSecureAuthCredentialStore(),
      tokenProvider: tokenProvider,
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: baseUrl != null,
      onSessionActivated: _onSessionActivated,
      canEndSession: _canEndCurrentSession,
      onExplicitSessionEnded: _onExplicitSessionEnded,
    );
    _ownsAuthController = true;
  }

  MobileLearningController _createFixtureLearningController(
    MobileBootstrapConfig config,
  ) {
    final baseUrl = config.apiBaseUrl;
    final LearningGateway gateway;
    final PendingSyncClient pendingSyncClient;
    if (baseUrl != null) {
      gateway = HttpLearningGateway(
        baseUrl: baseUrl,
        bearerToken: config.bearerToken,
        diagnostics: _diagnostics,
      );
      pendingSyncClient = HttpIssue14PendingSyncClient(
        baseUrl: baseUrl,
        bearerToken: config.bearerToken,
        diagnostics: _diagnostics,
      );
    } else {
      gateway = const UnconfiguredLearningGateway();
      pendingSyncClient = const DeferredIssue14SyncClient();
    }
    return MobileLearningController(
      gateway: gateway,
      config: config,
      pendingSyncClient: pendingSyncClient,
    );
  }

  MobileLearningController _createProductionLearningController(
    MobileBootstrapConfig config,
    MutableBearerToken tokenProvider,
    LearningRecoveryScope recoveryScope,
    LearningRecoveryStorage recoveryStorage,
  ) {
    final baseUrl = config.apiBaseUrl;
    final LearningGateway gateway;
    final PendingSyncClient pendingSyncClient;
    if (baseUrl != null) {
      gateway = HttpLearningGateway(
        baseUrl: baseUrl,
        bearerTokenProvider: tokenProvider.call,
        onAuthenticationRejected: _notifySessionRejected,
        onEmailVerificationRequired: _notifyEmailVerificationRequired,
        diagnostics: _diagnostics,
      );
      pendingSyncClient = HttpIssue14PendingSyncClient(
        baseUrl: baseUrl,
        bearerTokenProvider: tokenProvider.call,
        onAuthenticationRejected: _notifySessionRejected,
        diagnostics: _diagnostics,
      );
    } else {
      gateway = const UnconfiguredLearningGateway();
      pendingSyncClient = const DeferredIssue14SyncClient();
    }
    return MobileLearningController(
      gateway: gateway,
      config: config,
      downloadedContentCache: DurableDownloadedContentCache(
        storage: recoveryStorage,
        scope: recoveryScope,
      ),
      attemptSnapshotCache: DurableAttemptSnapshotCache(
        storage: recoveryStorage,
        scope: recoveryScope,
      ),
      pendingOperationStore: DurablePendingOperationStore(
        storage: recoveryStorage,
        scope: recoveryScope,
      ),
      pendingSyncClient: pendingSyncClient,
    );
  }

  Future<void> _onSessionActivated(
    String? previousAccountId,
    String currentAccountId,
  ) async {
    final recoveryScope = _learningRecoveryScope;
    if (recoveryScope != null) {
      recoveryScope.bind(currentAccountId);
    }
    if (previousAccountId != null && previousAccountId != currentAccountId) {
      // The replacement controller shares only the newly bound account scope;
      // the old account's persisted state remains inaccessible and can be
      // recovered only if that same account authenticates again.
      _replaceLearningController();
    }
    await _controller.initialize();
  }

  Future<bool> _canEndCurrentSession() async {
    await _controller.requestPendingCountRefresh();
    return _controller.pendingOperationCount == 0 &&
        !_controller.hasUnsavedAnswers;
  }

  Future<void> _onExplicitSessionEnded() async {
    final recoveryScope = _learningRecoveryScope;
    final recoveryStorage = _learningRecoveryStorage;
    final accountId = recoveryScope?.accountId;
    try {
      if (accountId != null && recoveryStorage != null) {
        await _controller.requestPendingCountRefresh();
        // Logout/deletion already uses the existing pending/draft guard. The
        // extra condition protects recovery data if another Auth path ends a
        // session without first invoking that guard (for example reset flow).
        if (_controller.pendingOperationCount == 0 &&
            !_controller.hasUnsavedAnswers) {
          await recoveryStorage.clearAccount(accountId);
        }
      }
    } finally {
      recoveryScope?.unbind();
      _replaceLearningController();
    }
  }

  void _replaceLearningController() {
    final config = _config;
    final tokenProvider = _tokenProvider;
    final recoveryScope = _learningRecoveryScope;
    final recoveryStorage = _learningRecoveryStorage;
    if (config == null ||
        tokenProvider == null ||
        recoveryScope == null ||
        recoveryStorage == null) {
      return;
    }
    if (_ownsController) {
      _controller.dispose();
    }
    _controller = _createProductionLearningController(
      config,
      tokenProvider,
      recoveryScope,
      recoveryStorage,
    );
    _ownsController = true;
    if (mounted) setState(() {});
  }

  void _notifySessionRejected() {
    final auth = _authController;
    if (auth != null) {
      unawaited(auth.handleSessionRejected());
    }
  }

  void _notifyEmailVerificationRequired() {
    _authController?.handleEmailVerificationRequired();
  }

  @override
  void dispose() {
    if (identical(FlutterError.onError, _installedFlutterErrorHandler)) {
      FlutterError.onError = _previousFlutterErrorHandler;
    }
    if (identical(
      PlatformDispatcher.instance.onError,
      _installedPlatformErrorHandler,
    )) {
      PlatformDispatcher.instance.onError = _previousPlatformErrorHandler;
    }
    if (_ownsController) {
      _controller.dispose();
    }
    if (_ownsAuthController) {
      _authController?.dispose();
    }
    if (_ownsDiagnostics) {
      _diagnostics?.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = _authController;
    final listenable = auth ?? _controller;
    return AnimatedBuilder(
      animation: listenable,
      builder: (context, _) {
        final locale = auth?.locale ?? _controller.locale;
        return MaterialApp(
          navigatorKey: _navigatorKey,
          title: 'MODRIK | مُدرك',
          debugShowCheckedModeBanner: false,
          theme: _themeFor(locale),
          builder: (context, child) {
            final diagnostics = _diagnostics;
            if (diagnostics == null || !diagnostics.enabled) {
              return child ?? const SizedBox.shrink();
            }
            final snapshot = _runtimeInspectorSnapshot(auth);
            return RuntimeInspectorHost(
              diagnostics: diagnostics,
              snapshot: snapshot,
              onOpen: () {
                final navigator = _navigatorKey.currentState;
                if (navigator == null) return;
                unawaited(
                  navigator.push(
                    MaterialPageRoute<void>(
                      builder: (_) => RuntimeInspectorScreen(
                        diagnostics: diagnostics,
                        snapshot: snapshot,
                      ),
                    ),
                  ),
                );
              },
              child: child ?? const SizedBox.shrink(),
            );
          },
          home: MobileNotificationLauncher(
            controller: _controller,
            gateway: _notificationGateway,
            child: auth == null
                ? AcademicContextResetBoundary(
                    controller: _controller,
                    child: MobileStudentShell(controller: _controller),
                  )
                : MobileAuthBoundary(
                    authController: auth,
                    learningController: _controller,
                  ),
          ),
        );
      },
    );
  }

  RuntimeInspectorSnapshot _runtimeInspectorSnapshot(MobileAuthController? auth) {
    final isOffline = _controller.status == MobileViewStatus.offline ||
        (auth?.isOfflineAuthenticated ?? false);
    final connectivity = isOffline
        ? DiagnosticConnectivity.offline
        : _controller.status == MobileViewStatus.loading
            ? DiagnosticConnectivity.unknown
            : DiagnosticConnectivity.online;
    final flow = auth != null && !auth.isAuthenticated
        ? 'auth.${auth.state.name}'
        : 'learning.${_controller.section.name}';
    final cacheCount =
        (_controller.hasLesson ? 1 : 0) + (_controller.hasAttempt ? 1 : 0);
    return RuntimeInspectorSnapshot(
      locale: (auth?.locale ?? _controller.locale).code,
      direction: (auth?.locale ?? _controller.locale) == ModrikLocale.ar
          ? TextDirection.rtl
          : TextDirection.ltr,
      connectivity: connectivity,
      currentFlow: flow,
      pendingSyncCount: _controller.pendingOperationCount,
      cacheItemCount: cacheCount,
    );
  }
}

ThemeData _themeFor(ModrikLocale locale) {
  final family = locale == ModrikLocale.ar
      ? ModrikTypography.arabicFamily
      : ModrikTypography.latinFamily;
  final colors = const ColorScheme.light(
    primary: ModrikColors.teal,
    onPrimary: ModrikColors.navy,
    secondary: ModrikColors.blue,
    onSecondary: ModrikColors.white,
    surface: ModrikColors.white,
    onSurface: ModrikColors.ink,
    error: ModrikColors.error,
    onError: ModrikColors.white,
  );
  final minimumTarget = const WidgetStatePropertyAll(Size(48, 48));

  return ThemeData(
    useMaterial3: true,
    colorScheme: colors,
    scaffoldBackgroundColor: ModrikColors.background,
    fontFamily: family,
    materialTapTargetSize: MaterialTapTargetSize.padded,
    visualDensity: VisualDensity.standard,
    focusColor: ModrikColors.teal,
    textSelectionTheme: const TextSelectionThemeData(
      cursorColor: ModrikColors.blue,
      selectionColor: ModrikColors.sky,
      selectionHandleColor: ModrikColors.blue,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: ButtonStyle(
        minimumSize: minimumTarget,
        padding: const WidgetStatePropertyAll(
          EdgeInsets.symmetric(horizontal: 18, vertical: 13),
        ),
        shape: WidgetStatePropertyAll(
          RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(ModrikRadii.small),
          ),
        ),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: ButtonStyle(
        minimumSize: minimumTarget,
        padding: const WidgetStatePropertyAll(
          EdgeInsets.symmetric(horizontal: 18, vertical: 13),
        ),
        shape: WidgetStatePropertyAll(
          RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(ModrikRadii.small),
          ),
        ),
      ),
    ),
    iconButtonTheme: IconButtonThemeData(
      style: ButtonStyle(minimumSize: minimumTarget),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: ModrikColors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(ModrikRadii.small),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(ModrikRadii.small),
        borderSide: const BorderSide(color: ModrikColors.teal, width: 2),
      ),
    ),
    navigationBarTheme: const NavigationBarThemeData(
      backgroundColor: ModrikColors.white,
      indicatorColor: ModrikColors.sky,
    ),
  );
}
