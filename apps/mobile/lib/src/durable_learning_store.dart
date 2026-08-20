import 'dart:async';
import 'dart:collection';
import 'dart:convert';

import 'package:flutter/services.dart';

import 'models.dart';
import 'offline_boundary.dart';

const int _learningRecoverySchemaVersion = 1;
const String _pendingOperationsBucket = 'pending_operations';
const String _attemptSnapshotBucket = 'attempt_snapshot';
const String _downloadedLessonsBucket = 'downloaded_lessons';

class LearningRecoveryStorageFailure implements Exception {
  const LearningRecoveryStorageFailure(this.code, [this.message]);

  final String code;
  final String? message;

  @override
  String toString() => 'LearningRecoveryStorageFailure($code)';
}

/// Mutable account binding shared by the production learning stores.
///
/// Authentication remains authoritative. This object only prevents persisted
/// learning state from being read or written before an authenticated opaque
/// account ID has been supplied by the existing Auth boundary.
class LearningRecoveryScope {
  String? _accountId;

  String? get accountId => _accountId;

  void bind(String accountId) {
    final normalized = accountId.trim();
    if (normalized.isEmpty) {
      throw const LearningRecoveryStorageFailure(
        'MOBILE_RECOVERY_SCOPE_INVALID',
      );
    }
    _accountId = normalized;
  }

  void unbind() => _accountId = null;

  String requireAccountId() {
    final value = _accountId;
    if (value == null || value.isEmpty) {
      throw const LearningRecoveryStorageFailure(
        'MOBILE_RECOVERY_SCOPE_REQUIRED',
      );
    }
    return value;
  }
}

/// Raw account-scoped persistence boundary used by the typed learning stores.
///
/// Account IDs are opaque Backend identifiers. Platform implementations hash
/// them before using them as native storage keys or filenames.
abstract interface class LearningRecoveryStorage {
  Future<String?> read({required String accountId, required String bucket});
  Future<void> write({
    required String accountId,
    required String bucket,
    required String payload,
  });
  Future<void> remove({required String accountId, required String bucket});
  Future<void> clearAccount(String accountId);
}

class PlatformLearningRecoveryStorage implements LearningRecoveryStorage {
  const PlatformLearningRecoveryStorage();

  static const MethodChannel _channel = MethodChannel(
    'org.modrik.mobile/learning_recovery',
  );

  @override
  Future<String?> read({
    required String accountId,
    required String bucket,
  }) async {
    try {
      return await _channel.invokeMethod<String>('read', {
        'account_id': accountId,
        'bucket': bucket,
      });
    } on PlatformException catch (error) {
      throw LearningRecoveryStorageFailure(
        error.code.isEmpty ? 'MOBILE_RECOVERY_STORAGE_UNAVAILABLE' : error.code,
        error.message,
      );
    }
  }

  @override
  Future<void> write({
    required String accountId,
    required String bucket,
    required String payload,
  }) async {
    try {
      await _channel.invokeMethod<void>('write', {
        'account_id': accountId,
        'bucket': bucket,
        'payload': payload,
      });
    } on PlatformException catch (error) {
      throw LearningRecoveryStorageFailure(
        error.code.isEmpty ? 'MOBILE_RECOVERY_STORAGE_UNAVAILABLE' : error.code,
        error.message,
      );
    }
  }

  @override
  Future<void> remove({
    required String accountId,
    required String bucket,
  }) async {
    try {
      await _channel.invokeMethod<void>('remove', {
        'account_id': accountId,
        'bucket': bucket,
      });
    } on PlatformException catch (error) {
      throw LearningRecoveryStorageFailure(
        error.code.isEmpty ? 'MOBILE_RECOVERY_STORAGE_UNAVAILABLE' : error.code,
        error.message,
      );
    }
  }

  @override
  Future<void> clearAccount(String accountId) async {
    try {
      await _channel.invokeMethod<void>('clear_account', {
        'account_id': accountId,
      });
    } on PlatformException catch (error) {
      throw LearningRecoveryStorageFailure(
        error.code.isEmpty ? 'MOBILE_RECOVERY_STORAGE_UNAVAILABLE' : error.code,
        error.message,
      );
    }
  }
}

