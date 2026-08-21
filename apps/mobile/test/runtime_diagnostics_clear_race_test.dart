import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/runtime_diagnostics.dart';

const _enabledConfig = RuntimeDiagnosticsConfig(
  enabled: true,
  environment: 'pilot',
  build: '17',
  version: '1.0.0',
  commit: 'abc123',
);

const _correlation = '11111111-1111-4111-8111-111111111111';

void main() {
  test('clear cannot be overwritten by an older queued persistence write', () async {
    final persistence = _DelayedWritePersistence();
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
    );
    await diagnostics.initialize();

    diagnostics.record(
      severity: DiagnosticSeverity.info,
      category: 'transport',
      correlationId: _correlation,
      operation: 'learning.get.session',
      result: 'success',
    );

    await persistence.writeStarted.future;
    final clearFuture = diagnostics.clear();

    expect(diagnostics.events, isEmpty);

    persistence.releaseWrite.complete();
    await persistence.writeFinished.future;
    await clearFuture;

    expect(persistence.clearCalls, 1);
    expect(persistence.encoded, isNull);
  });
}

class _DelayedWritePersistence implements RuntimeDiagnosticsPersistence {
  final writeStarted = Completer<void>();
  final releaseWrite = Completer<void>();
  final writeFinished = Completer<void>();

  String? encoded;
  int clearCalls = 0;

  @override
  Future<String?> read() async => encoded;

  @override
  Future<void> write(String value) async {
    if (!writeStarted.isCompleted) writeStarted.complete();
    await releaseWrite.future;
    encoded = value;
    if (!writeFinished.isCompleted) writeFinished.complete();
  }

  @override
  Future<void> clear() async {
    clearCalls += 1;
    encoded = null;
  }
}
