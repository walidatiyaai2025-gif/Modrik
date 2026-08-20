import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/runtime_diagnostics.dart';
import 'package:modrik_mobile/src/runtime_inspector.dart';

RuntimeDiagnostics _diagnostics({bool enabled = true}) => RuntimeDiagnostics(
      config: RuntimeDiagnosticsConfig(
        enabled: enabled,
        environment: enabled ? 'pilot' : 'production',
        build: '5',
        version: '1.0.0',
        commit: 'widget-test',
      ),
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );

const _snapshotEn = RuntimeInspectorSnapshot(
  locale: 'en',
  direction: TextDirection.ltr,
  connectivity: DiagnosticConnectivity.online,
  currentFlow: 'learning.practice',
  pendingSyncCount: 2,
  cacheItemCount: 1,
);

void main() {
  testWidgets('disabled production diagnostics expose no inspector UI', (tester) async {
    final diagnostics = _diagnostics(enabled: false);
    await tester.pumpWidget(
      MaterialApp(
        home: RuntimeInspectorHost(
          diagnostics: diagnostics,
          snapshot: _snapshotEn,
          child: const Scaffold(body: Text('Normal learning surface')),
        ),
      ),
    );

    expect(find.text('Normal learning surface'), findsOneWidget);
    expect(find.byIcon(Icons.monitor_heart_outlined), findsNothing);
    expect(find.text('Runtime Inspector'), findsNothing);
  });

  testWidgets('enabled inspector shows safe runtime summary and filters', (tester) async {
    final diagnostics = _diagnostics();
    diagnostics.record(
      severity: DiagnosticSeverity.error,
      category: 'transport',
      correlationId: 'srv-support-42',
      operation: 'learning.get.session',
      result: 'backend_failure',
      stableCode: 'AUTHENTICATION_REQUIRED',
      connectivity: DiagnosticConnectivity.online,
    );

    await tester.pumpWidget(
      MaterialApp(
        home: RuntimeInspectorHost(
          diagnostics: diagnostics,
          snapshot: _snapshotEn,
          child: const Scaffold(body: Text('Normal learning surface')),
        ),
      ),
    );
    await tester.tap(find.byIcon(Icons.monitor_heart_outlined));
    await tester.pumpAndSettle();

    expect(find.text('Runtime Inspector'), findsOneWidget);
    expect(find.text('pilot'), findsOneWidget);
    expect(find.text('srv-support-42'), findsOneWidget);
    expect(find.textContaining('AUTHENTICATION_REQUIRED'), findsOneWidget);
    expect(find.text('Copy JSON'), findsOneWidget);
    expect(find.text('Export JSON'), findsOneWidget);
    expect(find.text('Clear diagnostics'), findsOneWidget);
    expect(find.textContaining('Bearer '), findsNothing);
  });

  testWidgets('app root navigator opens inspector from MaterialApp builder overlay', (tester) async {
    final diagnostics = _diagnostics();
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: const MobileBootstrapConfig(apiBaseUrl: null),
    )..status = MobileViewStatus.permission;

    await tester.pumpWidget(
      ModrikApp(
        controller: controller,
        diagnostics: diagnostics,
        autoInitialize: false,
      ),
    );
    await tester.tap(find.byIcon(Icons.monitor_heart_outlined));
    await tester.pumpAndSettle();

    expect(find.text('Runtime Inspector'), findsOneWidget);
    expect(find.text('Runtime summary'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Arabic RTL inspector survives 320px and 200 percent text', (tester) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final diagnostics = _diagnostics();
    diagnostics.record(
      severity: DiagnosticSeverity.warn,
      category: 'connectivity',
      correlationId: 'mcr-ar-1',
      operation: 'learning.refresh',
      result: 'offline',
      connectivity: DiagnosticConnectivity.offline,
    );
    const snapshot = RuntimeInspectorSnapshot(
      locale: 'ar',
      direction: TextDirection.rtl,
      connectivity: DiagnosticConnectivity.offline,
      currentFlow: 'learning.study',
      pendingSyncCount: 3,
      cacheItemCount: 2,
    );

    await tester.pumpWidget(
      MediaQuery(
        data: const MediaQueryData(textScaler: TextScaler.linear(2)),
        child: MaterialApp(
          home: RuntimeInspectorHost(
            diagnostics: diagnostics,
            snapshot: snapshot,
            child: const Scaffold(body: Text('تعلم')),
          ),
        ),
      ),
    );
    await tester.tap(find.byIcon(Icons.monitor_heart_outlined));
    await tester.pumpAndSettle();

    expect(find.text('فاحص التشغيل'), findsOneWidget);
    expect(find.text('ملخص التشغيل'), findsOneWidget);
    expect(find.text('سجل التشخيصات الأخيرة'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('French inspector labels remain available', (tester) async {
    final diagnostics = _diagnostics();
    const snapshot = RuntimeInspectorSnapshot(
      locale: 'fr',
      direction: TextDirection.ltr,
      connectivity: DiagnosticConnectivity.unknown,
      currentFlow: 'auth.signedOut',
      pendingSyncCount: 0,
      cacheItemCount: 0,
    );
    await tester.pumpWidget(
      MaterialApp(
        home: RuntimeInspectorHost(
          diagnostics: diagnostics,
          snapshot: snapshot,
          child: const Scaffold(body: Text('Apprentissage')),
        ),
      ),
    );
    await tester.tap(find.byIcon(Icons.monitor_heart_outlined));
    await tester.pumpAndSettle();

    expect(find.text('Inspecteur d’exécution'), findsOneWidget);
    expect(find.text('Résumé d’exécution'), findsOneWidget);
    expect(find.text('Copier le JSON'), findsOneWidget);
  });
}