/// Deterministic raw persistence double. Reuse the same instance while creating
/// new typed stores to model an OS process reopening the same durable backing
/// data in Flutter tests.
class MemoryLearningRecoveryStorage implements LearningRecoveryStorage {
  final Map<String, String> _payloads = <String, String>{};

  String _key(String accountId, String bucket) => '$accountId\u0000$bucket';

  @override
  Future<String?> read({
    required String accountId,
    required String bucket,
  }) async =>
      _payloads[_key(accountId, bucket)];

  @override
  Future<void> write({
    required String accountId,
    required String bucket,
    required String payload,
  }) async {
    _payloads[_key(accountId, bucket)] = payload;
  }

  @override
  Future<void> remove({
    required String accountId,
    required String bucket,
  }) async {
    _payloads.remove(_key(accountId, bucket));
  }

  @override
  Future<void> clearAccount(String accountId) async {
    final prefix = '$accountId\u0000';
    _payloads.removeWhere((key, _) => key.startsWith(prefix));
  }
}

class DurablePendingOperationStore implements PendingOperationStore {
  DurablePendingOperationStore({
    required this.storage,
    required this.scope,
  });

  final LearningRecoveryStorage storage;
  final LearningRecoveryScope scope;
  final _SerialExecutor _serial = _SerialExecutor();

  @override
  Future<List<PendingLearningOperation>> list() {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final payload = await storage.read(
        accountId: accountId,
        bucket: _pendingOperationsBucket,
      );
      return UnmodifiableListView(_decodePendingOperations(payload));
    });
  }

  @override
  Future<void> put(PendingLearningOperation operation) {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final current = _decodePendingOperations(
        await storage.read(
          accountId: accountId,
          bucket: _pendingOperationsBucket,
        ),
      );
      final index = current.indexWhere(
        (candidate) => candidate.localId == operation.localId,
      );
      if (index >= 0) {
        current[index] = operation;
      } else {
        current.add(operation);
      }
      await storage.write(
        accountId: accountId,
        bucket: _pendingOperationsBucket,
        payload: jsonEncode({
          'schema_version': _learningRecoverySchemaVersion,
          'operations': current.map(_pendingOperationToJson).toList(growable: false),
        }),
      );
    });
  }

  @override
  Future<void> remove(String localId) {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final current = _decodePendingOperations(
        await storage.read(
          accountId: accountId,
          bucket: _pendingOperationsBucket,
        ),
      );
      current.removeWhere((operation) => operation.localId == localId);
      if (current.isEmpty) {
        await storage.remove(
          accountId: accountId,
          bucket: _pendingOperationsBucket,
        );
        return;
      }
      await storage.write(
        accountId: accountId,
        bucket: _pendingOperationsBucket,
        payload: jsonEncode({
          'schema_version': _learningRecoverySchemaVersion,
          'operations': current.map(_pendingOperationToJson).toList(growable: false),
        }),
      );
    });
  }

  @override
  Future<void> clear() {
    final accountId = scope.requireAccountId();
    return _serial.run(
      () => storage.remove(
        accountId: accountId,
        bucket: _pendingOperationsBucket,
      ),
    );
  }
}

class DurableAttemptSnapshotCache implements AttemptSnapshotCache {
  DurableAttemptSnapshotCache({
    required this.storage,
    required this.scope,
  });

  final LearningRecoveryStorage storage;
  final LearningRecoveryScope scope;
  final _SerialExecutor _serial = _SerialExecutor();

