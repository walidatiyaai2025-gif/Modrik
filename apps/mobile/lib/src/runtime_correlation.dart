import 'dart:math';

const diagnosticCorrelationHeader = 'X-Correlation-ID';
const diagnosticFallbackCorrelationHeader = 'X-Request-ID';
const diagnosticCorrelationMinLength = 16;
const diagnosticCorrelationMaxLength = 96;

final RegExp _correlationPattern = RegExp(
  r'^[A-Za-z0-9][A-Za-z0-9._:-]*$',
);

String? validDiagnosticCorrelationId(String? value) {
  if (value == null) return null;
  final length = value.length;
  if (length < diagnosticCorrelationMinLength ||
      length > diagnosticCorrelationMaxLength) {
    return null;
  }
  final match = _correlationPattern.firstMatch(value);
  if (match == null || match.start != 0 || match.end != length) return null;
  return value;
}

String createDiagnosticCorrelationId() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  final hex = bytes
      .map((value) => value.toRadixString(16).padLeft(2, '0'))
      .join();
  return '${hex.substring(0, 8)}-'
      '${hex.substring(8, 12)}-'
      '${hex.substring(12, 16)}-'
      '${hex.substring(16, 20)}-'
      '${hex.substring(20)}';
}
