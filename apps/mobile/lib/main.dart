import 'dart:async';

import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'src/academic_context_reset_boundary.dart';
import 'src/app_shell.dart';
import 'src/auth_gateway.dart';
import 'src/auth_shell.dart';
import 'src/issue14_sync_client.dart';
import 'src/learning_gateway.dart';
import 'src/mobile_auth_controller.dart';
import 'src/mobile_learning_controller.dart';
import 'src/models.dart';
import 'src/offline_boundary.dart';
import 'src/provider_auth_launcher.dart';
import 'src/secure_session_store.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const ModrikApp());
}

class ModrikApp extends StatefulWidget {
  const ModrikApp({
    super.key,
    this.controller,
    this.authController,
    this.autoInitialize = true,
  });

  /// Supplying only the learning controller preserves the isolated fixture/test
  /// harness. Production startup creates and owns both controllers instead.
  final MobileLearningController? controller;
  final MobileAuthController? authController;
  final bool autoInitialize;

  @override
  State<ModrikApp> createState() => _ModrikAppState();
}

class _ModrikAppState extends State<ModrikApp> {
  late MobileLearningController _controller;
  MobileAuthController? _authController;
  late bool _ownsController;
  late bool _ownsAuthController;
  MobileBootstrapConfig? _config;
  MutableBearerToken? _tokenProvider;

  @override
  void initState() {
    super.initState();
    _configureRuntime();

    if (!widget.autoInitialize) return;
    final auth = _authController;
    if (auth != null) {
      unawaited(auth.bootstrap());
    } else {
      unawaited(_controller.initialize());
    }
  }

  void _configureRuntime() {
    final suppliedLearning = widget.controller;
    final suppliedAuth = widget.authController;
    if (suppliedLearning != null) {
      _controller = suppliedLearning;
      _ownsController = false;
      _authController = suppliedAuth;
      _ownsAuthController = false;
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
      return;
    }

    final tokenProvider = MutableBearerToken();
    _tokenProvider = tokenProvider;
    _controller = _createProductionLearningController(config, tokenProvider);
    _ownsController = true;
    _authController = MobileAuthController(
      gateway: config.apiBaseUrl case final baseUrl?
          ? HttpAuthGateway(
              baseUrl: baseUrl,
              bearerTokenProvider: tokenProvider.call,
            )
          : const UnconfiguredAuthGateway(),
      credentialStore: const PlatformSecureAuthCredentialStore(),
      tokenProvider: tokenProvider,
      providerLauncher: const UnconfiguredProviderAuthLauncher(),
      apiConfigured: config.apiBaseUrl != null,
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
      );
      pendingSyncClient = HttpIssue14PendingSyncClient(
        baseUrl: baseUrl,
        bearerToken: config.bearerToken,
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
      );
      pendingSyncClient = HttpIssue14PendingSyncClient(
        baseUrl: baseUrl,
        bearerTokenProvider: tokenProvider.call,
        onAuthenticationRejected: _notifySessionRejected,
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

  Future<void> _onSessionActivated(
    String? previousAccountId,
    String currentAccountId,
  ) async {
    if (previousAccountId != null && previousAccountId != currentAccountId) {
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
    _replaceLearningController();
  }

  void _replaceLearningController() {
    final config = _config;
    final tokenProvider = _tokenProvider;
    if (config == null || tokenProvider == null) return;
    if (_ownsController) {
      _controller.dispose();
    }
    _controller = _createProductionLearningController(config, tokenProvider);
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
    if (_ownsController) {
      _controller.dispose();
    }
    if (_ownsAuthController) {
      _authController?.dispose();
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
          title: 'MODRIK | مُدرك',
          debugShowCheckedModeBanner: false,
          theme: _themeFor(locale),
          home: auth == null
              ? AcademicContextResetBoundary(
                  controller: _controller,
                  child: MobileStudentShell(controller: _controller),
                )
              : MobileAuthBoundary(
                  authController: auth,
                  learningController: _controller,
                ),
        );
      },
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