  @override
  Future<CachedAttempt?> readLatest() {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final payload = await storage.read(
        accountId: accountId,
        bucket: _attemptSnapshotBucket,
      );
      if (payload == null) return null;
      final decoded = _decodeEnvelope(payload);
      try {
        return CachedAttempt(
          attempt: Attempt.fromJson(_jsonMap(decoded['attempt'])),
          savedAt: DateTime.parse(decoded['saved_at'] as String),
        );
      } on LearningRecoveryStorageFailure {
        rethrow;
      } on Object {
        throw const LearningRecoveryStorageFailure(
          'MOBILE_RECOVERY_STORAGE_INVALID',
        );
      }
    });
  }

  @override
  Future<void> write(Attempt attempt, DateTime savedAt) {
    final accountId = scope.requireAccountId();
    return _serial.run(
      () => storage.write(
        accountId: accountId,
        bucket: _attemptSnapshotBucket,
        payload: jsonEncode({
          'schema_version': _learningRecoverySchemaVersion,
          // Preserve the exact Backend snapshot order. Attempt.toJson() does
          // not sort or reconstruct questions/options.
          'attempt': attempt.toJson(),
          'saved_at': savedAt.toUtc().toIso8601String(),
        }),
      ),
    );
  }

  @override
  Future<void> clear() {
    final accountId = scope.requireAccountId();
    return _serial.run(
      () => storage.remove(
        accountId: accountId,
        bucket: _attemptSnapshotBucket,
      ),
    );
  }
}

class DurableDownloadedContentCache implements DownloadedContentCache {
  DurableDownloadedContentCache({
    required this.storage,
    required this.scope,
  });

  final LearningRecoveryStorage storage;
  final LearningRecoveryScope scope;
  final _SerialExecutor _serial = _SerialExecutor();

  @override
  Future<CachedLesson?> readLesson(String lessonId) {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final lessons = _decodeLessons(
        await storage.read(
          accountId: accountId,
          bucket: _downloadedLessonsBucket,
        ),
      );
      return lessons.where((cached) => cached.lesson.id == lessonId).firstOrNull;
    });
  }

  @override
  Future<List<CachedLesson>> listLessons() {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final lessons = _decodeLessons(
        await storage.read(
          accountId: accountId,
          bucket: _downloadedLessonsBucket,
        ),
      );
      return UnmodifiableListView(lessons);
    });
  }

  @override
  Future<void> writeLesson(Lesson lesson, DateTime savedAt) {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final lessons = _decodeLessons(
        await storage.read(
          accountId: accountId,
          bucket: _downloadedLessonsBucket,
        ),
      );
      final replacement = CachedLesson(lesson: lesson, savedAt: savedAt);
      final index = lessons.indexWhere((cached) => cached.lesson.id == lesson.id);
      if (index >= 0) {
        lessons[index] = replacement;
      } else {
        lessons.add(replacement);
      }
      await storage.write(
        accountId: accountId,
        bucket: _downloadedLessonsBucket,
        payload: jsonEncode({
          'schema_version': _learningRecoverySchemaVersion,
          'lessons': lessons
              .map(
                (cached) => {
                  'lesson': cached.lesson.toJson(),
                  'saved_at': cached.savedAt.toUtc().toIso8601String(),
                },
              )
              .toList(growable: false),
        }),
      );
    });
  }

  @override
  Future<void> removeLesson(String lessonId) {
    final accountId = scope.requireAccountId();
    return _serial.run(() async {
      final lessons = _decodeLessons(
        await storage.read(
          accountId: accountId,
          bucket: _downloadedLessonsBucket,
        ),
      )..removeWhere((cached) => cached.lesson.id == lessonId);
      if (lessons.isEmpty) {
        await storage.remove(
          accountId: accountId,
          bucket: _downloadedLessonsBucket,
        );
        return;
      }
      await storage.write(
        accountId: accountId,
        bucket: _downloadedLessonsBucket,
        payload: jsonEncode({
          'schema_version': _learningRecoverySchemaVersion,
          'lessons': lessons
              .map(
                (cached) => {
                  'lesson': cached.lesson.toJson(),
                  'saved_at': cached.savedAt.toUtc().toIso8601String(),
                },
              )
              .toList(growable: false),
        }),
      );
    });
  }
}

