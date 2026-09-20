import 'package:flutter/services.dart';

enum StudentTextScalePreference {
  normal('normal', 1.0),
  large('large', 1.25),
  largest('largest', 1.5);

  const StudentTextScalePreference(this.storageValue, this.factor);

  final String storageValue;
  final double factor;

  static StudentTextScalePreference? fromStorage(Object? value) {
    if (value is! String) return null;
    for (final preference in values) {
      if (preference.storageValue == value) return preference;
    }
    return null;
  }
}

abstract interface class StudentPreferenceStore {
  Future<StudentTextScalePreference?> readTextScale();
  Future<void> writeTextScale(StudentTextScalePreference preference);
}

class StudentPreferenceStorageFailure implements Exception {
  const StudentPreferenceStorageFailure(this.code);

  final String code;

  @override
  String toString() => 'StudentPreferenceStorageFailure($code)';
}

class PlatformStudentPreferenceStore implements StudentPreferenceStore {
  const PlatformStudentPreferenceStore();

  static const MethodChannel _channel = MethodChannel(
    'org.modrik.mobile/student_preferences',
  );

  @override
  Future<StudentTextScalePreference?> readTextScale() async {
    try {
      final raw = await _channel.invokeMethod<String>('read_text_scale');
      return StudentTextScalePreference.fromStorage(raw);
    } on PlatformException {
      throw const StudentPreferenceStorageFailure(
        'MOBILE_STUDENT_PREFERENCES_UNAVAILABLE',
      );
    }
  }

  @override
  Future<void> writeTextScale(StudentTextScalePreference preference) async {
    try {
      await _channel.invokeMethod<void>(
        'write_text_scale',
        preference.storageValue,
      );
    } on PlatformException {
      throw const StudentPreferenceStorageFailure(
        'MOBILE_STUDENT_PREFERENCES_UNAVAILABLE',
      );
    }
  }
}

class MemoryStudentPreferenceStore implements StudentPreferenceStore {
  MemoryStudentPreferenceStore({
    StudentTextScalePreference? initialTextScale,
  }) : _textScale = initialTextScale;

  StudentTextScalePreference? _textScale;

  @override
  Future<StudentTextScalePreference?> readTextScale() async => _textScale;

  @override
  Future<void> writeTextScale(StudentTextScalePreference preference) async {
    _textScale = preference;
  }
}
