import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';

import 'runtime_correlation.dart';

enum DiagnosticSeverity { debug, info, warn, error, critical }

enum DiagnosticConnectivity { unknown, online, offline }

const mobileCorrelationHeader = diagnosticCorrelationHeader;
const _fallbackCorrelationHeader = diagnosticFallbackCorrelationHeader;

final RegExp _safeIdentifierPattern = RegExp(r'^[A-Za-z0-9._:/-]{1,128}$');
final RegExp _emailPattern = RegExp(
  r'\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b',
  caseSensitive: false,
);

const Set<String> _safeMetadataKeys = {
  'cache_count',
  'duration_ms',
  'http_status',
  'operation_count',
  'pending_count',
  'retryable',
  'status_class',
};

const List<String> _sensitiveKeyFragments = [
  'answer',
  'authorization',
  'cookie',
  'email',
  'name',
  'password',
  'payload',
  'question',
  'secret',
  'token',
];

const List<String> _sensitiveValueMarkers = [
  'bearer ',
  'password=',
  'password:',
  'secret=',
  'secret:',
  'token=',
  'token:',
  'cookie=',
  'cookie:',
];

class RuntimeDiagnosticsConfig {
  const RuntimeDiagnosticsConfig({
    required this.enabled,
    required this.environment,
    required this.build,
    required this.version,
    required this.commit,
  });

  factory RuntimeDiagnosticsConfig.fromEnvironment() {
    const enabled = bool.fromEnvironment(
      'MODRIK_RUNTIME_INSPECTOR',
      defaultValue: false,
    );
    const environment = String.fromEnvironment(
      'MODRIK_RUNTIME_ENVIRONMENT',
      defaultValue: 'production',
    );
    const build = String.fromEnvironment(
      'MODRIK_BUILD_NUMBER',
      defaultValue: 'unknown',
    );
    const version = String.fromEnvironment(
      'MODRIK_BUILD_VERSION',
      defaultValue: 'unknown',
    );
    const commit = String.fromEnvironment(
      'MODRIK_COMMIT_SHA',
      defaultValue: 'unknown',
    );
    return RuntimeDiagnosticsConfig(
      enabled: enabled,
      environment: sanitizeDiagnosticIdentifier(environment, fallback: 'unknown'),
      build: sanitizeDiagnosticIdentifier(build, fallback: 'unknown'),
      version: sanitizeDiagnosticIdentifier(version, fallback: 'unknown'),
      commit: sanitizeDiagnosticIdentifier(commit, fallback: 'unknown'),
    );
  }

  final bool enabled;
  final String environment;
  final String build;
  final String version;
  final String commit;
}

class RuntimeBuildMetadata {
  const RuntimeBuildMetadata({
    required this.environment,
    required this.build,
    required this.version,
    required this.commit,
    required this.platform,
    required this.runtime,
  });

  factory RuntimeBuildMetadata.fromConfig(RuntimeDiagnosticsConfig config) {
    return RuntimeBuildMetadata(
      environment: config.environment,
      build: config.build,
      version: config.version,
      commit: config.commit,
      platform: sanitizeDiagnosticIdentifier(Platform.operatingSystem),
      runtime: 'dart-${Platform.version.split(' ').first}',
    );
  }

  final String environment;
  final String build;
  final String version;
  final String commit;
  final String platform;
  final String runtime;

  Map<String, Object> toJson() => {
        'environment': environment,
        'build': build,
        'version': version,
        'commit': commit,
        'platform': platform,
        'runtime': runtime,
      };
}

class RuntimeDiagnosticEvent {
  const RuntimeDiagnosticEvent({
    required this.timestampUtc,
    required this.severity,
    required this.category,
    required this.correlationId,
    required this.operation,
    required this.result,
    required this.connectivity,
    this.stableCode,
    this.fingerprint,
    this.metadata = const {},
  });

  factory RuntimeDiagnosticEvent.fromJson(Map<String, dynamic> json) {
    final timestamp = DateTime.tryParse(json['timestamp_utc'] as String? ?? '');
    final severity = DiagnosticSeverity.values.where(
      (value) => value.name == json['severity'],
    );
    final connectivity = DiagnosticConnectivity.values.where(
      (value) => value.name == json['connectivity'],
    );
    if (timestamp == null || severity.isEmpty || connectivity.isEmpty) {
      throw const FormatException('Invalid runtime diagnostic event.');
    }
    final rawMetadata = json['metadata'];
    return RuntimeDiagnosticEvent(
      timestampUtc: timestamp.toUtc(),
      severity: severity.first,
      category: sanitizeDiagnosticIdentifier(
        json['category'] as String? ?? 'unknown',
      ),
      correlationId: sanitizeCorrelationId(
        json['correlation_id'] as String? ?? 'unknown',
      ),
      operation: sanitizeDiagnosticIdentifier(
        json['operation'] as String? ?? 'unknown',
      ),
      result: sanitizeDiagnosticIdentifier(
        json['result'] as String? ?? 'unknown',
      ),
      connectivity: connectivity.first,
      stableCode: _nullableSafeIdentifier(json['stable_code']),
      fingerprint: _nullableSafeIdentifier(json['fingerprint']),
      metadata: rawMetadata is Map
          ? sanitizeDiagnosticMetadata(Map<String, dynamic>.from(rawMetadata))
          : const {},
    );
  }

