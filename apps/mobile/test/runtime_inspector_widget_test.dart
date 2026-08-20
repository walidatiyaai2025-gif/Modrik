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

const _supportCorrelation = '99999999-9999-4999-8999-999999999999';

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
      correlationId: _supportCorrelation,
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
    expect(find.text(_supportCorrelation), findsOneWidget);
    expect(find.text('Copy JSON'), findsOneWidget);
    expect(find.text('Export JSON'), findsOneWidget);
    expect(find.text('Clear diagnostics'), findsOneWidget);
    expect(find.textContaining('Bearer '), findsNothing);

    final stableCode = find.textContaining('AUTHENTICATION_REQUIRED');
    await tester.dragUntilVisible(
      stableCode,
      find.byType(ListView),
      const Offset(0, -250),
    );
    expect(stableCode, findsOneWidget);
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
      correlationId: _supportCorrelation,
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
    final timeline = find.text('سجل التشخيصات الأخيرة');
    await tester.dragUntilVisible(
      timeline,
      find.byType(ListView),
      const Offset(0, -300),
    );
    expect(timeline, findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('French inspector survives 320px and 200 percent text with reachable actions', (tester) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final diagnostics = _diagnostics();
    diagnostics.record(
      severity: DiagnosticSeverity.error,
      category: 'transport',
      correlationId: _supportCorrelation,
      operation: 'learning.get.session',
      result: 'backend_failure',
      stableCode: 'AUTHENTICATION_REQUIRED',
      connectivity: DiagnosticConnectivity.online,
    );
    const snapshot = RuntimeInspectorSnapshot(
      locale: 'fr',
      direction: TextDirection.ltr,
      connectivity: DiagnosticConnectivity.online,
      currentFlow: 'auth.signedOut',
      pendingSyncCount: 2,
      cacheItemCount: 1,
    );

    await tester.pumpWidget(
      MediaQuery(
        data: const MediaQueryData(textScaler: TextScaler.linear(2)),
        child: MaterialApp(
          home: RuntimeInspectorHost(
            diagnostics: diagnostics,
            snapshot: snapshot,
            child: const Scaffold(body: Text('Apprentissage')),
          ),
        ),
      ),
    );
    await tester.tap(find.byIcon(Icons.monitor_heart_outlined));
    await tester.pumpAndSettle();

    expect(find.text('Inspecteur d’exécution'), findsOneWidget);
    expect(find.text('Résumé d’exécution'), findsOneWidget);
    expect(find.text(_supportCorrelation), findsOneWidget);

    for (final label in [
      'Copier le JSON',
      'Exporter le JSON',
      'Effacer les diagnostics',
    ]) {
      final text = find.text(label);
      await tester.ensureVisible(text);
      final button = find.ancestor(of: text, matching: find.byType(OutlinedButton));
      expect(button, findsOneWidget);
      expect(tester.getSize(button).height, greaterThanOrEqualTo(48));
    }

    final correlationFilter = find.text('ID de corrélation');
    await tester.ensureVisible(correlationFilter);
    expect(correlationFilter, findsOneWidget);

    final timeline = find.text('Chronologie récente des diagnostics');
    await tester.dragUntilVisible(
      timeline,
      find.byType(ListView),
      const Offset(0, -300),
    );
    expect(timeline, findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
