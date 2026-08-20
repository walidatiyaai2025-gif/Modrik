import 'dart:io';

import 'runtime_correlation.dart';
import 'runtime_diagnostics.dart';

class RuntimeDiagnosticTransportAttempt {
  RuntimeDiagnosticTransportAttempt._({
    required RuntimeDiagnostics? diagnostics,
    required this.operation,
  })  : _diagnostics = diagnostics,
        _started = Stopwatch()..start(),
        _correlationId = diagnostics?.enabled == true
            ? createDiagnosticCorrelationId()
            : null;

  factory RuntimeDiagnosticTransportAttempt.start(
    RuntimeDiagnostics? diagnostics,
    String operation,
  ) {
    return RuntimeDiagnosticTransportAttempt._(
      diagnostics: diagnostics,
      operation: sanitizeDiagnosticIdentifier(operation),
    );
  }

  final RuntimeDiagnostics? _diagnostics;
  final Stopwatch _started;
  final String operation;
  String? _correlationId;
  bool _recorded = false;

  String? get correlationId => _correlationId;

  void attach(HttpClientRequest request) {
    final correlationId = _correlationId;
    if (correlationId != null) {
      request.headers.set(diagnosticCorrelationHeader, correlationId);
    }
  }

  void acceptResponse(HttpClientResponse response) {
    final current = _correlationId;
    if (current == null) return;
    final candidate = response.headers.value(diagnosticCorrelationHeader) ??
        response.headers.value(diagnosticFallbackCorrelationHeader);
    _correlationId = validDiagnosticCorrelationId(candidate) ?? current;
  }

  void success({required int status}) {
    _record(
      severity: DiagnosticSeverity.info,
      result: 'success',
      connectivity: DiagnosticConnectivity.online,
      metadata: {
        'duration_ms': _elapsedMilliseconds(),
        'http_status': status,
        'status_class': '${status ~/ 100}xx',
      },
    );
  }

  void backendFailure({
    required int status,
    required String stableCode,
    required bool retryable,
  }) {
    _record(
      severity: status >= 500 ? DiagnosticSeverity.error : DiagnosticSeverity.warn,
      result: status == 401 ? 'authentication_rejected' : 'backend_failure',
      stableCode: stableCode,
      connectivity: DiagnosticConnectivity.online,
      metadata: {
        'duration_ms': _elapsedMilliseconds(),
        'http_status': status,
        'retryable': retryable,
        'status_class': '${status ~/ 100}xx',
      },
    );
  }

  void offline({String stableCode = 'MOBILE_NETWORK_OFFLINE'}) {
    _record(
      severity: DiagnosticSeverity.warn,
      result: 'offline',
      stableCode: stableCode,
      connectivity: DiagnosticConnectivity.offline,
      metadata: {
        'duration_ms': _elapsedMilliseconds(),
        'retryable': true,
      },
    );
  }

  void transportFailure({
    String stableCode = 'MOBILE_NETWORK_ERROR',
    bool retryable = true,
  }) {
    _record(
      severity: DiagnosticSeverity.error,
      result: 'transport_failure',
      stableCode: stableCode,
      connectivity: DiagnosticConnectivity.unknown,
      metadata: {
        'duration_ms': _elapsedMilliseconds(),
        'retryable': retryable,
      },
    );
  }

  void invalidResponse({required String stableCode}) {
    _record(
      severity: DiagnosticSeverity.error,
      result: 'invalid_response',
      stableCode: stableCode,
      connectivity: DiagnosticConnectivity.online,
      metadata: {'duration_ms': _elapsedMilliseconds()},
    );
  }

  void _record({
    required DiagnosticSeverity severity,
    required String result,
    required DiagnosticConnectivity connectivity,
    String? stableCode,
    Map<String, Object?> metadata = const {},
  }) {
    if (_recorded) return;
    _recorded = true;
    _started.stop();
    final diagnostics = _diagnostics;
    final correlationId = _correlationId;
    if (diagnostics == null || correlationId == null) return;
    diagnostics.record(
      severity: severity,
      category: 'transport',
      correlationId: correlationId,
      operation: operation,
      result: result,
      connectivity: connectivity,
      stableCode: stableCode,
      metadata: metadata,
    );
  }

  int _elapsedMilliseconds() {
    final elapsed = _started.elapsedMilliseconds;
    return elapsed.clamp(0, 600000);
  }
}

String diagnosticOperationName(String surface, String method, String path) {
  final segments = path
      .split('/')
      .where((segment) => segment.isNotEmpty)
      .toList(growable: false);
  final safeSegments = <String>[];
  for (final segment in segments) {
    if (_looksOpaqueResourceId(segment)) continue;
    safeSegments.add(sanitizeDiagnosticIdentifier(segment));
    if (safeSegments.length == 3) break;
  }
  final suffix = safeSegments.isEmpty ? 'root' : safeSegments.join('.');
  return '$surface.${method.toLowerCase()}.$suffix';
}

bool _looksOpaqueResourceId(String segment) {
  if (segment.length >= 20 && RegExp(r'^[A-Za-z0-9_-]+$').hasMatch(segment)) {
    return true;
  }
  return RegExp(r'^[0-9]+$').hasMatch(segment);
}