  final DateTime timestampUtc;
  final DiagnosticSeverity severity;
  final String category;
  final String correlationId;
  final String operation;
  final String result;
  final DiagnosticConnectivity connectivity;
  final String? stableCode;
  final String? fingerprint;
  final Map<String, Object> metadata;

  Map<String, Object?> toJson() {
    final json = <String, Object?>{
      'timestamp_utc': timestampUtc.toUtc().toIso8601String(),
      'severity': severity.name,
      'category': category,
      'correlation_id': correlationId,
      'operation': operation,
      'result': result,
      'connectivity': connectivity.name,
    };
    final code = stableCode;
    if (code != null) json['stable_code'] = code;
    final safeFingerprint = fingerprint;
    if (safeFingerprint != null) json['fingerprint'] = safeFingerprint;
    if (metadata.isNotEmpty) json['metadata'] = metadata;
    return json;
  }
}

abstract interface class RuntimeDiagnosticsPersistence {
  Future<String?> read();
  Future<void> write(String encoded);
  Future<void> clear();
}

class FileRuntimeDiagnosticsPersistence implements RuntimeDiagnosticsPersistence {
  FileRuntimeDiagnosticsPersistence({File? file, this.maxReadBytes = 192 * 1024})
      : file = file ??
            File(
              '${Directory.systemTemp.path}${Platform.pathSeparator}'
              'modrik-mobile-runtime-diagnostics-v1.json',
            );

  final File file;
  final int maxReadBytes;

  @override
  Future<String?> read() async {
    try {
      if (!await file.exists()) return null;
      if (await file.length() > maxReadBytes) {
        await clear();
        return null;
      }
      return await file.readAsString();
    } on FileSystemException {
      return null;
    }
  }

  @override
  Future<void> write(String encoded) async {
    try {
      await file.parent.create(recursive: true);
      await file.writeAsString(encoded, flush: true);
    } on FileSystemException {
      // Diagnostics are fail-open: storage failure must never affect learning.
    }
  }

  @override
  Future<void> clear() async {
    try {
      if (await file.exists()) await file.delete();
    } on FileSystemException {
      // Clearing diagnostics is best-effort and must never break the app.
    }
  }
}

class MemoryRuntimeDiagnosticsPersistence implements RuntimeDiagnosticsPersistence {
  String? encoded;

  @override
  Future<String?> read() async => encoded;

  @override
  Future<void> write(String value) async {
    encoded = value;
  }

  @override
  Future<void> clear() async {
    encoded = null;
  }
}

class RuntimeDiagnostics extends ChangeNotifier {
  RuntimeDiagnostics({
    required this.config,
    RuntimeDiagnosticsPersistence? persistence,
    this.maxEvents = 160,
    this.maxBytes = 128 * 1024,
  })  : metadata = RuntimeBuildMetadata.fromConfig(config),
        _persistence = persistence ?? FileRuntimeDiagnosticsPersistence();

  final RuntimeDiagnosticsConfig config;
  final RuntimeBuildMetadata metadata;
  final RuntimeDiagnosticsPersistence _persistence;
  final int maxEvents;
  final int maxBytes;
  final List<RuntimeDiagnosticEvent> _events = [];
  Future<void> _writeTail = Future<void>.value();
  bool _initialized = false;
  bool _disposed = false;

  bool get enabled => config.enabled;
  bool get initialized => _initialized;
  List<RuntimeDiagnosticEvent> get events => List.unmodifiable(_events);
  RuntimeDiagnosticEvent? get lastError {
    for (final event in _events.reversed) {
      if (event.severity == DiagnosticSeverity.error ||
          event.severity == DiagnosticSeverity.critical) {
        return event;
      }
    }
    return null;
  }

