import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/runtime_diagnostics.dart';

const _enabledConfig = RuntimeDiagnosticsConfig(
  enabled: true,
  environment: 'pilot',
  build: '17',
  version: '1.0.0',
  commit: 'abc123',
);

void main() {
  test('production-default disabled diagnostics record nothing', () {
    const disabled = RuntimeDiagnosticsConfig(
      enabled: false,
      environment: 'production',
      build: 'unknown',
      version: 'unknown',
      commit: 'unknown',
    );
    final diagnostics = RuntimeDiagnostics(
      config: disabled,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );

    diagnostics.record(
      severity: DiagnosticSeverity.error,
      category: 'network',
      correlationId: 'mcr-disabled',
      operation: 'learning.session',
      result: 'failure',
    );

    expect(diagnostics.events, isEmpty);
  });

  test('ring buffer evicts oldest events and persists bounded safe state', () async {
    final persistence = MemoryRuntimeDiagnosticsPersistence();
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
      maxEvents: 3,
      maxBytes: 4096,
    );
    await diagnostics.initialize();

    for (var index = 0; index < 5; index += 1) {
      diagnostics.record(
        severity: DiagnosticSeverity.info,
        category: 'network',
        correlationId: 'mcr-$index',
        operation: 'learning.session',
        result: 'success',
        metadata: {'duration_ms': index},
      );
    }
    await Future<void>.delayed(Duration.zero);

    expect(diagnostics.events, hasLength(3));
    expect(diagnostics.events.first.correlationId, 'mcr-2');
    expect(utf8.encode(persistence.encoded!).length, lessThanOrEqualTo(4096));
  });

  test('persisted bounded timeline restores after restart', () async {
    final directory = await Directory.systemTemp.createTemp('modrik-diagnostics-test-');
    final file = File('${directory.path}/timeline.json');
    final persistence = FileRuntimeDiagnosticsPersistence(file: file);
    addTearDown(() async {
      if (await directory.exists()) await directory.delete(recursive: true);
    });

    final first = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
      maxEvents: 4,
    );
    await first.initialize();
    first.record(
      severity: DiagnosticSeverity.warn,
      category: 'connectivity',
      correlationId: 'mcr-restart',
      operation: 'learning.refresh',
      result: 'offline',
      connectivity: DiagnosticConnectivity.offline,
    );
    await Future<void>.delayed(const Duration(milliseconds: 30));

    final restarted = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
      maxEvents: 4,
    );
    await restarted.initialize();

    expect(restarted.events, hasLength(1));
    expect(restarted.events.single.correlationId, 'mcr-restart');
    expect(restarted.events.single.connectivity, DiagnosticConnectivity.offline);
  });

  test('sensitive sentinel values never enter storage or export', () async {
    const bearer = 'Bearer SENTINEL-BEARER-CREDENTIAL';
    const password = 'SENTINEL-password-value';
    const learnerAnswer = 'SENTINEL learner answer: secret option B';
    const question = 'SENTINEL question text: identify the answer';
    const email = 'sentinel.student@example.test';
    const name = 'SENTINEL Learner Name';

    final persistence = MemoryRuntimeDiagnosticsPersistence();
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
    );
    await diagnostics.initialize();
    diagnostics.record(
      severity: DiagnosticSeverity.error,
      category: 'network',
      correlationId: bearer,
      operation: 'learning.answer',
      result: 'failure',
      stableCode: 'ANSWER_REVISION_CONFLICT',
      metadata: {
        'source': bearer,
        'answer': learnerAnswer,
        'question': question,
        'email': email,
        'name': name,
        'password': password,
        'pending_count': 2,
      },
    );
    await Future<void>.delayed(Duration.zero);

    final exported = diagnostics.exportSanitizedJson(
      locale: 'en',
      direction: 'ltr',
      connectivity: DiagnosticConnectivity.online,
      currentFlow: 'practice',
      pendingSyncCount: 2,
    );
    final stored = persistence.encoded ?? '';

    for (final sentinel in [bearer, password, learnerAnswer, question, email, name]) {
      expect(stored, isNot(contains(sentinel)));
      expect(exported, isNot(contains(sentinel)));
    }
    expect(exported, contains('ANSWER_REVISION_CONFLICT'));
    expect(exported, contains('pending_sync_count'));
  });

  test('unexpected exception capture stores fingerprint not raw exception', () {
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );
    const sentinel = 'SENTINEL learner answer must never be logged';

    diagnostics.recordUnexpected(
      StateError(sentinel),
      StackTrace.fromString('#0 safe_frame (runtime_diagnostics_test.dart:1:1)'),
    );

    final event = diagnostics.events.single;
    expect(event.stableCode, 'MOBILE_UNEXPECTED_ERROR');
    expect(event.fingerprint, startsWith('fp-'));
    expect(jsonEncode(event.toJson()), isNot(contains(sentinel)));
  });

  test('correlation IDs are diagnostic-only opaque values', () {
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );

    final first = diagnostics.newCorrelationId();
    final second = diagnostics.newCorrelationId();

    expect(first, matches(RegExp(r'^mcr-[0-9a-f]{32}$')));
    expect(second, matches(RegExp(r'^mcr-[0-9a-f]{32}$')));
    expect(second, isNot(first));
  });
}