Map<String, Object?> _pendingOperationToJson(PendingLearningOperation operation) => {
      'local_id': operation.localId,
      'type': operation.type.name,
      'logical_command_key': operation.logicalCommandKey,
      'created_at': operation.createdAt.toUtc().toIso8601String(),
      'attempt_id': operation.attemptId,
      'attempt_question_id': operation.attemptQuestionId,
      'expected_revision': operation.expectedRevision,
      'value': operation.value,
      'transport_attempted': operation.transportAttempted,
    };

List<PendingLearningOperation> _decodePendingOperations(String? payload) {
  if (payload == null) return <PendingLearningOperation>[];
  try {
    final decoded = _decodeEnvelope(payload);
    final rawOperations = decoded['operations'];
    if (rawOperations is! List) {
      throw const LearningRecoveryStorageFailure(
        'MOBILE_RECOVERY_STORAGE_INVALID',
      );
    }
    return rawOperations.map((raw) {
      final json = _jsonMap(raw);
      final typeName = json['type'] as String;
      final type = PendingLearningOperationType.values.firstWhere(
        (candidate) => candidate.name == typeName,
      );
      return PendingLearningOperation(
        localId: json['local_id'] as String,
        type: type,
        logicalCommandKey: json['logical_command_key'] as String,
        createdAt: DateTime.parse(json['created_at'] as String),
        attemptId: json['attempt_id'] as String,
        attemptQuestionId: json['attempt_question_id'] as String,
        expectedRevision: (json['expected_revision'] as num).toInt(),
        value: json['value'],
        transportAttempted: json['transport_attempted'] as bool? ?? false,
      );
    }).toList(growable: true);
  } on LearningRecoveryStorageFailure {
    rethrow;
  } on Object {
    throw const LearningRecoveryStorageFailure(
      'MOBILE_RECOVERY_STORAGE_INVALID',
    );
  }
}

List<CachedLesson> _decodeLessons(String? payload) {
  if (payload == null) return <CachedLesson>[];
  try {
    final decoded = _decodeEnvelope(payload);
    final rawLessons = decoded['lessons'];
    if (rawLessons is! List) {
      throw const LearningRecoveryStorageFailure(
        'MOBILE_RECOVERY_STORAGE_INVALID',
      );
    }
    return rawLessons.map((raw) {
      final json = _jsonMap(raw);
      return CachedLesson(
        lesson: Lesson.fromJson(_jsonMap(json['lesson'])),
        savedAt: DateTime.parse(json['saved_at'] as String),
      );
    }).toList(growable: true);
  } on LearningRecoveryStorageFailure {
    rethrow;
  } on Object {
    throw const LearningRecoveryStorageFailure(
      'MOBILE_RECOVERY_STORAGE_INVALID',
    );
  }
}

Map<String, dynamic> _decodeEnvelope(String payload) {
  try {
    final decoded = _jsonMap(jsonDecode(payload));
    if ((decoded['schema_version'] as num?)?.toInt() !=
        _learningRecoverySchemaVersion) {
      throw const LearningRecoveryStorageFailure(
        'MOBILE_RECOVERY_STORAGE_INVALID',
      );
    }
    return decoded;
  } on LearningRecoveryStorageFailure {
    rethrow;
  } on Object {
    throw const LearningRecoveryStorageFailure(
      'MOBILE_RECOVERY_STORAGE_INVALID',
    );
  }
}

Map<String, dynamic> _jsonMap(Object? value) {
  if (value is! Map) {
    throw const LearningRecoveryStorageFailure(
      'MOBILE_RECOVERY_STORAGE_INVALID',
    );
  }
  return Map<String, dynamic>.from(value);
}

class _SerialExecutor {
  Future<void> _tail = Future<void>.value();

  Future<T> run<T>(Future<T> Function() action) {
    final completer = Completer<T>();
    _tail = _tail.then((_) async {
      try {
        completer.complete(await action());
      } on Object catch (error, stackTrace) {
        completer.completeError(error, stackTrace);
      }
    });
    return completer.future;
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull {
    final iterator = this.iterator;
    return iterator.moveNext() ? iterator.current : null;
  }
}
