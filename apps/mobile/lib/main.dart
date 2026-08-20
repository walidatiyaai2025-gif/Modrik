import 'dart:async';

import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'src/academic_context_reset_boundary.dart';
import 'src/app_shell.dart';
import 'src/issue14_sync_client.dart';
import 'src/learning_gateway.dart';
import 'src/mobile_learning_controller.dart';
import 'src/models.dart';
import 'src/offline_boundary.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const ModrikApp());
}

class ModrikApp extends StatefulWidget {
  const ModrikApp({super.key, this.controller, this.autoInitialize = true});

  final MobileLearningController? controller;
  final bool autoInitialize;

  @override
  State<ModrikApp> createState() => _ModrikAppState();
}

class _ModrikAppState extends State<ModrikApp> {
  late final MobileLearningController _controller;
  late final bool _ownsController;

  @override
  void initState() {
    super.initState();
    _ownsController = widget.controller == null;
    final supplied = widget.controller;
    if (supplied != null) {
      _controller = supplied;
    } else {
      final config = MobileBootstrapConfig.fromEnvironment();
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
      _controller = MobileLearningController(
        gateway: gateway,
        config: config,
        pendingSyncClient: pendingSyncClient,
      );
    }
    if (widget.autoInitialize) {
      unawaited(_controller.initialize());
    }
  }

  @override
  void dispose() {
    if (_ownsController) {
      _controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) => MaterialApp(
        title: 'MODRIK | مُدرك',
        debugShowCheckedModeBanner: false,
        theme: _themeFor(_controller.locale),
        home: AcademicContextResetBoundary(
          controller: _controller,
          child: MobileStudentShell(controller: _controller),
        ),
      ),
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
