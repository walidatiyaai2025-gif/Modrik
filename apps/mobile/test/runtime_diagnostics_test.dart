import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/runtime_correlation.dart';
import 'package:modrik_mobile/src/runtime_diagnostics.dart';

const _enabledConfig = RuntimeDiagnosticsConfig(
  enabled: true,
  environment: 'pilot',
  build: '17',
  version: '1.0.0',
  commit: 'abc123',
);

const _correlationA = '11111111-1111-4111-8111-111111111111';
const _correlationB = '22222222-2222-4222-8222-222222222222';
const _correlationC = '33333333-3333-4333-8333-333333333333';
const _correlationD = '44444444-4444-4444-8444-444444444444';
const _correlationE = '55555555-5555-4555-8555-555555555555';

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
      correlationId: _correlationA,
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

    for (final correlationId in [
      _correlationA,
      _correlationB,
      _correlationC,
      _correlationD,
      _correlationE,
    ]) {
      diagnostics.record(
        severity: DiagnosticSeverity.info,
        category: 'network',
        correlationId: correlationId,
        operation: 'learning.session',
        result: 'success',
        metadata: {'duration_ms': diagnostics.events.length},
      );
    }
    await Future<void>.delayed(Duration.zero);

    expect(diagnostics.events, hasLength(3));
    expect(diagnostics.events.first.correlationId, _correlationC);
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
      correlationId: _correlationA,
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
    expect(restarted.events.single.correlationId, _correlationA);
    expect(restarted.events.single.connectivity, DiagnosticConnectivity.offline);
  });

  test('malformed persisted diagnostics reset safely and emit recovery classification', () async {
    final persistence = MemoryRuntimeDiagnosticsPersistence()
      ..encoded = '{not valid json';
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
    );

    await diagnostics.initialize();
    await Future<void>.delayed(Duration.zero);

    expect(diagnostics.events, hasLength(1));
    expect(diagnostics.events.single.category, 'storage');
    expect(diagnostics.events.single.result, 'recovered');
    expect(
      diagnostics.events.single.stableCode,
      'MOBILE_DIAGNOSTICS_STORAGE_RECOVERED',
    );
    expect(persistence.encoded, contains('MOBILE_DIAGNOSTICS_STORAGE_RECOVERED'));
  });

  test('read cleanup and recovery-write failures never escape initialization', () async {
    final persistence = _ThrowingRuntimeDiagnosticsPersistence(
      throwOnRead: true,
      throwOnWrite: true,
      throwOnClear: true,
    );
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
    );

    await expectLater(diagnostics.initialize(), completes);
    await Future<void>.delayed(Duration.zero);

    expect(diagnostics.initialized, isTrue);
    expect(diagnostics.events, hasLength(1));
    expect(
      diagnostics.events.single.stableCode,
      'MOBILE_DIAGNOSTICS_STORAGE_RECOVERED',
    );
    expect(persistence.readCalls, 1);
    expect(persistence.clearCalls, 1);
    expect(persistence.writeCalls, 1);
  });

  test('manual diagnostic clear remains fail open when storage clear fails', () async {
    final persistence = _ThrowingRuntimeDiagnosticsPersistence(
      throwOnClear: true,
    );
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
    );
    await diagnostics.initialize();
    diagnostics.record(
      severity: DiagnosticSeverity.info,
      category: 'transport',
      correlationId: _correlationA,
      operation: 'learning.get.session',
      result: 'success',
    );

    await expectLater(diagnostics.clear(), completes);

    expect(diagnostics.events, isEmpty);
    expect(persistence.clearCalls, 1);
  });

  test('asynchronous diagnostic write failure does not mutate the timeline', () async {
    final persistence = _ThrowingRuntimeDiagnosticsPersistence(
      throwOnWrite: true,
    );
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
    );
    await diagnostics.initialize();

    diagnostics.record(
      severity: DiagnosticSeverity.info,
      category: 'transport',
      correlationId: _correlationA,
      operation: 'learning.get.academic-tracks',
      result: 'success',
    );
    await Future<void>.delayed(Duration.zero);

    expect(diagnostics.events, hasLength(1));
    expect(diagnostics.events.single.result, 'success');
    expect(persistence.writeCalls, 1);
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
    expect(exported, isNot(contains('source')));
  });

  test('local JSON export is sanitized and clear removes only diagnostics', () async {
    final persistence = MemoryRuntimeDiagnosticsPersistence();
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: persistence,
    );
    await diagnostics.initialize();
    diagnostics.record(
      severity: DiagnosticSeverity.warn,
      category: 'sync',
      correlationId: _correlationA,
      operation: 'sync.acknowledgements',
      result: 'conflict',
      stableCode: 'ANSWER_REVISION_CONFLICT',
      metadata: {'pending_count': 1, 'retryable': false},
    );

    final path = await diagnostics.writeSanitizedExport(
      locale: 'ar',
      direction: 'rtl',
      connectivity: DiagnosticConnectivity.online,
      currentFlow: 'learning.practice',
      pendingSyncCount: 1,
      cacheItemCount: 2,
    );
    expect(path, isNotNull);
    final exportFile = File(path!);
    addTearDown(() async {
      if (await exportFile.exists()) await exportFile.delete();
    });
    final exported = await exportFile.readAsString();
    expect(exported, contains(_correlationA));
    expect(exported, contains('ANSWER_REVISION_CONFLICT'));
    expect(exported, contains('"locale":"ar"'));

    await diagnostics.clear();
    expect(diagnostics.events, isEmpty);
    expect(persistence.encoded, isNull);
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

  test('correlation IDs use canonical safe diagnostics envelope', () {
    final diagnostics = RuntimeDiagnostics(
      config: _enabledConfig,
      persistence: MemoryRuntimeDiagnosticsPersistence(),
    );

    final first = diagnostics.newCorrelationId();
    final second = diagnostics.newCorrelationId();

    expect(validDiagnosticCorrelationId(first), first);
    expect(validDiagnosticCorrelationId(second), second);
    expect(first, matches(RegExp(r'^[0-9a-f-]{36}$')));
    expect(second, isNot(first));
    expect(validDiagnosticCorrelationId('not/a/correlation/id'), isNull);
  });
}

class _ThrowingRuntimeDiagnosticsPersistence
    implements RuntimeDiagnosticsPersistence {
  _ThrowingRuntimeDiagnosticsPersistence({
    this.throwOnRead = false,
    this.throwOnWrite = false,
    this.throwOnClear = false,
  });

  final bool throwOnRead;
  final bool throwOnWrite;
  final bool throwOnClear;
  int readCalls = 0;
  int writeCalls = 0;
  int clearCalls = 0;

  @override
  Future<String?> read() async {
    readCalls += 1;
    if (throwOnRead) {
      throw StateError('injected diagnostics read failure');
    }
    return null;
  }

  @override
  Future<void> write(String encoded) async {
    writeCalls += 1;
    if (throwOnWrite) {
      throw StateError('injected diagnostics write failure');
    }
  }

  @override
  Future<void> clear() async {
    clearCalls += 1;
    if (throwOnClear) {
      throw StateError('injected diagnostics clear failure');
    }
  }
}