  Future<void> initialize() async {
    if (!enabled || _initialized) return;
    var recoveredStorage = false;
    try {
      final encoded = await _persistence.read();
      if (encoded != null && encoded.isNotEmpty) {
        final decoded = jsonDecode(encoded);
        if (decoded is! List) {
          throw const FormatException('Invalid runtime diagnostic timeline.');
        }
        final restored = <RuntimeDiagnosticEvent>[];
        for (final item in decoded.whereType<Map>()) {
          try {
            restored.add(
              RuntimeDiagnosticEvent.fromJson(Map<String, dynamic>.from(item)),
            );
          } on FormatException {
            recoveredStorage = true;
          }
        }
        if (_events.isEmpty) {
          _events.addAll(restored);
        } else {
          _events.insertAll(0, restored);
        }
        _enforceBounds();
      }
    } on Object {
      _events.clear();
      recoveredStorage = true;
      try {
        await _persistence.clear();
      } on Object {
        // Recovery cleanup is best-effort; diagnostics must never fail startup.
      }
    } finally {
      _initialized = true;
      if (recoveredStorage) {
        _events.add(
          RuntimeDiagnosticEvent(
            timestampUtc: DateTime.now().toUtc(),
            severity: DiagnosticSeverity.warn,
            category: 'storage',
            correlationId: 'local',
            operation: 'diagnostics.restore',
            result: 'recovered',
            connectivity: DiagnosticConnectivity.unknown,
            stableCode: 'MOBILE_DIAGNOSTICS_STORAGE_RECOVERED',
          ),
        );
        _enforceBounds();
        _schedulePersist();
      }
      if (!_disposed) notifyListeners();
    }
  }

  String newCorrelationId() => createDiagnosticCorrelationId();

  void record({
    required DiagnosticSeverity severity,
    required String category,
    required String correlationId,
    required String operation,
    required String result,
    DiagnosticConnectivity connectivity = DiagnosticConnectivity.unknown,
    String? stableCode,
    String? fingerprint,
    Map<String, Object?> metadata = const {},
  }) {
    if (!enabled || _disposed) return;
    _events.add(
      RuntimeDiagnosticEvent(
        timestampUtc: DateTime.now().toUtc(),
        severity: severity,
        category: sanitizeDiagnosticIdentifier(category),
        correlationId: sanitizeCorrelationId(correlationId),
        operation: sanitizeDiagnosticIdentifier(operation),
        result: sanitizeDiagnosticIdentifier(result),
        connectivity: connectivity,
        stableCode: _nullableSafeIdentifier(stableCode),
        fingerprint: _nullableSafeIdentifier(fingerprint),
        metadata: sanitizeDiagnosticMetadata(metadata),
      ),
    );
    _enforceBounds();
    notifyListeners();
    _schedulePersist();
  }

  void recordUnexpected(
    Object error,
    StackTrace stack, {
    String operation = 'flutter.unhandled',
    String correlationId = 'local',
  }) {
    final material = '${error.runtimeType}|${_stackFingerprintMaterial(stack)}';
    record(
      severity: DiagnosticSeverity.critical,
      category: 'runtime',
      correlationId: correlationId,
      operation: operation,
      result: 'unexpected_error',
      stableCode: 'MOBILE_UNEXPECTED_ERROR',
      fingerprint: 'fp-${_fnv32(material)}',
    );
  }

  String exportSanitizedJson({
    required String locale,
    required String direction,
    required DiagnosticConnectivity connectivity,
    required String currentFlow,
    int pendingSyncCount = 0,
    int cacheItemCount = 0,
  }) {
    final document = <String, Object>{
      'schema': 'modrik-mobile-diagnostics-v1',
      'generated_at_utc': DateTime.now().toUtc().toIso8601String(),
      'surface': 'mobile',
      'runtime': metadata.toJson(),
      'state': {
        'locale': sanitizeDiagnosticIdentifier(locale),
        'direction': sanitizeDiagnosticIdentifier(direction),
        'connectivity': connectivity.name,
        'current_flow': sanitizeDiagnosticIdentifier(currentFlow),
        'pending_sync_count': pendingSyncCount.clamp(0, 1000000),
        'cache_item_count': cacheItemCount.clamp(0, 1000000),
      },
      'events': _events.map((event) => event.toJson()).toList(growable: false),
    };
    final encoded = jsonEncode(document);
    if (utf8.encode(encoded).length <= maxBytes) return encoded;

    final boundedEvents = _events.toList();
    while (boundedEvents.isNotEmpty) {
      boundedEvents.removeAt(0);
      document['events'] = boundedEvents
          .map((event) => event.toJson())
          .toList(growable: false);
      final candidate = jsonEncode(document);
      if (utf8.encode(candidate).length <= maxBytes) return candidate;
    }
    document['events'] = const <Object>[];
    return jsonEncode(document);
  }

  Future<String?> writeSanitizedExport({
    required String locale,
    required String direction,
    required DiagnosticConnectivity connectivity,
    required String currentFlow,
    int pendingSyncCount = 0,
    int cacheItemCount = 0,
  }) async {
    if (!enabled) return null;
    try {
      final encoded = exportSanitizedJson(
        locale: locale,
        direction: direction,
        connectivity: connectivity,
        currentFlow: currentFlow,
        pendingSyncCount: pendingSyncCount,
        cacheItemCount: cacheItemCount,
      );
      final stamp = DateTime.now().toUtc().millisecondsSinceEpoch;
      final file = File(
        '${Directory.systemTemp.path}${Platform.pathSeparator}'
        'modrik-diagnostics-$stamp.json',
      );
      await file.writeAsString(encoded, flush: true);
      return file.path;
    } on FileSystemException {
      return null;
    }
  }

  Future<void> clear() async {
    _events.clear();
    if (!_disposed) notifyListeners();
    try {
      await _persistence.clear();
    } on Object {
      // Clearing diagnostic storage is best-effort and never domain-fatal.
    }
  }

  String responseCorrelationId(HttpHeaders headers, String fallback) {
    final candidate = headers.value(mobileCorrelationHeader) ??
        headers.value(_fallbackCorrelationHeader);
    return validDiagnosticCorrelationId(candidate) ??
        validDiagnosticCorrelationId(fallback) ??
        createDiagnosticCorrelationId();
  }

  void _enforceBounds() {
    while (_events.length > maxEvents) {
      _events.removeAt(0);
    }
    while (_events.length > 1 && _encodedEventsLength() > maxBytes) {
      _events.removeAt(0);
    }
  }

  int _encodedEventsLength() => utf8.encode(
        jsonEncode(_events.map((event) => event.toJson()).toList(growable: false)),
      ).length;

  void _schedulePersist() {
    if (!enabled) return;
    final snapshot = jsonEncode(
      _events.map((event) => event.toJson()).toList(growable: false),
    );
    _writeTail = _writeTail.then((_) async {
      try {
        await _persistence.write(snapshot);
      } on Object {
        // A custom persistence adapter must not make diagnostics fatal.
      }
    });
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}

String sanitizeCorrelationId(String value) {
  final valid = validDiagnosticCorrelationId(value);
  if (valid != null) return valid;
  if (value == 'local') return value;
  return sanitizeDiagnosticIdentifier(value, fallback: 'unknown');
}

String sanitizeDiagnosticIdentifier(String value, {String fallback = 'unknown'}) {
  final trimmed = value.trim();
  if (trimmed.isEmpty) return fallback;
  if (_emailPattern.hasMatch(trimmed) || _looksSensitiveValue(trimmed)) {
    return 'redacted';
  }
  final bounded = trimmed.length > 128 ? trimmed.substring(0, 128) : trimmed;
  if (_safeIdentifierPattern.hasMatch(bounded)) return bounded;
  final normalized = bounded.replaceAll(RegExp(r'[^A-Za-z0-9._:/-]'), '_');
  return normalized.isEmpty ? fallback : normalized;
}

Map<String, Object> sanitizeDiagnosticMetadata(Map<String, Object?> metadata) {
  final safe = <String, Object>{};
  for (final entry in metadata.entries) {
    final key = entry.key.toLowerCase();
    if (!_safeMetadataKeys.contains(key) || _isSensitiveKey(key)) continue;
    final value = entry.value;
    if (value is bool || value is int) {
      safe[key] = value as Object;
    } else if (value is double && value.isFinite) {
      safe[key] = value;
    } else if (value is String && key == 'status_class') {
      safe[key] = sanitizeDiagnosticIdentifier(value);
    }
  }
  return Map.unmodifiable(safe);
}

String? _nullableSafeIdentifier(Object? value) {
  if (value is! String || value.trim().isEmpty) return null;
  return sanitizeDiagnosticIdentifier(value);
}

bool _isSensitiveKey(String key) =>
    _sensitiveKeyFragments.any((fragment) => key.contains(fragment));

bool _looksSensitiveValue(String value) {
  final lower = value.toLowerCase();
  return _sensitiveValueMarkers.any(lower.contains);
}

String _stackFingerprintMaterial(StackTrace stack) {
  final raw = stack.toString();
  if (raw.isEmpty) return 'no-stack';
  return raw.length > 512 ? raw.substring(0, 512) : raw;
}

String _fnv32(String input) {
  var hash = 0x811c9dc5;
  for (final unit in input.codeUnits) {
    hash ^= unit;
    hash = (hash * 0x01000193) & 0xffffffff;
  }
  return hash.toRadixString(16).padLeft(8, '0');
}
